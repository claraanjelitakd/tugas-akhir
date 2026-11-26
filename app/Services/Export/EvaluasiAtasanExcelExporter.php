<?php

namespace App\Services\Export;

use App\Models\Biodata;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * EvaluasiAtasanExcelExporter
 *
 * Layanan ekspor berkas Excel (.xls SpreadsheetML) untuk rekapitulasi data Evaluasi Atasan Pengguna Lulusan.
 * Menghasilkan tabel dengan kolom:
 * - Identitas Alumni: No, NIM, Nama Alumni, Program Studi, Tahun Lulus
 * - Data Perusahaan: Nama Perusahaan, Jenis / Sektor Perusahaan, Alamat Perusahaan
 * - Data Atasan: Nama Atasan, Email Atasan, Telepon / WhatsApp Atasan
 * - Status Evaluasi: Status Pengisian, Waktu Pengisian
 * - Hasil Evaluasi: Kolom dinamis untuk setiap butir pertanyaan instrumen evaluasi atasan
 *
 * Data per evaluasi disusun per baris (row).
 */
class EvaluasiAtasanExcelExporter
{
    /**
     * Mengunduh berkas Excel rekap evaluasi atasan dalam format streaming.
     */
    public static function download(?User $user = null, ?Request $request = null): StreamedResponse
    {
        $timestamp = now()->format('Ymd_His');
        $filename = "Rekap_Evaluasi_Atasan_{$timestamp}.xls";

        return response()->streamDownload(function () use ($user, $request) {
            echo self::generateSpreadsheetXml($user, $request);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Menghasilkan dokumen SpreadsheetML XML berkas Excel evaluasi atasan.
     */
    public static function generateSpreadsheetXml(?User $user = null, ?Request $request = null): string
    {
        // 1. Ambil pertanyaan evaluasi atasan aktif sesuai urutan bagian dan urutan soal
        $atasanSectionIds = KuesionerSection::where('target', 'atasan')->pluck('id')->all();
        $questions = KuesionerSectionPertanyaan::with('pertanyaan')
            ->whereIn('section_id', $atasanSectionIds)
            ->where('is_active', true)
            ->whereHas('pertanyaan', fn ($q) => $q->where('is_active', true))
            ->orderBy('order', 'asc')
            ->get()
            ->pluck('pertanyaan')
            ->filter()
            ->unique('id')
            ->values();

        // 2. Ambil data relasi profil alumni-atasan dengan scope peran pengguna & filter
        $query = Biodata::query()->whereNotNull('atasan_id');

        if ($user) {
            if ($user->role === 'admin_prodi' && $user->kode_prodi) {
                $query->where('kode_prodi', $user->kode_prodi);
            } elseif ($user->role === 'admin_fakultas' && $user->kode_fakultas) {
                $query->whereHas('prodi', fn (Builder $q): Builder => $q->where('kode_fakultas', $user->kode_fakultas));
            }
        }

        if ($request) {
            $search = trim((string) $request->input('search', ''));
            $companySearch = trim((string) $request->input('company', ''));
            $provinsiDomisili = trim((string) $request->input('provinsi', ''));
            $selectedProdiId = $request->input('kode_prodi');

            if ($user?->role !== 'admin_prodi' && filled($selectedProdiId) && $selectedProdiId !== 'all') {
                $query->where('kode_prodi', $selectedProdiId);
            }

            if ($companySearch !== '') {
                $query->where(function (Builder $q) use ($companySearch): void {
                    $q->whereHas('perusahaan', fn (Builder $cq) => $cq->where('nama_perusahaan', 'like', "%{$companySearch}%"))
                        ->orWhereHas('atasan.perusahaan', fn (Builder $cq) => $cq->where('nama_perusahaan', 'like', "%{$companySearch}%"));
                });
            }

            if ($provinsiDomisili === 'LUAR_NEGERI' || $provinsiDomisili === 'Luar Negeri') {
                $query->where(function (Builder $q): void {
                    $q->whereHas('perusahaan', function (Builder $cq): void {
                        $cq->where('jenis_lokasi', 'Luar Negeri')
                            ->orWhere(function (Builder $sq): void {
                                $sq->whereNotNull('kode_negara')->where('kode_negara', '!=', 'ID');
                            });
                    })->orWhereHas('atasan.perusahaan', function (Builder $cq): void {
                        $cq->where('jenis_lokasi', 'Luar Negeri')
                            ->orWhere(function (Builder $sq): void {
                                $sq->whereNotNull('kode_negara')->where('kode_negara', '!=', 'ID');
                            });
                    });
                });
            } elseif ($provinsiDomisili !== '' && $provinsiDomisili !== 'all') {
                $query->where('kode_provinsi', $provinsiDomisili);
            }

            if ($search !== '') {
                $query->where(function (Builder $sq) use ($search): void {
                    $sq->where('nim', 'like', "%{$search}%")
                        ->orWhereHas('dataAkademik', fn (Builder $aq): Builder => $aq->where('nama', 'like', "%{$search}%"))
                        ->orWhereHas('atasan', function (Builder $atq) use ($search): void {
                            $atq->where('nama', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('telepon', 'like', "%{$search}%");
                        })
                        ->orWhereHas('atasan.perusahaan', fn (Builder $cq) => $cq->where('nama_perusahaan', 'like', "%{$search}%"))
                        ->orWhereHas('perusahaan', fn (Builder $cq) => $cq->where('nama_perusahaan', 'like', "%{$search}%"));
                });
            }
        }

        $records = $query->with([
            'user',
            'dataAkademik',
            'prodi',
            'atasan',
            'atasan.perusahaan.propinsi',
            'atasan.perusahaan.kabupaten',
            'perusahaan.propinsi',
            'perusahaan.kabupaten',
            'evaluasiAtasan.respons.pertanyaan',
            'evaluasiAtasan.respons.opsi',
        ])
            ->latest('updated_at')
            ->get();

        $exportTime = now()->translatedFormat('d F Y, H:i').' WIB';

        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="Title">
   <Font ss:FontName="Calibri" ss:Size="16" ss:Color="#0D542B" ss:Bold="1"/>
   <Alignment ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="SubTitle">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#475569" ss:Italic="1"/>
   <Alignment ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="TableHeader">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0D542B"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0D542B"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0D542B"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0D542B"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#0D542B" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TableHeaderSub">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#0F172A" ss:Bold="1"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TableBody">
   <Alignment ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1E293B"/>
  </Style>
  <Style ss:ID="TableBodyCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1E293B"/>
  </Style>
  <Style ss:ID="TextFormat">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1E293B"/>
   <NumberFormat ss:Format="@"/>
  </Style>
  <Style ss:ID="BadgeSudah">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#166534" ss:Bold="1"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="BadgeBelum">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#9A3412" ss:Bold="1"/>
   <Interior ss:Color="#FFEDD5" ss:Pattern="Solid"/>
  </Style>
 </Styles>

 <Worksheet ss:Name="Rekap Evaluasi Atasan">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="40"/>
   <Column ss:Width="95"/>
   <Column ss:Width="180"/>
   <Column ss:Width="160"/>
   <Column ss:Width="80"/>
   <Column ss:Width="180"/>
   <Column ss:Width="140"/>
   <Column ss:Width="200"/>
   <Column ss:Width="160"/>
   <Column ss:Width="160"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
   <Column ss:Width="130"/>
   <?php foreach ($questions as $q) { ?>
   <Column ss:Width="180"/>
   <?php } ?>

   <!-- Baris Judul Laporan -->
   <Row ss:Height="26">
    <Cell ss:MergeAcross="<?= 12 + count($questions) ?>" ss:StyleID="Title"><Data ss:Type="String">REKAPITULASI HASIL EVALUASI PENGGUNA LULUSAN (ATASAN ALUMNI)</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="<?= 12 + count($questions) ?>" ss:StyleID="SubTitle"><Data ss:Type="String">Universitas Kristen Duta Wacana (UKDW) | Waktu Ekspor: <?= self::xmlEscape($exportTime) ?> | Total Data: <?= count($records) ?> Responden</Data></Cell>
   </Row>
   <Row ss:Height="12"/>

   <!-- Baris Header Kolom / Field -->
   <Row ss:Height="36">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">NIM Alumni</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Alumni</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Program Studi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Tahun Lulus</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Perusahaan / Instansi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Sektor / Bidang Perusahaan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Alamat Perusahaan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Atasan Penilai</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Email Atasan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No HP / WhatsApp Atasan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Status Evaluasi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Waktu Pengisian</Data></Cell>
    <?php foreach ($questions as $q) {
        $kode = trim((string) $q->kode_pertanyaan);
        $cleanKode = str_starts_with($kode, 'EVA_') ? substr($kode, 4) : $kode;
        $label = trim((string) ($q->label_pertanyaan ?: $q->teks_pertanyaan));
        $headerText = "[{$cleanKode}] {$label}";
        ?>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String"><?= self::xmlEscape($headerText) ?></Data></Cell>
    <?php } ?>
   </Row>

   <!-- Baris Isi Data (Row per Alumni / Atasan) -->
   <?php
   $no = 1;
        foreach ($records as $biodata) {
            $nim = $biodata->nim ?: '-';
            $namaAlumni = $biodata->user?->name ?: ($biodata->dataAkademik?->nama ?: ($biodata->nama ?: '-'));
            $prodi = $biodata->prodi?->nama_prodi ?: '-';
            $tahunLulus = $biodata->dataAkademik?->tahun_lulus ?: ($biodata->tahun_lulus ?: '-');

            $company = $biodata->atasan?->perusahaan ?? $biodata->perusahaan;
            $perusahaanNama = $company?->nama_perusahaan ?: '-';
            $perusahaanSektor = $company?->jenis_perusahaan ?: ($company?->bentuk_perusahaan ?: '-');
            $perusahaanAlamat = $company?->alamat ?: '-';

            $atasan = $biodata->atasan;
            $atasanNama = $atasan?->nama ?: '-';
            $atasanEmail = $atasan?->email ?: '-';
            $atasanTelp = $atasan?->telepon ?: '-';

            $ev = $biodata->evaluasiAtasan;
            $isSubmitted = (bool) ($ev?->is_submitted);
            $statusLabel = $isSubmitted ? 'Sudah Mengisi' : ($ev ? 'Menunggu Respon' : 'Belum Ada Undangan');
            $badgeStyle = $isSubmitted ? 'BadgeSudah' : 'BadgeBelum';
            $waktuIsi = $ev?->submitted_at ? $ev->submitted_at->format('d/m/Y H:i') : '-';

            // Peta jawaban butir pertanyaan evaluasi
            $answerMap = [];
            if ($ev && $ev->respons) {
                foreach ($ev->respons as $resp) {
                    $answerMap[$resp->pertanyaan_id] = $resp;
                }
            }
            ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $no++ ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($nim) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($namaAlumni) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($prodi) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape((string) $tahunLulus) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($perusahaanNama) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($perusahaanSektor) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($perusahaanAlamat) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($atasanNama) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($atasanEmail) ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= self::xmlEscape($atasanTelp) ?></Data></Cell>
    <Cell ss:StyleID="<?= $badgeStyle ?>"><Data ss:Type="String"><?= $statusLabel ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= self::xmlEscape($waktuIsi) ?></Data></Cell>
    <?php foreach ($questions as $q) {
        $r = $answerMap[$q->id] ?? null;
        $val = self::formatAnswerValue($r);
        ?>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= self::xmlEscape($val) ?></Data></Cell>
    <?php } ?>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
</Workbook>
<?php
        return ob_get_clean();
    }

    /**
     * Memformat teks jawaban dari model KuesionerJawaban evaluasi atasan.
     */
    private static function formatAnswerValue(?object $resp): string
    {
        if (! $resp) {
            return '-';
        }

        if (filled($resp->jawaban_teks)) {
            $score = $resp->opsi?->nilai_skor;
            if ($score !== null) {
                return "{$resp->jawaban_teks} (Skor: {$score})";
            }

            return (string) $resp->jawaban_teks;
        }

        if ($resp->opsi && filled($resp->opsi->label)) {
            return (string) $resp->opsi->label;
        }

        if (is_array($resp->jawaban_json)) {
            return implode(', ', array_filter(array_map('strval', $resp->jawaban_json)));
        }

        return '-';
    }

    /**
     * Escape karakter khusus XML untuk SpreadsheetML.
     */
    private static function xmlEscape(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
