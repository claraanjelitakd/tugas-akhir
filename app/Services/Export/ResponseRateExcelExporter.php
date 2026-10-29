<?php

namespace App\Services\Export;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Layanan ekspor Excel untuk Indikator 01: Response Rate Universitas.
 *
 * Menghasilkan dokumen Excel SpreadsheetML multi-sheet atau single-sheet:
 * 1. Sheet Ringkasan: Matriks capaian per program studi (Populasi, Sudah, Belum, %, Target)
 * 2. Sheet Sudah Mengisi: Daftar alumni responden lengkap dengan tanggal dan kontak WA/Email
 * 3. Sheet Belum Mengisi: Daftar alumni non-responden untuk tindak lanjut tim tracer
 */
class ResponseRateExcelExporter
{
    /**
     * Mengunduh file Excel (.xls SpreadsheetML) secara streaming berdasarkan filter aktif.
     *
     * @param  string  $year  Tahun kelulusan kohort
     * @param  int|null  $prodiId  ID program studi (opsional)
     * @param  string  $status  Filter status: 'all', 'sudah', atau 'belum'
     */
    public static function download(string $year, ?int $prodiId = null, string $status = 'all'): StreamedResponse
    {
        $statusKey = strtolower(trim($status));
        if (! in_array($statusKey, ['all', 'sudah', 'belum'], true)) {
            $statusKey = 'all';
        }

        $query = DB::table('v_analytics_response_rate')
            ->when($year !== 'ALL', fn ($q) => $q->where('tahun_lulus', $year))
            ->when($prodiId, fn ($q) => $q->where('prodi_id', $prodiId));

        if ($statusKey === 'sudah') {
            $query->where('answered', '>', 0);
        } elseif ($statusKey === 'belum') {
            $query->where('answered', 0);
        }

        $records = $query->orderBy('nama_prodi')->orderBy('nama')->get();

        $prodiName = 'Seluruh_Prodi';
        if ($prodiId) {
            $prodi = DB::table('prodi')->where('id', $prodiId)->first(['nama_prodi']);
            if ($prodi) {
                $prodiName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prodi->nama_prodi);
            }
        }

        $timestamp = now()->format('Ymd_His');
        $filename = "Tracer_ResponseRate_{$year}_{$prodiName}_{$statusKey}_{$timestamp}.xls";

