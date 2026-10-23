<?php

namespace App\Services\Export;

use App\Models\AlumniAuditRekap;
use App\Models\AlumniProfileSummary;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\Kuesioner;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\ProdiQuestionSection;
use App\Models\ProdiResponse;
use App\Models\Tracer;
use App\Services\Kuesioner\KelengkapanTracerService;
use App\Services\Kuesioner\KuesionerSyncService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AlumniTracerExcelExporter
 *
 * Fungsi: Menghasilkan berkas Excel (.xls) berformat HTML kaya gaya (UKDW Green Branding,
 * Identitas Alumni Lengkap, Kuesioner Universitas, Kuesioner Prodi, dan Sheet Hasil Evaluasi Atasan)
 * dengan proteksi format teks (mso-number-format:'\@') agar data numerik panjang seperti NIM,
 * No BPJS, NISN, dan No Telepon tidak terkonversi menjadi notasi eksponensial.
 */
class AlumniTracerExcelExporter
{
    /**
     * Download Berkas Excel (.xls) Berformat Lengkap (Single Alumni Stream)
     *
     * @param  int|string|Biodata  $id  ID Biodata Alumni atau instance Biodata
     */
    public static function download(int|string|Biodata $id): StreamedResponse
    {
        $alumni = $id instanceof Biodata ? $id : Biodata::findOrFail($id);
        $nim = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $alumni->nim);
        $nama = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($alumni->nama ?? 'Alumni'));
        $filename = "Tracer_Study_{$nim}_{$nama}.xls";

        return response()->streamDownload(function () use ($alumni) {
            echo self::generateExcelContent($alumni);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Menghasilkan konten string lengkap berkas Excel (.xls) untuk satu alumni
     * Termasuk Sheet Kuesioner Tracer Study & Sheet Hasil Evaluasi Atasan
     */
    public static function generateExcelContent(int|string|Biodata $alumniOrId): string
    {
        if ($alumniOrId instanceof Biodata) {
            $alumni = $alumniOrId;
            if (! $alumni->relationLoaded('dataAkademik') || ! $alumni->relationLoaded('atasan')) {
                $alumni->load([
                    'dataAkademik.yudisium',
                    'yudisium',
                    'propinsi',
                    'kabupaten',
                    'perusahaan.propinsi',
                    'perusahaan.kabupaten',
                    'atasan',
                    'user',
                    'prodi.fakultas',
                ]);
            }
        } else {
            $alumni = Biodata::with([
                'dataAkademik.yudisium',
                'yudisium',
                'propinsi',
                'kabupaten',
                'perusahaan.propinsi',
                'perusahaan.kabupaten',
                'atasan',
                'user',
                'prodi.fakultas',
            ])->findOrFail($alumniOrId);
        }

        KuesionerSyncService::syncProfileResponses($alumni);

        // Ambil status audit rekapitulasi kelengkapan tracer study dari Database View
        $auditRekap = $alumni->auditRekap ?? AlumniAuditRekap::where('biodata_id', $alumni->id)->first();
        $summary = $auditRekap ?? ($alumni->profileSummary ?? AlumniProfileSummary::where('biodata_id', $alumni->id)->first());

        // Ambil seluruh jawaban kuesioner umum
        $savedResponses = Tracer::where('biodata_id', $alumni->id)
            ->get()
            ->keyBy('question_id');

        $kuesioner = Kuesioner::where('is_active', true)
            ->with(['sections' => function ($secQuery) {
                $secQuery->orderBy('order', 'asc')
                    ->with(['subpertanyaans' => function ($qQuery) {
                        $qQuery->orderBy('order', 'asc')
                            ->with('detils');
                    }]);
            }])
            ->first();

        // Kuesioner Prodi
        $savedProdiResponses = ProdiResponse::where('biodata_id', $alumni->id)
            ->get()
            ->keyBy('prodi_question_id');

        $prodiSections = $alumni->prodi_id
            ? ProdiQuestionSection::where('prodi_id', $alumni->prodi_id)
                ->with(['questions' => function ($qQuery) {
                    $qQuery->orderBy('order', 'asc')->with('options');
                }])
                ->orderBy('order', 'asc')
                ->get()
            : collect([]);

        // Evaluasi Atasan
        $evaluasiAtasan = EvaluasiAtasan::where('biodata_id', $alumni->id)
            ->with(['atasan', 'perusahaan', 'respons.pertanyaan'])
            ->latest('updated_at')
            ->first();

        $pertanyaansAtasan = PertanyaanEvaluasiAtasan::where('is_active', true)
            ->orderBy('order', 'asc')
            ->get();

        $responAtasanMap = $evaluasiAtasan ? $evaluasiAtasan->respons->keyBy('pertanyaan_id') : collect([]);

        // Mulai penangkapan output buffer
        ob_start();

        $dataAkademik = $alumni->dataAkademik;
        $yudisium = $alumni->yudisium ?? $dataAkademik?->yudisium;
        $perusahaan = $alumni->perusahaan;
        $atasan = $alumni->atasan;

        // 1. Data Pribadi & Kontak (Diutamakan dari view v_alumni_profile_summary)
        $namaLengkap = $summary?->nama ?: ($alumni->nama ?: ($dataAkademik?->nama ?: ($alumni->user?->name ?: '-')));
        $noBpjs = $summary?->no_bpjs ?: ($alumni->no_bpjs ?: ($dataAkademik?->no_bpjs ?: '-'));
        $nisn = $summary?->nisn ?: ($alumni->nisn ?: ($dataAkademik?->nisn ?: '-'));
        $jenisKelamin = $summary?->jenis_kelamin ?: ($dataAkademik?->jenis_kelamin ?: '-');
        $tempatLahir = $summary?->tempat_lahir ?: ($dataAkademik?->tempat_lahir ?: '-');
        $tglLahirVal = $summary?->tanggal_lahir ?: $dataAkademik?->tanggal_lahir;
        $tanggalLahir = $tglLahirVal ? date('d-m-Y', strtotime($tglLahirVal)) : '-';
        $agama = $summary?->agama ?: ($dataAkademik?->agama ?: '-');
        $golDarah = $summary?->golongan_darah ?: ($dataAkademik?->golongan_darah ?: '-');
        $wargaNegara = $summary?->warga_negara ?: ($dataAkademik?->warga_negara ?: 'WNI');
        $telepon = $summary?->nomor_telepon ?: ($alumni->nomor_telepon ?: '-');
        $emailPribadi = $summary?->email_pribadi ?: ($alumni->email_pribadi ?: '-');
        $emailKampus = $summary?->email_students ?: ($dataAkademik?->email_students ?: '-');
        $linkedin = $summary?->linkedin_url ?: ($summary?->linkedin_username ?: ($alumni->linkedin_url ?: ($alumni->linkedin_username ?: '-')));
        $ig = $summary?->instagram_url ?: $alumni->instagram_url;
        $fb = $summary?->facebook_url ?: $alumni->facebook_url;
        $medsos = trim(($ig ? 'IG: '.$ig : '').($fb ? ' | FB: '.$fb : '')) ?: '-';
        $skills = $summary?->skills ?: ($alumni->skills ?: '-');
        $experience = $summary?->experience ?: ($alumni->experience ?: '-');
        $alamatDomisiliLengkap = $summary?->alamat ?: ($alumni->alamat_saat_ini ?: '-');

        // 2. Data Akademik
        $prodiNama = $summary?->nama_prodi ?: ($alumni->prodi?->nama_prodi ?: ($dataAkademik?->prodi?->nama_prodi ?: '-'));
        $fakultasNama = $summary?->nama_fakultas ?: ($alumni->prodi?->fakultas?->nama_fakultas ?: '-');
        $angkatanMasuk = $summary?->angkatan_masuk ?: ($dataAkademik?->angkatan_masuk ?: '-');
        $statusMhsVal = $summary?->status_mahasiswa ?: $dataAkademik?->status_mahasiswa;
        $statusMahasiswa = $statusMhsVal === 'L' ? 'Lulus' : ($statusMhsVal ?: '-');
        $tahunLulus = $summary?->tahun_lulus
            ?: ($alumni->tahun_lulus
            ?: ($yudisium?->tahun_lulus
            ?: ($dataAkademik?->tahun_lulus
            ?: ($yudisium?->tahun_akademik_lulus
            ?: ($dataAkademik?->tahun_akademik_lulus ?: '-')))));
        $semesterLulus = $summary?->tahun_akademik_lulus ?: ($yudisium?->tahun_akademik_lulus ?: ($dataAkademik?->tahun_akademik_lulus ?: '-'));
        $ipk = $summary?->ipk ?: ($dataAkademik?->ip_kumulatif ?: '-');
        $totalSks = $summary?->total_sks ?: ($dataAkademik?->total_sks ?: '-');
        $totalAngkaKualitas = $summary?->total_angka_kualitas ?: ($dataAkademik?->total_angka_kualitas ?: '-');
        $asalSekolah = $summary?->asal_sekolah ?: ($dataAkademik?->asal_sekolah ?: '-');
        $jurusanSekolah = $summary?->jurusan_asal_sekolah ?: ($dataAkademik?->jurusan_asal_sekolah ?: '-');
        $alamatSekolah = $summary?->alamat_asal_sekolah ?: ($dataAkademik?->alamat_asal_sekolah ?: '-');
        $kotaSekolah = $summary?->kota_kabupaten_asal_sekolah ?: $dataAkademik?->kota_kabupaten_asal_sekolah;
        $provSekolah = $summary?->provinsi_asal_sekolah ?: $dataAkademik?->provinsi_asal_sekolah;
        $wilayahSekolah = trim(($kotaSekolah ? $kotaSekolah : '').($provSekolah ? ', '.$provSekolah : '')) ?: '-';

        // 3. Data Karier, Perusahaan & Atasan
        $kategoriPekerjaan = $summary?->kategori_pekerjaan ?: ($alumni->kategori_pekerjaan ?: '-');
        $posisiJabatan = $summary?->posisi_jabatan ?: ($alumni->posisi_jabatan ?: ($alumni->posisi_wiraswasta ?: '-'));
        $posisiWiraswasta = $summary?->posisi_wiraswasta ?: ($alumni->posisi_wiraswasta ?: '-');
        $gajiVal = $summary?->gaji ?: $alumni->gaji;
        $gaji = $gajiVal ? 'Rp '.number_format((float) $gajiVal, 0, ',', '.') : '-';
        $jenisPekerjaan = $summary?->jenis_pekerjaan ?: ($alumni->jenis_pekerjaan ?: ($perusahaan?->jenis_perusahaan ?: ($alumni->kategori_pekerjaan ?: '-')));
        $namaPerusahaan = $summary?->nama_perusahaan ?: ($perusahaan?->nama_perusahaan ?: '-');
        $sektorPerusahaan = $summary?->sektor_perusahaan ?: ($perusahaan?->sektor ?: '-');
        $skalaPerusahaan = $summary?->skala_perusahaan ?: ($perusahaan?->skala ?: '-');
        $jenisPerusahaan = $summary?->jenis_perusahaan ?: ($perusahaan?->jenis_perusahaan ?: ($alumni->jenis_pekerjaan ?: '-'));
        $lokasiPerusahaan = $summary?->jenis_lokasi_perusahaan ?: ($perusahaan?->jenis_lokasi ?: '-');
        $negaraPerusahaan = $summary?->negara_perusahaan ?: ($perusahaan?->negara ?: 'Indonesia');
        $alamatPerusahaan = $summary?->alamat_perusahaan ?: ($perusahaan?->alamat ?: '-');
        $kodePosPerusahaan = $summary?->kode_pos ?: ($perusahaan?->kode_pos ?: ($alumni->zipcode ?: '-'));
        $namaAtasan = $summary?->nama_atasan ?: ($atasan?->nama ?: '-');
        $teleponAtasan = $summary?->telepon_atasan ?: ($atasan?->telepon ?: '-');
        $emailAtasan = $summary?->email_atasan ?: ($atasan?->email ?: '-');
        $tingkatPendidikanLanjut = $summary?->pendidikan_tingkat ?: ($alumni->pendidikan_tingkat ?: '-');
        $kampusLanjut = $summary?->perguruan_tinggi ?: ($alumni->perguruan_tinggi ?: '-');
        $prodiLanjut = $summary?->pendidikan_prodi ?: ($alumni->pendidikan_prodi ?: '-');

        // 5. Data Evaluasi Atasan
        $atasanEvaluatorNama = $evaluasiAtasan?->atasan?->nama ?: ($atasan?->nama ?: '-');
        $atasanEvaluatorEmail = $evaluasiAtasan?->atasan?->email ?: ($atasan?->email ?: '-');
        $atasanEvaluatorTelepon = $evaluasiAtasan?->atasan?->telepon ?: ($atasan?->telepon ?: '-');
        $perusahaanEvaluatorNama = $evaluasiAtasan?->perusahaan?->nama_perusahaan ?: ($perusahaan?->nama_perusahaan ?: '-');
        $isSubmittedAtasan = (bool) ($evaluasiAtasan?->is_submitted);
        $submittedAtAtasan = $evaluasiAtasan?->submitted_at ? $evaluasiAtasan->submitted_at->format('d F Y, H:i').' WIB' : '-';
        $statusAtasanLabel = $isSubmittedAtasan ? "SUDAH DIISI ({$submittedAtAtasan})" : ($evaluasiAtasan ? 'BELUM DIISI (MENUNGGU RESPON ATASAN)' : 'BELUM ADA DATA ATASAN');
        $jumlahAlumniUkdw = $evaluasiAtasan?->jumlah_alumni_ukdw ?: '-';
        $standarGajiPertama = $evaluasiAtasan?->standar_gaji_pertama ?: '-';

        $waktuUnduh = date('d F Y, H:i').' WIB';

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">'."\n";
        echo "<head>\n";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'."\n";
        echo "<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Tracer Study Alumni</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet><x:ExcelWorksheet><x:Name>Hasil Evaluasi Atasan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->\n";
        echo "<style>\n";
        echo "  body { font-family: Calibri, 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #1e293b; }\n";
        echo "  table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }\n";
        echo "  th, td { border: 1px solid #cbd5e1; padding: 6px 10px; vertical-align: middle; mso-number-format: '\@'; }\n";
        echo "  .header-main { font-size: 15pt; font-weight: bold; color: #0D542B; padding: 10px 0 4px 0; }\n";
        echo "  .header-sub { font-size: 9.5pt; color: #64748b; padding-bottom: 12px; }\n";
        echo "  .box-identitas-header { background-color: #0D542B; color: #ffffff; font-weight: bold; text-align: center; font-size: 11pt; padding: 8px; }\n";
        echo "  .box-identitas-subheader { background-color: #FDC700; color: #000000; font-weight: bold; text-align: left; font-size: 10.5pt; padding: 6px 10px; }\n";
        echo "  .box-identitas-label { background-color: #f8fafc; font-weight: bold; color: #334155; width: 18%; }\n";
        echo "  .box-identitas-val { background-color: #ffffff; width: 32%; mso-number-format: '\@'; }\n";
        echo "  .table-header { background-color: #f1f5f9; font-weight: bold; text-align: center; color: #1e293b; font-size: 10pt; }\n";
        echo "  .section-row-univ { background-color: #fef08a; font-weight: bold; color: #713f12; padding: 8px 10px; font-size: 10.5pt; }\n";
        echo "  .section-row-prodi { background-color: #fed7aa; font-weight: bold; color: #7c2d12; padding: 8px 10px; font-size: 10.5pt; }\n";
        echo "  .text-center { text-align: center; }\n";
        echo "  .text-left { text-align: left; }\n";
        echo "  .text-right { text-align: right; }\n";
        echo "  .text-code { font-family: Consolas, 'Courier New', monospace; font-weight: bold; text-align: center; mso-number-format: '\@'; }\n";
        echo "  .status-v { color: #15803d; font-weight: bold; text-align: center; font-size: 12pt; background-color: #f0fdf4; mso-number-format: '\@'; }\n";
        echo "  .status-x { color: #b91c1c; font-weight: bold; text-align: center; font-size: 12pt; background-color: #fef2f2; mso-number-format: '\@'; }\n";
        echo "  .status-header { color: #64748b; font-style: italic; text-align: center; background-color: #fef9c3; mso-number-format: '\@'; }\n";
        echo "  .badge-wajib { color: #b91c1c; font-weight: bold; text-align: center; }\n";
        echo "  .badge-opsional { color: #475569; text-align: center; }\n";
        echo "  .bg-alt { background-color: #fafafa; }\n";
        echo "</style>\n";
        echo "</head>\n";
        echo "<body>\n";

        // =========================================================================
        // SHEET 1: REKAP LENGKAP KUESIONER TRACER STUDY ALUMNI
        // =========================================================================
        echo "<table>\n";
        echo "  <tr><td colspan=\"8\" class=\"header-main\" style=\"border:none;\">LAPORAN LENGKAP KUESIONER TRACER STUDY ALUMNI</td></tr>\n";
        echo "  <tr><td colspan=\"8\" class=\"header-sub\" style=\"border:none;\">Universitas Kristen Duta Wacana (UKDW) | Diunduh pada: {$waktuUnduh}</td></tr>\n";
        echo "  <tr><td colspan=\"8\" style=\"border:none; height: 6px;\"></td></tr>\n";

        echo "  <tr><td colspan=\"8\" class=\"box-identitas-header\">IDENTITAS LENGKAP & REKAM JEJAK MAHASISWA / ALUMNI</td></tr>\n";

        // Sub 1: Data Diri
        echo "  <tr><td colspan=\"8\" class=\"box-identitas-subheader\">1. Data Diri & Kontak Pribadi</td></tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">NIM</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$alumni->nim}</td>\n";
        echo "    <td class=\"box-identitas-label\">Nama Lengkap</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$namaLengkap}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">No. BPJS / Asuransi</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$noBpjs}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">NISN</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$nisn}</td>\n";
        echo "    <td class=\"box-identitas-label\">Jenis Kelamin</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$jenisKelamin}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Tempat Lahir</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$tempatLahir}</td>\n";
        echo "    <td class=\"box-identitas-label\">Tanggal Lahir</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$tanggalLahir}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Agama</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$agama}</td>\n";
        echo "    <td class=\"box-identitas-label\">Golongan Darah</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$golDarah}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Kewarganegaraan</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$wargaNegara}</td>\n";
        echo "    <td class=\"box-identitas-label\">Nomor Telepon / WA</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$telepon}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Email Pribadi</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$emailPribadi}</td>\n";
        echo "    <td class=\"box-identitas-label\">Email Kampus / Mahasiswa</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$emailKampus}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">LinkedIn (URL / Username)</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$linkedin}</td>\n";
        echo "    <td class=\"box-identitas-label\">Media Sosial Lainnya</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$medsos}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Keahlian (Skills)</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$skills}</td>\n";
        echo "    <td class=\"box-identitas-label\">Pengalaman Kerja (Experience)</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$experience}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Alamat Domisili Lengkap</td>\n";
        echo "    <td colspan=\"7\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$alamatDomisiliLengkap}</td>\n";
        echo "  </tr>\n";

        // Sub 2: Data Akademik
        echo "  <tr><td colspan=\"8\" class=\"box-identitas-subheader\">2. Data Akademik & Kelulusan</td></tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Program Studi</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$prodiNama}</td>\n";
        echo "    <td class=\"box-identitas-label\">Fakultas</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$fakultasNama}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Angkatan Masuk</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$angkatanMasuk}</td>\n";
        echo "    <td class=\"box-identitas-label\">Status Mahasiswa</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$statusMahasiswa}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Tahun Lulus</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$tahunLulus}</td>\n";
        echo "    <td class=\"box-identitas-label\">Semester Kelulusan</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$semesterLulus}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">IPK Kumulatif</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$ipk}</td>\n";
        echo "    <td class=\"box-identitas-label\">Total SKS & Angka Kualitas</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">SKS: {$totalSks} | Angka Kualitas: {$totalAngkaKualitas}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Asal Sekolah (SMA/SMK)</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$asalSekolah}</td>\n";
        echo "    <td class=\"box-identitas-label\">Jurusan Asal Sekolah</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$jurusanSekolah}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Alamat Asal Sekolah</td>\n";
        echo "    <td colspan=\"7\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$alamatSekolah} ({$wilayahSekolah})</td>\n";
        echo "  </tr>\n";

        // Sub 3: Data Karier
        echo "  <tr><td colspan=\"8\" class=\"box-identitas-subheader\">3. Data Karier, Perusahaan & Atasan</td></tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Status / Kategori Pekerjaan</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$kategoriPekerjaan}</td>\n";
        echo "    <td class=\"box-identitas-label\">Posisi / Jabatan Pekerjaan</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$posisiJabatan}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Posisi Usaha / Wiraswasta</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$posisiWiraswasta}</td>\n";
        echo "    <td class=\"box-identitas-label\">Estimasi Gaji (Take Home Pay)</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$gaji}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Jenis / Bidang Pekerjaan</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$jenisPekerjaan}</td>\n";
        echo "    <td class=\"box-identitas-label\">Nama Perusahaan / Kantor</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$namaPerusahaan}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Sektor Perusahaan</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$sektorPerusahaan}</td>\n";
        echo "    <td class=\"box-identitas-label\">Skala Instansi / Perusahaan</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$skalaPerusahaan}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Jenis Usaha Perusahaan</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$jenisPerusahaan}</td>\n";
        echo "    <td class=\"box-identitas-label\">Jenis Lokasi & Negara</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$lokasiPerusahaan} ({$negaraPerusahaan})</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Alamat Lengkap Perusahaan</td>\n";
        echo "    <td colspan=\"7\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$alamatPerusahaan} (Kode Pos: {$kodePosPerusahaan})</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Nama Atasan Langsung</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$namaAtasan}</td>\n";
        echo "    <td class=\"box-identitas-label\">Kontak Atasan Langsung</td>\n";
        echo "    <td colspan=\"5\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">Telp: {$teleponAtasan} | Email: {$emailAtasan}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Pendidikan Lanjut (Studi S2/S3)</td>\n";
        echo "    <td colspan=\"7\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">Tingkat: {$tingkatPendidikanLanjut} | Kampus: {$kampusLanjut} | Program Studi: {$prodiLanjut}</td>\n";
        echo "  </tr>\n";
        echo "  <tr><td colspan=\"8\" style=\"border:none; height: 12px;\"></td></tr>\n";

        // Header Tabel Kuesioner
        echo "  <thead>\n";
        echo "    <tr class=\"table-header\">\n";
        echo "      <th style=\"width: 4%;\">No</th>\n";
        echo "      <th style=\"width: 18%;\">Bagian / Seksi</th>\n";
        echo "      <th style=\"width: 10%;\">Kode</th>\n";
        echo "      <th style=\"width: 34%;\">Pertanyaan Instrumen</th>\n";
        echo "      <th style=\"width: 8%;\">Tipe Input</th>\n";
        echo "      <th style=\"width: 6%;\">Sifat</th>\n";
        echo "      <th style=\"width: 6%;\">Status</th>\n";
        echo "      <th style=\"width: 14%;\">Respon / Jawaban Alumni</th>\n";
        echo "    </tr>\n";
        echo "  </thead>\n";
        echo "  <tbody>\n";

        // Kuesioner Universitas
        $no = 1;
        if ($kuesioner) {
            foreach ($kuesioner->sections as $section) {
                $sectionTitle = htmlspecialchars($section->title ?: ($section->section ?: 'Bagian Kuesioner'));
                echo "    <tr><td colspan=\"8\" class=\"section-row-univ\">Seksi {$section->order}: {$sectionTitle} (Kuesioner Universitas)</td></tr>\n";

                foreach ($section->subpertanyaans as $sub) {
                    $resp = $savedResponses->get($sub->id);
                    $isMandatory = KelengkapanTracerService::isMandatoryQuestion($sub->kode_pertanyaan);
                    $isHeader = in_array($sub->type, ['header', 'section_header']);

                    $answerText = '-';
                    $statusClass = 'status-x';
                    $statusText = 'x';

                    if ($isHeader) {
                        $answerText = '';
                        $statusClass = 'status-header';
                        $statusText = '-';
                    } elseif ($resp) {
                        $plainAnswer = ! empty($resp->answer_text) && trim((string) $resp->answer_text) !== ''
                            ? (string) $resp->answer_text
                            : (! empty($resp->answer) && trim((string) $resp->answer) !== '' ? (string) $resp->answer : null);

                        if ($plainAnswer !== null) {
                            $answerText = htmlspecialchars($plainAnswer);
                            $statusClass = 'status-v';
                            $statusText = 'v';
                        } elseif (is_array($resp->answer_json) && count($resp->answer_json) > 0) {
                            $jsonFormatted = [];
                            foreach ($resp->answer_json as $k => $v) {
                                $jsonFormatted[] = is_numeric($k) ? (string) $v : "{$k}: {$v}";
                            }
                            $answerText = htmlspecialchars(implode(', ', $jsonFormatted));
                            $statusClass = 'status-v';
                            $statusText = 'v';
                        }
                    }

                    $bgClass = ($no % 2 === 0) ? ' class="bg-alt"' : '';
                    $subPertanyaanText = htmlspecialchars($sub->subpertanyaan);
                    $tipeText = htmlspecialchars($sub->type ?: 'text');
                    $kodePertanyaan = htmlspecialchars($sub->kode_pertanyaan);
                    $sifatHtml = $isHeader ? '-' : ($isMandatory ? '<span class="badge-wajib">Wajib</span>' : '<span class="badge-opsional">Opsional</span>');

                    echo "    <tr{$bgClass}>\n";
                    echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">{$no}</td>\n";
                    echo "      <td style=\"mso-number-format:'\@';\">{$sectionTitle}</td>\n";
                    echo "      <td class=\"text-code\" style=\"mso-number-format:'\@';\">{$kodePertanyaan}</td>\n";
                    echo "      <td style=\"mso-number-format:'\@';\">{$subPertanyaanText}</td>\n";
                    echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">{$tipeText}</td>\n";
                    echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">{$sifatHtml}</td>\n";
                    echo "      <td class=\"{$statusClass}\" style=\"mso-number-format:'\@';\">{$statusText}</td>\n";
                    echo "      <td style=\"font-weight: 500; mso-number-format:'\@';\">{$answerText}</td>\n";
                    echo "    </tr>\n";

                    $no++;
                }
            }
        }

        // Kuesioner Prodi
        if ($prodiSections->isNotEmpty()) {
            foreach ($prodiSections as $pSection) {
                $pTitle = htmlspecialchars($pSection->title ?: 'Kuesioner Program Studi');
                echo "    <tr><td colspan=\"8\" class=\"section-row-prodi\">Seksi {$pSection->order}: {$pTitle} (Kuesioner Program Studi: {$prodiNama})</td></tr>\n";

                foreach ($pSection->questions as $pQ) {
                    $isHeader = in_array($pQ->type, ['header', 'section_header']);
                    $resp = $savedProdiResponses->get($pQ->id);
                    $isMandatory = (bool) $pQ->is_required;

                    $answerText = '-';
                    $statusClass = 'status-x';
                    $statusText = 'x';

                    if ($isHeader) {
                        $answerText = '';
                        $statusClass = 'status-header';
                        $statusText = '-';
                    } elseif ($resp) {
                        if (! empty($resp->answer_text) && trim((string) $resp->answer_text) !== '') {
                            $answerText = htmlspecialchars((string) $resp->answer_text);
                            $statusClass = 'status-v';
                            $statusText = 'v';
                        } elseif (is_array($resp->answer_json) && count($resp->answer_json) > 0) {
                            $jsonFormatted = [];
                            foreach ($resp->answer_json as $k => $v) {
                                $jsonFormatted[] = is_numeric($k) ? (string) $v : "{$k}: {$v}";
                            }
                            $answerText = htmlspecialchars(implode(', ', $jsonFormatted));
                            $statusClass = 'status-v';
                            $statusText = 'v';
                        }
                    }

                    $bgClass = ($no % 2 === 0) ? ' class="bg-alt"' : '';
                    $questionText = htmlspecialchars($pQ->question_text);
                    $tipeText = htmlspecialchars($pQ->type ?: 'text');
                    $codeText = htmlspecialchars($pQ->code);
                    $sifatHtml = $isHeader ? '-' : ($isMandatory ? '<span class="badge-wajib">Wajib</span>' : '<span class="badge-opsional">Opsional</span>');

                    echo "    <tr{$bgClass}>\n";
                    echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">{$no}</td>\n";
                    echo "      <td style=\"mso-number-format:'\@';\">{$pTitle}</td>\n";
                    echo "      <td class=\"text-code\" style=\"mso-number-format:'\@';\">{$codeText}</td>\n";
                    echo "      <td style=\"mso-number-format:'\@';\">{$questionText}</td>\n";
                    echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">{$tipeText}</td>\n";
                    echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">{$sifatHtml}</td>\n";
                    echo "      <td class=\"{$statusClass}\" style=\"mso-number-format:'\@';\">{$statusText}</td>\n";
                    echo "      <td style=\"font-weight: 500; mso-number-format:'\@';\">{$answerText}</td>\n";
                    echo "    </tr>\n";

                    $no++;
                }
            }
        }

        echo "  </tbody>\n";
        echo "</table>\n";

        // =========================================================================
        // SHEET 2: HASIL KUESIONER EVALUASI ATASAN / PENGGUNA LULUSAN
        // =========================================================================
        echo "<br style=\"page-break-before: always;\">\n";
        echo "<table>\n";
        echo "  <tr><td colspan=\"6\" class=\"header-main\" style=\"border:none;\">HASIL KUESIONER EVALUASI ATASAN / PENGGUNA LULUSAN</td></tr>\n";
        echo "  <tr><td colspan=\"6\" class=\"header-sub\" style=\"border:none;\">Penilaian Kepuasan & Kinerja Alumni: {$namaLengkap} ({$alumni->nim}) | UKDW</td></tr>\n";
        echo "  <tr><td colspan=\"6\" style=\"border:none; height: 6px;\"></td></tr>\n";

        echo "  <tr><td colspan=\"6\" class=\"box-identitas-header\">INFORMASI PENGGUNA LULUSAN (ATASAN LANGSUNG) & INSTANSI PENILAI</td></tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Nama Atasan Penilai</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$atasanEvaluatorNama}</td>\n";
        echo "    <td class=\"box-identitas-label\">Status Evaluasi</td>\n";
        echo '    <td colspan="3" class="box-identitas-val" style="font-weight: bold; color: '.($isSubmittedAtasan ? '#15803d' : '#b45309')."; mso-number-format:'\@';\">{$statusAtasanLabel}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Email Atasan</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$atasanEvaluatorEmail}</td>\n";
        echo "    <td class=\"box-identitas-label\">Nomor Telepon Atasan</td>\n";
        echo "    <td colspan=\"3\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$atasanEvaluatorTelepon}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Instansi / Perusahaan</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$perusahaanEvaluatorNama}</td>\n";
        echo "    <td class=\"box-identitas-label\">Jumlah Alumni UKDW</td>\n";
        echo "    <td colspan=\"3\" class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$jumlahAlumniUkdw}</td>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <td class=\"box-identitas-label\">Standar Gaji Awal</td>\n";
        echo "    <td class=\"box-identitas-val\" style=\"mso-number-format:'\@';\">{$standarGajiPertama}</td>\n";
        echo "    <td class=\"box-identitas-label\">Token Survei</td>\n";
        echo "    <td colspan=\"3\" class=\"box-identitas-val\" style=\"font-family: monospace; font-size: 9pt; mso-number-format:'\@';\">".($evaluasiAtasan?->token ?: '-')."</td>\n";
        echo "  </tr>\n";
        echo "  <tr><td colspan=\"6\" style=\"border:none; height: 10px;\"></td></tr>\n";

        // Tabel Penilaian Aspek
        echo "  <tr><td colspan=\"6\" class=\"box-identitas-subheader\">Daftar Penilaian Butir Aspek Kinerja Lulusan</td></tr>\n";
        echo "  <thead>\n";
        echo "    <tr class=\"table-header\">\n";
        echo "      <th style=\"width: 5%;\">No</th>\n";
        echo "      <th style=\"width: 15%;\">Kode Aspek</th>\n";
        echo "      <th style=\"width: 42%;\">Nama Aspek Penilaian</th>\n";
        echo "      <th style=\"width: 14%;\">Kategori</th>\n";
        echo "      <th style=\"width: 12%;\">Skor / Nilai</th>\n";
        echo "      <th style=\"width: 12%;\">Catatan Khusus</th>\n";
        echo "    </tr>\n";
        echo "  </thead>\n";
        echo "  <tbody>\n";

        if ($pertanyaansAtasan->isNotEmpty()) {
            $numAtasan = 1;
            foreach ($pertanyaansAtasan as $qAtasan) {
                $rAtasan = $responAtasanMap->get($qAtasan->id);
                $nilaiDisplay = '-';
                $catatanDisplay = '-';
                $statusValClass = 'status-x';

                if ($rAtasan && ! empty($rAtasan->nilai)) {
                    $nilaiDisplay = htmlspecialchars((string) $rAtasan->nilai);
                    $statusValClass = 'status-v';
                    if (! empty($rAtasan->catatan)) {
                        $catatanDisplay = htmlspecialchars((string) $rAtasan->catatan);
                    }
                }

                $bgClass = ($numAtasan % 2 === 0) ? ' class="bg-alt"' : '';
                echo "    <tr{$bgClass}>\n";
                echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">{$numAtasan}</td>\n";
                echo "      <td class=\"text-code\" style=\"mso-number-format:'\@';\">".htmlspecialchars($qAtasan->kode)."</td>\n";
                echo "      <td style=\"mso-number-format:'\@';\">".htmlspecialchars($qAtasan->aspek)."</td>\n";
                echo "      <td class=\"text-center\" style=\"mso-number-format:'\@';\">".htmlspecialchars($qAtasan->kategori ?: 'Kinerja')."</td>\n";
                echo "      <td class=\"{$statusValClass}\" style=\"font-size: 11pt; mso-number-format:'\@';\">{$nilaiDisplay}</td>\n";
                echo "      <td style=\"mso-number-format:'\@';\">{$catatanDisplay}</td>\n";
                echo "    </tr>\n";
                $numAtasan++;
            }
        } else {
            echo "    <tr><td colspan=\"6\" class=\"text-center\" style=\"padding: 15px; color: #64748b;\">Belum ada butir pertanyaan evaluasi atasan yang dikonfigurasi.</td></tr>\n";
        }

        echo "  </tbody>\n";
        echo "</table>\n";

        echo "</body>\n";
        echo "</html>\n";

        return ob_get_clean();
    }
}
