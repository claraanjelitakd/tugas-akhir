<?php

namespace App\Services\Export;

use App\Models\EvaluasiAtasan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\LinkedinSyncResult;
use App\Models\Prodi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AlumniProdiMultiSheetExcelExporter
 *
 * Layanan ekspor berkas Excel (.xls SpreadsheetML) komprehensif per Program Studi
 * yang menyajikan data seluruh alumni dalam 5 lembar kerja (worksheet):
 * 1. Sheet 1: Profil Alumni (identitas lengkap, demografi, status akademik, & karier).
 * 2. Sheet 2: Kuesioner Universitas (jawaban butir pertanyaan kuesioner tingkat universitas).
 * 3. Sheet 3: Kuesioner Prodi (jawaban butir pertanyaan spesifik program studi).
 * 4. Sheet 4: Evaluasi Atasan (identitas atasan & perusahaan serta penilaian kinerja).
 * 5. Sheet 5: Data LinkedIn (data terstruktur hasil penarikan profil LinkedIn alumni).
 *
 * Menggunakan pendekatan batch query agregat (bebas N+1) untuk mencegah timeout dan lag.
 */
class AlumniProdiMultiSheetExcelExporter
{
    /**
     * Unduh berkas Excel multi-sheet per Program Studi dalam format streaming.
     *
     * @param  int  $prodiId  ID Program Studi yang wajib menjadi constraint ekspor.
     * @param  string|null  $tahun  Filter tahun kelulusan kohort (opsional).
     * @param  string|null  $semester  Filter semester kelulusan Gasal/Genap (opsional).
     */
    public static function download(int $prodiId, ?string $tahun = null, ?string $semester = null): StreamedResponse
    {
        $prodi = Prodi::with('fakultas')->findOrFail($prodiId);
        $cleanProdiName = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $prodi->nama_prodi);
        $cleanTahun = ($tahun && $tahun !== 'all') ? preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $tahun) : 'SemuaTahun';
        $timestamp = now()->format('Ymd_His');
        $filename = "Tracer_Prodi_{$cleanProdiName}_{$cleanTahun}_{$timestamp}.xls";

        return response()->streamDownload(function () use ($prodi, $tahun, $semester) {
            echo self::generateSpreadsheetXml($prodi, $tahun, $semester);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Menghasilkan string dokumen XML Spreadsheet 2003 (SpreadsheetML) multi-sheet.
     *
     * @param  Prodi  $prodi  Model Program Studi
     * @param  string|null  $tahun  Tahun kelulusan
     * @param  string|null  $semester  Semester kelulusan
     */
    public static function generateSpreadsheetXml(Prodi $prodi, ?string $tahun = null, ?string $semester = null): string
    {
        // 1. Ambil data seluruh alumni prodi melalui view agregat v_alumni_audit_rekap
        $queryAlumni = DB::table('v_alumni_audit_rekap')->where('prodi_id', $prodi->id);

        if ($tahun && $tahun !== 'all') {
            $tahunNormalized = preg_match('/(\d{4})/', (string) $tahun, $matches) ? $matches[1] : $tahun;
            $queryAlumni->where(function ($q) use ($tahun, $tahunNormalized) {
                $q->where('tahun_lulus', $tahunNormalized)
                    ->orWhere('tahun_lulus', $tahun);
            });
        }

        if ($semester && $semester !== 'all') {
            $queryAlumni->where('tahun_akademik_lulus', 'like', "%{$semester}%");
        }

        $alumnis = $queryAlumni->orderBy('nim', 'asc')->get();
        $biodataIds = $alumnis->pluck('biodata_id')->filter()->all();

        // 2. Ambil master instrumen pertanyaan (Universitas, Prodi, & Atasan) secara batch
        $univQuestions = self::getQuestionsByTarget('univ');
        $prodiQuestions = self::getQuestionsByTarget('prodi', $prodi->id);
        $atasanQuestions = self::getQuestionsByTarget('atasan');

        // 3. Ambil data jawaban kuesioner alumni dalam 1 kueri agregat
        $savedAnswers = empty($biodataIds) ? collect() : KuesionerJawaban::whereIn('biodata_id', $biodataIds)
            ->whereNull('evaluasi_atasan_id')
            ->get();

        $answersLookup = [];
        foreach ($savedAnswers as $ans) {
            $answersLookup[$ans->biodata_id][$ans->pertanyaan_id] = $ans;
        }

        // 4. Ambil data evaluasi atasan dalam 1 kueri agregat
        $evaluasiMap = empty($biodataIds) ? collect() : EvaluasiAtasan::whereIn('biodata_id', $biodataIds)
            ->with(['atasan', 'perusahaan', 'respons.pertanyaan', 'respons.opsi'])
            ->orderByDesc('id')
            ->get()
            ->keyBy('biodata_id');

        // 5. Ambil data sinkronisasi LinkedIn dalam 1 kueri agregat
        $syncMap = empty($biodataIds) ? collect() : LinkedinSyncResult::whereIn('biodata_id', $biodataIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('biodata_id');

        // 6. Susun berkas SpreadsheetML
        $exportTime = now()->format('d/m/Y H:i:s');
        $namaProdi = $prodi->nama_prodi;
        $namaFakultas = $prodi->fakultas?->nama_fakultas ?? 'Universitas Kristen Duta Wacana';
        $filterTahunLabel = ($tahun && $tahun !== 'all') ? $tahun : 'Semua Angkatan';

        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<?mso-application progid="Excel.Sheet"?>'."\n";
        ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Tracer Study UKDW</Author>
  <Created><?= now()->utc()->format('Y-m-d\\TH:i:s\\Z') ?></Created>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#1E293B"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="Title">
   <Font ss:FontName="Calibri" ss:Size="13" ss:Bold="1" ss:Color="#0D542B"/>
  </Style>
  <Style ss:ID="SubTitle">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#475569"/>
  </Style>
  <Style ss:ID="MetaLabel">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#334155"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="MetaValue">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#0F172A"/>
  </Style>
  <Style ss:ID="TableHeader">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0D542B" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TableBody">
   <Alignment ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10"/>
  </Style>
  <Style ss:ID="TableBodyCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10"/>
  </Style>
  <Style ss:ID="TableBodyRight">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10"/>
  </Style>
  <Style ss:ID="TextFormat">
   <Alignment ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10"/>
   <NumberFormat ss:Format="@"/>
  </Style>
  <Style ss:ID="BadgeSudah">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#0D542B"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="BadgeBelum">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#991B1B"/>
   <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
  </Style>
 </Styles>

 <?php
        // RENDER SHEET 1: PROFIL ALUMNI
        self::renderSheetProfil($alumnis, $namaProdi, $namaFakultas, $filterTahunLabel, $exportTime);

        // RENDER SHEET 2: KUESIONER UNIVERSITAS
        self::renderSheetKuesionerUniv($alumnis, $univQuestions, $answersLookup, $namaProdi, $filterTahunLabel);

        // RENDER SHEET 3: KUESIONER PRODI
        self::renderSheetKuesionerProdi($alumnis, $prodiQuestions, $answersLookup, $namaProdi, $filterTahunLabel);

        // RENDER SHEET 4: EVALUASI ATASAN
        self::renderSheetEvaluasiAtasan($alumnis, $atasanQuestions, $evaluasiMap, $namaProdi, $filterTahunLabel);

        // RENDER SHEET 5: DATA LINKEDIN
        self::renderSheetLinkedIn($alumnis, $syncMap, $namaProdi, $filterTahunLabel);
        ?>
</Workbook>
<?php
        return ob_get_clean();
    }

    /**
     * Lembar Kerja 1: Profil & Identitas Lengkap Alumni
     *
     * @param  Collection<int, object>  $alumnis
     */
    private static function renderSheetProfil(Collection $alumnis, string $prodi, string $fakultas, string $tahun, string $exportTime): void
    {
        $headers = [
            'No', 'NIM', 'Nama Lengkap', 'Jenis Kelamin', 'Tanggal Lahir', 'Warga Negara',
            'Status Mahasiswa', 'Angkatan Masuk', 'Tahun Lulus', 'Semester Lulus', 'IPK',
            'Email', 'Nomor Telepon/WA',
            'Alamat Domisili', 'Kelurahan', 'Kecamatan', 'Kabupaten/Kota', 'Provinsi', 'Kode Pos',
            'Instagram', 'Facebook', 'LinkedIn URL', 'Status Tracer', 'Kelengkapan Univ (%)', 'Kelengkapan Prodi (%)',
            'Aktivitas/Karier', 'Posisi/Jabatan', 'Bidang Pekerjaan', 'Divisi/Unit', 'Gaji Pertama (Rp)',
            'Nama Perusahaan', 'Sektor Perusahaan', 'Skala Perusahaan', 'Jenis Perusahaan', 'Lokasi Perusahaan', 'Negara Perusahaan', 'Alamat Perusahaan',
            'Pendidikan Lanjut: Tingkat', 'Pendidikan Lanjut: Perguruan Tinggi', 'Pendidikan Lanjut: Program Studi',
            'Nama Atasan', 'Email Atasan', 'Telepon Atasan',
        ];
        ?>
 <Worksheet ss:Name="Profil Alumni">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="40"/>
   <Column ss:Width="90"/>
   <Column ss:Width="180"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="100"/>
   <Column ss:Width="90"/>
   <Column ss:Width="80"/>
   <Column ss:Width="90"/>
   <Column ss:Width="60"/>
   <Column ss:Width="160"/>
   <Column ss:Width="110"/>
   <Column ss:Width="200"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
   <Column ss:Width="70"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
   <Column ss:Width="150"/>
   <Column ss:Width="110"/>
   <Column ss:Width="90"/>
   <Column ss:Width="90"/>
   <Column ss:Width="120"/>
   <Column ss:Width="140"/>
   <Column ss:Width="130"/>
   <Column ss:Width="120"/>
   <Column ss:Width="110"/>
   <Column ss:Width="180"/>
   <Column ss:Width="120"/>
   <Column ss:Width="110"/>
   <Column ss:Width="110"/>
   <Column ss:Width="110"/>
   <Column ss:Width="110"/>
   <Column ss:Width="200"/>
   <Column ss:Width="100"/>
   <Column ss:Width="160"/>
   <Column ss:Width="140"/>
   <Column ss:Width="140"/>
   <Column ss:Width="150"/>
   <Column ss:Width="110"/>

   <!-- Header Info Dokumen -->
   <Row ss:Height="26">
    <Cell ss:MergeAcross="4" ss:StyleID="Title"><Data ss:Type="String">REKAPITULASI PROFIL ALUMNI TRACER STUDY</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="4" ss:StyleID="SubTitle"><Data ss:Type="String">Program Studi: <?= self::xmlEscape($prodi) ?> | Fakultas: <?= self::xmlEscape($fakultas) ?></Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="4" ss:StyleID="SubTitle"><Data ss:Type="String">Kohort Angkatan: <?= self::xmlEscape($tahun) ?> | Tanggal Ekspor: <?= self::xmlEscape($exportTime) ?> WIB</Data></Cell>
   </Row>
   <Row ss:Height="12"/>

   <!-- Table Header -->
   <Row ss:Height="28">
    <?php foreach ($headers as $h) { ?>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String"><?= self::xmlEscape($h) ?></Data></Cell>
    <?php } ?>
   </Row>

   <!-- Table Body Rows -->
   <?php
   $no = 1;
        foreach ($alumnis as $row) {
            $isComplete = (bool) ($row->is_complete_total ?? false);
            $statusLabel = $isComplete ? 'Selesai' : 'Belum Selesai';
            $badgeStyle = $isComplete ? 'BadgeSudah' : 'BadgeBelum';
            $tglLahir = $row->tanggal_lahir ? date('d-m-Y', strtotime((string) $row->tanggal_lahir)) : '-';
            $emailKontak = $row->email_pribadi ?: ($row->email ?: '-');
            ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->nim) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($row->jenis_kelamin === 'L' ? 'Laki-Laki' : ($row->jenis_kelamin === 'P' ? 'Perempuan' : ($row->jenis_kelamin ?: '-'))) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($tglLahir) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($row->warga_negara ?: 'WNI') ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($row->status_mahasiswa === 'L' ? 'Lulus' : ($row->status_mahasiswa ?: '-')) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($row->angkatan_masuk ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($row->tahun_lulus ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($row->tahun_akademik_lulus ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="<?= is_numeric($row->ipk) ? 'Number' : 'String' ?>"><?= self::xmlEscape($row->ipk ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($emailKontak) ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->nomor_telepon ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->alamat ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->kelurahan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->kecamatan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama_kabupaten ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama_provinsi ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->kode_pos ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->instagram_url ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->facebook_url ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->linkedin_url ?: ($row->linkedin_username ?: '-')) ?></Data></Cell>
    <Cell ss:StyleID="<?= $badgeStyle ?>"><Data ss:Type="String"><?= $statusLabel ?></Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="String"><?= round((float) ($row->univ_percentage ?? 0), 1) ?>%</Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="String"><?= round((float) ($row->prodi_percentage ?? 0), 1) ?>%</Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->kategori_pekerjaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->posisi_jabatan ?: ($row->posisi_wiraswasta ?: '-')) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->bidang_pekerjaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->divisi ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="<?= is_numeric($row->gaji) ? 'Number' : 'String' ?>"><?= self::xmlEscape($row->gaji ? (string) $row->gaji : '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama_perusahaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->sektor_perusahaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->skala_perusahaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->jenis_perusahaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->jenis_lokasi_perusahaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama_negara_perusahaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->alamat_perusahaan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->pendidikan_tingkat ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->perguruan_tinggi ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->pendidikan_prodi ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama_atasan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->email_atasan ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->telepon_atasan ?: '-') ?></Data></Cell>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
        <?php
    }

    /**
     * Lembar Kerja 2: Butir Instrumen & Jawaban Kuesioner Universitas
     *
     * @param  Collection<int, object>  $alumnis
     * @param  Collection<int, object>  $questions
     * @param  array<int, array<int, mixed>>  $answersLookup
     */
    private static function renderSheetKuesionerUniv(Collection $alumnis, Collection $questions, array $answersLookup, string $prodi, string $tahun): void
    {
        ?>
 <Worksheet ss:Name="Kuesioner Univ">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="40"/>
   <Column ss:Width="90"/>
   <Column ss:Width="180"/>
   <Column ss:Width="100"/>
   <?php foreach ($questions as $q) { ?>
   <Column ss:Width="200"/>
   <?php } ?>

   <Row ss:Height="26">
    <Cell ss:MergeAcross="3" ss:StyleID="Title"><Data ss:Type="String">HASIL KUESIONER TRACER STUDY TINGKAT UNIVERSITAS</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="3" ss:StyleID="SubTitle"><Data ss:Type="String">Program Studi: <?= self::xmlEscape($prodi) ?> | Angkatan: <?= self::xmlEscape($tahun) ?></Data></Cell>
   </Row>
   <Row ss:Height="12"/>

   <!-- Header Baris Pertanyaan -->
   <Row ss:Height="40">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">NIM</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Alumni</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Status Univ</Data></Cell>
    <?php foreach ($questions as $q) {
        $kode = trim((string) $q->kode_pertanyaan);
        $label = trim((string) ($q->label_pertanyaan ?: $q->teks_pertanyaan));
        $headerText = "[{$kode}] {$label}";
        ?>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String"><?= self::xmlEscape($headerText) ?></Data></Cell>
    <?php } ?>
   </Row>

   <!-- Body Baris Responden -->
   <?php
   $no = 1;
        foreach ($alumnis as $row) {
            $isComplete = (bool) ($row->is_complete_univ ?? false);
            $badgeStyle = $isComplete ? 'BadgeSudah' : 'BadgeBelum';
            $statusLabel = $isComplete ? 'Lengkap' : round((float) ($row->univ_percentage ?? 0), 0).'%';
            ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->nim) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama) ?></Data></Cell>
    <Cell ss:StyleID="<?= $badgeStyle ?>"><Data ss:Type="String"><?= $statusLabel ?></Data></Cell>
    <?php foreach ($questions as $q) {
        $ans = $answersLookup[$row->biodata_id][$q->id] ?? null;
        $val = self::resolveQuestionValue($row, $q, $ans);
        ?>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($val) ?></Data></Cell>
    <?php } ?>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
        <?php
    }

    /**
     * Lembar Kerja 3: Butir Instrumen & Jawaban Kuesioner Program Studi
     *
     * @param  Collection<int, object>  $alumnis
     * @param  Collection<int, object>  $questions
     * @param  array<int, array<int, mixed>>  $answersLookup
     */
    private static function renderSheetKuesionerProdi(Collection $alumnis, Collection $questions, array $answersLookup, string $prodi, string $tahun): void
    {
        ?>
 <Worksheet ss:Name="Kuesioner Prodi">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="40"/>
   <Column ss:Width="90"/>
   <Column ss:Width="180"/>
   <Column ss:Width="100"/>
   <?php foreach ($questions as $q) { ?>
   <Column ss:Width="200"/>
   <?php } ?>

   <Row ss:Height="26">
    <Cell ss:MergeAcross="3" ss:StyleID="Title"><Data ss:Type="String">HASIL KUESIONER SPESIFIK PROGRAM STUDI</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="3" ss:StyleID="SubTitle"><Data ss:Type="String">Program Studi: <?= self::xmlEscape($prodi) ?> | Angkatan: <?= self::xmlEscape($tahun) ?></Data></Cell>
   </Row>
   <Row ss:Height="12"/>

   <!-- Header Baris Pertanyaan -->
   <Row ss:Height="40">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">NIM</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Alumni</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Status Prodi</Data></Cell>
    <?php foreach ($questions as $q) {
        $kode = trim((string) $q->kode_pertanyaan);
        $label = trim((string) ($q->label_pertanyaan ?: $q->teks_pertanyaan));
        $headerText = "[{$kode}] {$label}";
        ?>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String"><?= self::xmlEscape($headerText) ?></Data></Cell>
    <?php } ?>
   </Row>

   <!-- Body Baris Responden -->
   <?php
   $no = 1;
        foreach ($alumnis as $row) {
            $isComplete = (bool) ($row->is_complete_prodi ?? false);
            $badgeStyle = $isComplete ? 'BadgeSudah' : 'BadgeBelum';
            $statusLabel = $isComplete ? 'Lengkap' : round((float) ($row->prodi_percentage ?? 0), 0).'%';
            ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->nim) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama) ?></Data></Cell>
    <Cell ss:StyleID="<?= $badgeStyle ?>"><Data ss:Type="String"><?= $statusLabel ?></Data></Cell>
    <?php foreach ($questions as $q) {
        $ans = $answersLookup[$row->biodata_id][$q->id] ?? null;
        $val = self::resolveQuestionValue($row, $q, $ans);
        ?>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($val) ?></Data></Cell>
    <?php } ?>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
        <?php
    }

    /**
     * Lembar Kerja 4: Hasil Evaluasi Pengguna Lulusan / Atasan
     *
     * @param  Collection<int, object>  $alumnis
     * @param  Collection<int, object>  $questions
     * @param  Collection<int, EvaluasiAtasan>  $evaluasiMap
     */
    private static function renderSheetEvaluasiAtasan(Collection $alumnis, Collection $questions, Collection $evaluasiMap, string $prodi, string $tahun): void
    {
        ?>
 <Worksheet ss:Name="Evaluasi Atasan">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="40"/>
   <Column ss:Width="90"/>
   <Column ss:Width="180"/>
   <Column ss:Width="160"/>
   <Column ss:Width="150"/>
   <Column ss:Width="150"/>
   <Column ss:Width="110"/>
   <Column ss:Width="110"/>
   <Column ss:Width="130"/>
   <?php foreach ($questions as $q) { ?>
   <Column ss:Width="180"/>
   <?php } ?>

   <Row ss:Height="26">
    <Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">HASIL EVALUASI PENGGUNA LULUSAN (ATASAN ALUMNI)</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="8" ss:StyleID="SubTitle"><Data ss:Type="String">Program Studi: <?= self::xmlEscape($prodi) ?> | Angkatan: <?= self::xmlEscape($tahun) ?></Data></Cell>
   </Row>
   <Row ss:Height="12"/>

   <!-- Header Baris Pertanyaan -->
   <Row ss:Height="40">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">NIM</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Alumni</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Perusahaan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Atasan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Email Atasan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Telepon Atasan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Status Pengisian</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Tanggal Pengisian</Data></Cell>
    <?php foreach ($questions as $q) {
        $kode = trim((string) $q->kode_pertanyaan);
        $label = trim((string) ($q->label_pertanyaan ?: $q->teks_pertanyaan));
        $headerText = "[{$kode}] {$label}";
        ?>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String"><?= self::xmlEscape($headerText) ?></Data></Cell>
    <?php } ?>
   </Row>

   <!-- Body Baris Alumni -->
   <?php
   $no = 1;
        foreach ($alumnis as $row) {
            $ev = $evaluasiMap->get($row->biodata_id);
            $atasanNama = $ev?->atasan?->nama ?: ($row->nama_atasan ?: '-');
            $atasanEmail = $ev?->atasan?->email ?: ($row->email_atasan ?: '-');
            $atasanTelp = $ev?->atasan?->telepon ?: ($row->telepon_atasan ?: '-');
            $perusahaanNama = $ev?->perusahaan?->nama_perusahaan ?: ($row->nama_perusahaan ?: '-');
            $isSubmitted = (bool) ($ev?->is_submitted ?? false);
            $statusLabel = $isSubmitted ? 'Sudah Diisi' : ($ev ? 'Menunggu Respon' : 'Belum Ada Data Atasan');
            $badgeStyle = $isSubmitted ? 'BadgeSudah' : ($ev ? 'TableBodyCenter' : 'BadgeBelum');
            $tglIsi = $ev?->submitted_at ? $ev->submitted_at->format('d-m-Y H:i') : '-';

            // Petakan jawaban butir evaluasi atasan berdasarkan pertanyaan_id
            $evAnswerMap = [];
            if ($ev && $ev->relationLoaded('respons')) {
                foreach ($ev->respons as $r) {
                    $evAnswerMap[$r->pertanyaan_id] = $r;
                }
            }
            ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->nim) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($perusahaanNama) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($atasanNama) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($atasanEmail) ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($atasanTelp) ?></Data></Cell>
    <Cell ss:StyleID="<?= $badgeStyle ?>"><Data ss:Type="String"><?= $statusLabel ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($tglIsi) ?></Data></Cell>
    <?php foreach ($questions as $q) {
        $r = $evAnswerMap[$q->id] ?? null;
        $val = self::formatAnswerValue($r);
        ?>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($val) ?></Data></Cell>
    <?php } ?>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
        <?php
    }

    /**
     * Lembar Kerja 5: Data Terstruktur Profil Hasil Scraping LinkedIn
     *
     * @param  Collection<int, object>  $alumnis
     * @param  Collection<int, Collection<int, LinkedinSyncResult>>  $syncMap
     */
    private static function renderSheetLinkedIn(Collection $alumnis, Collection $syncMap, string $prodi, string $tahun): void
    {
        $headers = [
            'No', 'NIM', 'Nama Alumni', 'Status LinkedIn', 'URL Profil LinkedIn',
            'Nama di LinkedIn', 'Headline LinkedIn', 'Tentang Profil (About)',
            'Perusahaan Terkini', 'Posisi Terkini', 'Divisi / Unit', 'Industri Perusahaan',
            'Periode Masa Kerja', 'Durasi Kerja', 'Lokasi Kerja',
            'Pendidikan LinkedIn', 'Daftar Keahlian (Skills)', 'Riwayat Pengalaman Kerja',
            'Sertifikasi', 'Koneksi & Followers', 'Tanggal Penarikan Data',
        ];
        ?>
 <Worksheet ss:Name="Data LinkedIn">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="40"/>
   <Column ss:Width="90"/>
   <Column ss:Width="180"/>
   <Column ss:Width="120"/>
   <Column ss:Width="200"/>
   <Column ss:Width="160"/>
   <Column ss:Width="220"/>
   <Column ss:Width="250"/>
   <Column ss:Width="180"/>
   <Column ss:Width="160"/>
   <Column ss:Width="120"/>
   <Column ss:Width="150"/>
   <Column ss:Width="130"/>
   <Column ss:Width="100"/>
   <Column ss:Width="140"/>
   <Column ss:Width="220"/>
   <Column ss:Width="220"/>
   <Column ss:Width="250"/>
   <Column ss:Width="200"/>
   <Column ss:Width="130"/>
   <Column ss:Width="120"/>

   <Row ss:Height="26">
    <Cell ss:MergeAcross="4" ss:StyleID="Title"><Data ss:Type="String">DATA REKAM JEJAK KARIER LINKEDIN ALUMNI</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="4" ss:StyleID="SubTitle"><Data ss:Type="String">Program Studi: <?= self::xmlEscape($prodi) ?> | Angkatan: <?= self::xmlEscape($tahun) ?></Data></Cell>
   </Row>
   <Row ss:Height="12"/>

   <!-- Table Header -->
   <Row ss:Height="28">
    <?php foreach ($headers as $h) { ?>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String"><?= self::xmlEscape($h) ?></Data></Cell>
    <?php } ?>
   </Row>

   <!-- Body Baris LinkedIn -->
   <?php
   $no = 1;
        foreach ($alumnis as $row) {
            $syncList = $syncMap->get($row->biodata_id);
            /** @var LinkedinSyncResult|null $latestSync */
            $latestSync = $syncList ? $syncList->first() : null;

            $statusSync = 'Belum Sinkron';
            $badgeStyle = 'TableBodyCenter';
            if ($latestSync) {
                if ($latestSync->isApproved()) {
                    $statusSync = 'Disetujui (Approved)';
                    $badgeStyle = 'BadgeSudah';
                } elseif ($latestSync->isPending()) {
                    $statusSync = 'Menunggu Review';
                    $badgeStyle = 'TableBodyCenter';
                } elseif ($latestSync->isRejected()) {
                    $statusSync = 'Ditolak (Rejected)';
                    $badgeStyle = 'BadgeBelum';
                }
            }

            $preview = $latestSync ? $latestSync->getPreviewData() : [];
            $rawItem = $latestSync ? $latestSync->getProfileItem() : [];

            $liUrl = $latestSync?->linkedin_url ?: ($row->linkedin_url ?: ($row->linkedin_username ?: '-'));
            $liName = $preview['name'] ?? ($rawItem['full_name'] ?? ($rawItem['fullName'] ?? '-'));
            $headline = $preview['headline'] ?? ($rawItem['headline'] ?? '-');
            $about = $preview['about'] ?? ($rawItem['about'] ?? ($rawItem['summary'] ?? '-'));
            $currentCompany = $preview['current_company'] ?? ($row->nama_perusahaan ?: '-');
            $currentPosition = $preview['current_job'] ?? ($preview['current_position'] ?? ($row->posisi_jabatan ?: '-'));
            $currentDivisi = $preview['current_divisi'] ?? ($row->divisi ?: '-');

            // Ekstraksi Industri Perusahaan dari Objek {id, name} maupun string
            $industry = $preview['industry'] ?? null;
            if (! $industry && ! empty($rawItem['industry'])) {
                $industry = is_array($rawItem['industry']) ? ($rawItem['industry']['name'] ?? null) : $rawItem['industry'];
            }
            if (! $industry && ! empty($rawItem['company_industry'])) {
                $industry = $rawItem['company_industry'];
            }
            $industry = $industry ?: '-';

            // Ekstraksi Periode Masa Kerja & Durasi
            $firstExp = ! empty($preview['experience']) && is_array($preview['experience']) ? $preview['experience'][0] : null;
            $periodeKerja = '-';
            $durasiKerja = '-';
            if ($firstExp) {
                if (! empty($firstExp['date'])) {
                    $periodeKerja = (string) $firstExp['date'];
                } elseif (! empty($firstExp['start_date']) || ! empty($firstExp['end_date'])) {
                    $st = is_array($firstExp['start_date']) ? ($firstExp['start_date']['text'] ?? '') : (string) $firstExp['start_date'];
                    $et = ! empty($firstExp['is_current']) ? 'Sekarang' : (is_array($firstExp['end_date']) ? ($firstExp['end_date']['text'] ?? '') : (string) ($firstExp['end_date'] ?? '-'));
                    $periodeKerja = trim(($st ?: '?').' s/d '.($et ?: '?'));
                }
                if (! empty($firstExp['time_period'])) {
                    $durasiKerja = (string) $firstExp['time_period'];
                }
            }

            // Lokasi Kerja
            $location = $preview['location'] ?? ($firstExp['location'] ?? '-');

            // Riwayat Pendidikan LinkedIn
            $eduLines = [];
            if (! empty($preview['education']) && is_array($preview['education'])) {
                foreach ($preview['education'] as $edu) {
                    $sch = $edu['school'] ?? '';
                    $deg = $edu['degree'] ?? '';
                    $fld = $edu['field_of_study'] ?? '';
                    $dt = $edu['date'] ?? '';
                    $part = array_filter([$sch, $deg, $fld, $dt]);
                    if (! empty($part)) {
                        $eduLines[] = implode(' - ', $part);
                    }
                }
            }
            $eduSummary = ! empty($eduLines) ? implode(' | ', $eduLines) : '-';

            // Keahlian / Skills
            $skillsList = ! empty($preview['skills']) && is_array($preview['skills']) ? implode(', ', $preview['skills']) : ($row->skills ?: '-');

            // Riwayat Pengalaman Kerja Lengkap
            $expLines = [];
            if (! empty($preview['experiences']) && is_array($preview['experiences'])) {
                foreach ($preview['experiences'] as $exp) {
                    $t = $exp['position'] ?? ($exp['title'] ?? '');
                    $c = $exp['company'] ?? '';
                    $d = $exp['date'] ?? ($exp['time_period'] ?? '');
                    $line = trim("{$t} di {$c}");
                    if ($d) {
                        $line .= " ({$d})";
                    }
                    if ($line !== 'di' && $line !== '') {
                        $expLines[] = $line;
                    }
                }
            }
            $expHistory = ! empty($expLines) ? implode(' | ', $expLines) : ($row->experience ?: '-');

            // Sertifikasi
            $certLines = [];
            if (! empty($preview['certifications']) && is_array($preview['certifications'])) {
                foreach ($preview['certifications'] as $cert) {
                    $ct = $cert['title'] ?? '';
                    $ca = $cert['issued_by'] ?? '';
                    if ($ct || $ca) {
                        $certLines[] = trim("{$ct}".($ca ? " ({$ca})" : ''));
                    }
                }
            }
            $certSummary = ! empty($certLines) ? implode(' | ', $certLines) : '-';

            // Koneksi & Followers
            $conn = $preview['connections'] ?? ($rawItem['connectionsCount'] ?? ($rawItem['li_number_connections'] ?? null));
            $foll = $preview['followers'] ?? ($rawItem['followerCount'] ?? ($rawItem['li_number_followers'] ?? null));
            $connFollText = ($conn !== null || $foll !== null) ? "{$conn} koneksi / {$foll} followers" : '-';

            $scrapedAt = $latestSync?->scraped_at ? $latestSync->scraped_at->format('d-m-Y H:i') : '-';
            ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($row->nim) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($row->nama) ?></Data></Cell>
    <Cell ss:StyleID="<?= $badgeStyle ?>"><Data ss:Type="String"><?= $statusSync ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($liUrl) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($liName) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($headline) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($about) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($currentCompany) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($currentPosition) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($currentDivisi) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($industry) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($periodeKerja) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($durasiKerja) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($location) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($eduSummary) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($skillsList) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($expHistory) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($certSummary) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($connFollText) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($scrapedAt) ?></Data></Cell>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
        <?php
    }

    /**
     * Mengambil daftar pertanyaan instrumen berdasarkan target kuesioner.
     *
     * @return Collection<int, object>
     */
    private static function getQuestionsByTarget(string $target, ?int $prodiId = null): Collection
    {
        $query = KuesionerPertanyaan::query()
            ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
            ->join('kuesioner_section as s', 's.id', '=', 'sp.section_id')
            ->where('s.target', $target)
            ->where('kuesioner_pertanyaan.is_active', true)
            ->where('s.is_active', true)
            ->whereNotIn('kuesioner_pertanyaan.tipe', ['header', 'section_header']);

        if ($target === 'prodi' && $prodiId) {
            $query->where('s.prodi_id', $prodiId);
        }

        return $query->select([
            'kuesioner_pertanyaan.id',
            'kuesioner_pertanyaan.kode_pertanyaan',
            'kuesioner_pertanyaan.label_pertanyaan',
            'kuesioner_pertanyaan.teks_pertanyaan',
            'kuesioner_pertanyaan.tipe',
            's.judul as section_judul',
        ])
            ->orderBy('s.order', 'asc')
            ->orderBy('sp.order', 'asc')
            ->get();
    }

    /**
     * Memecahkan nilai pertanyaan kuesioner dengan fallback otomatis ke field profil alumni
     * untuk butir pertanyaan yang dikelola di modul profil (misal: BIO_WARGA_NEGARA, BIO_JK, BIO_TANGGAL_LAHIR).
     *
     * @param  object  $alumni  Data baris alumni dari view agregat
     * @param  object  $pertanyaan  Master butir pertanyaan kuesioner
     * @param  object|null  $jawaban  Jawaban kuesioner alumni jika ada
     */
    private static function resolveQuestionValue(object $alumni, object $pertanyaan, ?object $jawaban): string
    {
        // 1. Jika ada respons tersimpan di kuesioner_jawaban dan tidak kosong, gunakan nilai tersebut
        $formatted = self::formatAnswerValue($jawaban);
        if ($formatted !== '-') {
            return $formatted;
        }

        // 2. Fallback ke field profil alumni jika kode pertanyaan terdaftar di profil
        $kode = strtoupper(trim((string) ($pertanyaan->kode_pertanyaan ?? '')));

        return match ($kode) {
            'BIO_WARGA_NEGARA' => (string) ($alumni->warga_negara ?: 'WNI'),
            'BIO_JK' => $alumni->jenis_kelamin === 'L' ? 'Laki-Laki' : ($alumni->jenis_kelamin === 'P' ? 'Perempuan' : ($alumni->jenis_kelamin ?: '-')),
            'BIO_TANGGAL_LAHIR' => $alumni->tanggal_lahir ? date('d-m-Y', strtotime((string) $alumni->tanggal_lahir)) : '-',
            'BIO_TGL_LULUS' => $alumni->tanggal_lulus ? date('d-m-Y', strtotime((string) $alumni->tanggal_lulus)) : '-',
            'BIO_JUDUL_TA' => (string) ($alumni->judul_ta ?: '-'),
            'BIO_PROVINSI' => (string) ($alumni->nama_provinsi ?: '-'),
            'BIO_KABUPATEN' => (string) ($alumni->nama_kabupaten ?: '-'),
            'BIO_KECAMATAN' => (string) ($alumni->kecamatan ?: '-'),
            'BIO_KELURAHAN' => (string) ($alumni->kelurahan ?: '-'),
            'BIO_KODE_POS' => (string) ($alumni->kode_pos ?: '-'),
            'BIO_LINKEDIN_URL' => (string) ($alumni->linkedin_url ?: ($alumni->linkedin_username ?: '-')),
            'BIO_LINKEDIN_USER' => (string) ($alumni->linkedin_username ?: '-'),
            'BIO_INSTAGRAM_URL' => (string) ($alumni->instagram_url ?: '-'),
            'BIO_FACEBOOK_URL' => (string) ($alumni->facebook_url ?: '-'),
            'BIO_SKILLS' => (string) ($alumni->skills ?: '-'),
            'BIO_EXPERIENCE' => (string) ($alumni->experience ?: '-'),
            'BIO_BIDANG_DIVISI' => (string) ($alumni->divisi ?: ($alumni->bidang_pekerjaan ?: '-')),
            'BIO_INST_BENTUK' => (string) ($alumni->jenis_perusahaan ?: ($alumni->skala_perusahaan ?: '-')),
            'BIO_INST_LOKASI' => (string) ($alumni->jenis_lokasi_perusahaan ?: ($alumni->alamat_perusahaan ?: '-')),
            'BIO_INST_NEGARA' => (string) ($alumni->nama_negara_perusahaan ?: '-'),
            'BIO_PEND_TINGKAT' => (string) ($alumni->pendidikan_tingkat ?: '-'),
            'BIO_PEND_KAMPUS' => (string) ($alumni->perguruan_tinggi ?: '-'),
            'BIO_PEND_PRODI' => (string) ($alumni->pendidikan_prodi ?: '-'),
            'F1' => "NIM: {$alumni->nim} | Nama: {$alumni->nama}",
            'F505' => $alumni->gaji ? (string) $alumni->gaji : '-',
            'F2A', 'F2B', 'F2C', 'F2D' => (string) ($alumni->kategori_pekerjaan ?: '-'),
            'F5A1', 'F5A2', 'F5B', 'F5C', 'F5D' => (string) ($alumni->nama_perusahaan ?: '-'),
            'F11' => (string) ($alumni->posisi_jabatan ?: ($alumni->posisi_wiraswasta ?: '-')),
            default => '-',
        };
    }

    /**
     * Memformat objek KuesionerJawaban menjadi string jawaban yang ramah dibaca manusia.
     */
    private static function formatAnswerValue(?object $jawaban): string
    {
        if (! $jawaban) {
            return '-';
        }

        if (filled($jawaban->jawaban_teks ?? null)) {
            return (string) $jawaban->jawaban_teks;
        }

        if (! empty($jawaban->jawaban_json)) {
            $json = is_string($jawaban->jawaban_json) ? json_decode($jawaban->jawaban_json, true) : $jawaban->jawaban_json;
            if (is_array($json)) {
                // Tangani bentuk format jawaban JSON seperti catatan atau daftar pilihan
                if (isset($json['catatan'])) {
                    return (string) $json['catatan'];
                }

                return implode(', ', array_map('strval', $json));
            }
        }

        return '-';
    }

    /**
     * Helper sanitasi string XML untuk keamanan output berkas SpreadsheetML.
     */
    private static function xmlEscape(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