        return response()->streamDownload(function () use ($year, $prodiId, $statusKey, $records) {
            echo self::generateSpreadsheetXml($year, $prodiId, $statusKey, $records);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Menyusun seluruh isi berkas Excel dalam format XML Spreadsheet 2003 (SpreadsheetML).
     *
     * @param  Collection<int, object>  $records
     */
    public static function generateSpreadsheetXml(string $year, ?int $prodiId, string $status, Collection $records): string
    {
        $exportTime = now()->format('d/m/Y H:i:s');
        $prodiLabel = 'Seluruh Program Studi';
        if ($prodiId) {
            $prodi = DB::table('prodi')->where('id', $prodiId)->first(['nama_prodi']);
            if ($prodi) {
                $prodiLabel = $prodi->nama_prodi;
            }
        }

        // Ambil data ringkasan jika status 'all'
        $summaryRows = collect();
        if ($status === 'all') {
            $summaryQuery = DB::table('v_analytics_response_rate')
                ->when($year !== 'ALL', fn ($q) => $q->where('tahun_lulus', $year))
                ->when($prodiId, fn ($q) => $q->where('prodi_id', $prodiId));

            $allRows = $summaryQuery->get();
            $summaryRows = $allRows->groupBy('nama_prodi')->map(function ($prodiList, $pName) {
                $pop = $prodiList->count();
                $sudah = $prodiList->where('answered', '>', 0)->count();
                $belum = max($pop - $sudah, 0);
                $pctSudah = $pop > 0 ? round(($sudah / $pop) * 100, 1) : 0;
                $pctBelum = $pop > 0 ? round(($belum / $pop) * 100, 1) : 0;

                return [
                    'nama_prodi' => $pName ?: 'Tanpa prodi',
                    'populasi' => $pop,
                    'sudah' => $sudah,
                    'belum' => $belum,
                    'pct_sudah' => $pctSudah,
                    'pct_belum' => $pctBelum,
                ];
            })->sortByDesc('pct_sudah')->values();
        }

        $sudahList = $records->where('answered', '>', 0)->values();
        $belumList = $records->where('answered', 0)->values();

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
  <Created><?= now()->toIso8601String() ?></Created>
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
   <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#0D542B"/>
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
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0D542B" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TableBody">
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
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#166534"/>
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
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#9A3412"/>
   <Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TableTotal">
   <Alignment ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#0D542B"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#0D542B"/>
   </Borders>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0D542B"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
  </Style>
 </Styles>

<?php if ($status === 'all') { ?>
 <!-- WORKSHEET 1: RINGKASAN CAPAIAN -->
 <Worksheet ss:Name="Ringkasan">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="35"/>
   <Column ss:Width="200"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   <Column ss:Width="95"/>
   <Column ss:Width="95"/>
   <Column ss:Width="130"/>

   <Row ss:Height="24">
    <Cell ss:MergeAcross="7" ss:StyleID="Title"><Data ss:Type="String">LAPORAN RINGKASAN RESPONSE RATE UNIVERSITAS</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="7" ss:StyleID="SubTitle"><Data ss:Type="String">Tracer Study Universitas Kristen Duta Wacana (UKDW)</Data></Cell>
   </Row>
   <Row><Cell/></Row>

   <!-- Metadata -->
   <Row>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Tahun Kelulusan</Data></Cell>
    <Cell ss:StyleID="MetaValue"><Data ss:Type="String"><?= htmlspecialchars($year, ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Cakupan Program Studi</Data></Cell>
    <Cell ss:MergeAcross="4" ss:StyleID="MetaValue"><Data ss:Type="String"><?= htmlspecialchars($prodiLabel, ENT_XML1) ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Waktu Unduh</Data></Cell>
    <Cell ss:StyleID="MetaValue"><Data ss:Type="String"><?= $exportTime ?> WIB</Data></Cell>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Target Minimum</Data></Cell>
    <Cell ss:MergeAcross="4" ss:StyleID="MetaValue"><Data ss:Type="String">60,0% (Standar Kemendikbudristek / IKU 1)</Data></Cell>
   </Row>
   <Row><Cell/></Row>

   <!-- Header Tabel -->
   <Row ss:Height="26">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Program Studi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Populasi Kohort</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Sudah Mengisi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Belum Mengisi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">% Sudah</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">% Belum</Data></Cell>
   </Row>

   <!-- Rows Data -->
   <?php
   $totalPop = 0;
    $totalSudah = 0;
    $totalBelum = 0;
    foreach ($summaryRows as $idx => $sRow) {
        $totalPop += $sRow['populasi'];
        $totalSudah += $sRow['sudah'];
        $totalBelum += $sRow['belum'];
        ?>
   <Row>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $idx + 1 ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= htmlspecialchars($sRow['nama_prodi'], ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="Number"><?= $sRow['populasi'] ?></Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="Number"><?= $sRow['sudah'] ?></Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="Number"><?= $sRow['belum'] ?></Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="String"><?= number_format($sRow['pct_sudah'], 1, ',', '.') ?>%</Data></Cell>
    <Cell ss:StyleID="TableBodyRight"><Data ss:Type="String"><?= number_format($sRow['pct_belum'], 1, ',', '.') ?>%</Data></Cell>
   </Row>
   <?php } ?>

   <!-- Total Row -->
   <?php
        $totalPctSudah = $totalPop > 0 ? round(($totalSudah / $totalPop) * 100, 1) : 0;
    $totalPctBelum = $totalPop > 0 ? round(($totalBelum / $totalPop) * 100, 1) : 0;
    ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="TableTotal"><Data ss:Type="String"></Data></Cell>
    <Cell ss:StyleID="TableTotal"><Data ss:Type="String">TOTAL UNIVERSITAS</Data></Cell>
    <Cell ss:StyleID="TableTotal"><Data ss:Type="Number"><?= $totalPop ?></Data></Cell>
    <Cell ss:StyleID="TableTotal"><Data ss:Type="Number"><?= $totalSudah ?></Data></Cell>
    <Cell ss:StyleID="TableTotal"><Data ss:Type="Number"><?= $totalBelum ?></Data></Cell>
    <Cell ss:StyleID="TableTotal"><Data ss:Type="String"><?= number_format($totalPctSudah, 1, ',', '.') ?>%</Data></Cell>
    <Cell ss:StyleID="TableTotal"><Data ss:Type="String"><?= number_format($totalPctBelum, 1, ',', '.') ?>%</Data></Cell>
   </Row>
  </Table>
 </Worksheet>
<?php } ?>

<?php if ($status === 'all' || $status === 'sudah') { ?>
 <!-- WORKSHEET: SUDAH MENGISI -->
 <Worksheet ss:Name="Sudah Mengisi">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="35"/>
   <Column ss:Width="85"/>
   <Column ss:Width="200"/>
   <Column ss:Width="160"/>
   <Column ss:Width="75"/>
   <Column ss:Width="110"/>
   <Column ss:Width="120"/>
   <Column ss:Width="110"/>
   <Column ss:Width="190"/>

   <Row ss:Height="24">
    <Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">DAFTAR ALUMNI SUDAH MENGISI KUESIONER</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="8" ss:StyleID="SubTitle"><Data ss:Type="String">Status: Responden Aktif (Tahun Kelulusan <?= htmlspecialchars($year, ENT_XML1) ?> - <?= htmlspecialchars($prodiLabel, ENT_XML1) ?>)</Data></Cell>
   </Row>
   <Row><Cell/></Row>

   <!-- Header -->
   <Row ss:Height="26">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">NIM</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Alumni</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Program Studi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Tahun Lulus</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Status Kuesioner</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Tgl Respons Terakhir</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No. Telepon / WhatsApp</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Email</Data></Cell>
   </Row>

   <?php foreach ($sudahList as $idx => $item) { ?>
   <Row>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $idx + 1 ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= htmlspecialchars((string) $item->nim, ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->nama ?? '-'), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->nama_prodi ?? '-'), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->tahun_lulus ?? $year), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="BadgeSudah"><Data ss:Type="String">Sudah Mengisi</Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->last_response_at ? date('d/m/Y H:i', strtotime($item->last_response_at)) : 'Tercatat'), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->nomor_telepon ?? '-'), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->email ?? '-'), ENT_XML1) ?></Data></Cell>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
<?php } ?>

<?php if ($status === 'all' || $status === 'belum') { ?>
 <!-- WORKSHEET: BELUM MENGISI -->
 <Worksheet ss:Name="Belum Mengisi">
  <Table ss:DefaultRowHeight="20">
   <Column ss:Width="35"/>
   <Column ss:Width="85"/>
   <Column ss:Width="200"/>
   <Column ss:Width="160"/>
   <Column ss:Width="75"/>
   <Column ss:Width="110"/>
   <Column ss:Width="120"/>
   <Column ss:Width="110"/>
   <Column ss:Width="190"/>

   <Row ss:Height="24">
    <Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">DAFTAR ALUMNI BELUM MENGISI KUESIONER</Data></Cell>
   </Row>
   <Row>
    <Cell ss:MergeAcross="8" ss:StyleID="SubTitle"><Data ss:Type="String">Target Tindak Lanjut / Follow-up (Tahun Kelulusan <?= htmlspecialchars($year, ENT_XML1) ?> - <?= htmlspecialchars($prodiLabel, ENT_XML1) ?>)</Data></Cell>
   </Row>
   <Row><Cell/></Row>

   <!-- Header -->
   <Row ss:Height="26">
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">NIM</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Nama Alumni</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Program Studi</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Tahun Lulus</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Status Kuesioner</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Keterangan</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">No. Telepon / WhatsApp</Data></Cell>
    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Email</Data></Cell>
   </Row>

   <?php foreach ($belumList as $idx => $item) { ?>
   <Row>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="Number"><?= $idx + 1 ?></Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= htmlspecialchars((string) $item->nim, ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->nama ?? '-'), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->nama_prodi ?? '-'), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->tahun_lulus ?? $year), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="BadgeBelum"><Data ss:Type="String">Belum Mengisi</Data></Cell>
    <Cell ss:StyleID="TableBodyCenter"><Data ss:Type="String">Belum Memulai Respons</Data></Cell>
    <Cell ss:StyleID="TextFormat"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->nomor_telepon ?? '-'), ENT_XML1) ?></Data></Cell>
    <Cell ss:StyleID="TableBody"><Data ss:Type="String"><?= htmlspecialchars((string) ($item->email ?? '-'), ENT_XML1) ?></Data></Cell>
   </Row>
   <?php } ?>
  </Table>
 </Worksheet>
<?php } ?>

</Workbook>
        <?php
         return ob_get_clean();
    }
}
