<?php

namespace App\Services\Alumni;

use App\Models\AlumniLinkedinPlot;
use App\Models\Biodata;
use App\Models\LinkedinSyncResult;

class LinkedinTraceHelper
{
    /**
     * Mengambil ringkasan plotting LinkedIn langsung dari Database View
     */
    public static function getPlotSummary(Biodata $alumni): ?AlumniLinkedinPlot
    {
        return $alumni->linkedinPlot ?? AlumniLinkedinPlot::where('biodata_id', $alumni->id)->first();
    }

    /**
     * Membangun Baris Pemetaan Audit Trace Hasil Scraping LinkedIn ke Basis Data
     */
    public static function buildTraceMapping(Biodata $alumni, ?LinkedinSyncResult $syncResult): array
    {
        if (! $syncResult) {
            return [];
        }

        $item = $syncResult->getProfileItem();
        if (empty($item)) {
            return [];
        }

        $preview = $syncResult->getPreviewData();
        $plot = self::getPlotSummary($alumni);

        $rows = [];
        $handledKeys = [];

        // 1. Foto Profil
        $scrapedFoto = $preview['profile_picture'] ?? ($item['li_profile_image_url'] ?? ($item['profile_picture'] ?? ($item['profilePicture'] ?? ($item['picture_url'] ?? null))));
        $dbFoto = $alumni->foto;
        $rows[] = [
            'key' => 'li_profile_image_url',
            'label' => 'Foto Profil Alumni',
            'scraped_value' => $scrapedFoto ?: '(Tidak ditemukan)',
            'target_table' => 'biodata',
            'target_column' => 'foto',
            'db_value' => $dbFoto ?: '(Belum tersimpan di biodata)',
            'status' => ! empty($dbFoto) ? 'Tersinkron' : 'Belum Disinkron',
            'badge' => ! empty($dbFoto) ? 'emerald' : 'amber',
            'note' => 'Disimpan permanen ke direktori /uploads/profile/',
        ];
        $handledKeys = array_merge($handledKeys, ['li_profile_image_url', 'profile_picture', 'profilePicture', 'picture_url']);

        // 2. Posisi Jabatan / Job Title
        // Headline adalah teks branding profil, bukan sumber posisi atau jabatan.
        $scrapedJob = $preview['current_job'] ?? ($item['job_title'] ?? ($item['jobTitle'] ?? null));
        $dbJob = $alumni->posisi_jabatan;
        $rows[] = [
            'key' => 'job_title',
            'label' => 'Posisi Jabatan Struktural (F2G)',
            'scraped_value' => $scrapedJob ?: '(Tidak ditemukan)',
            'target_table' => 'biodata',
            'target_column' => 'posisi_jabatan',
            'db_value' => $dbJob ?: '(Belum terisi)',
            'status' => ! empty($dbJob) ? 'Tersinkron' : 'Belum Disinkron',
            'badge' => ! empty($dbJob) ? 'emerald' : 'amber',
            'note' => 'Jabatan profesional hasil ekstraksi LinkedIn',
        ];
        $handledKeys = array_merge($handledKeys, ['job_title', 'jobTitle']);

        // 3. Status Pekerjaan (Aktivitas Utama)
        $dbKategori = $alumni->kategori_pekerjaan;
        $derivedStatus = (! empty($scrapedJob) || ! empty($preview['current_company']) || ! empty($item['company_name'])) ? 'Pekerja' : 'Belum Bekerja';
        $rows[] = [
            'key' => 'status_pekerjaan',
            'label' => 'Status Pekerjaan / Aktivitas Utama',
            'scraped_value' => $derivedStatus.' / Karyawan',
            'target_table' => 'biodata',
            'target_column' => 'kategori_pekerjaan',
            'db_value' => $dbKategori ?: '(Belum terisi)',
            'status' => ($dbKategori === 'Pekerja' || $dbKategori === 'Wiraswasta') ? 'Tersinkron' : 'Belum Disinkron',
            'badge' => ($dbKategori === 'Pekerja' || $dbKategori === 'Wiraswasta') ? 'emerald' : 'amber',
            'note' => "Pilihan: 'Pekerja' atau 'Wiraswasta'",
        ];

        // 4. Nama Perusahaan / Tempat Bekerja
        $scrapedCompany = $preview['current_company'] ?? ($item['company_name'] ?? ($item['companyName'] ?? null));
        $dbCompany = $alumni->perusahaan?->nama_perusahaan;
        $rows[] = [
            'key' => 'company_name',
            'label' => 'Nama Perusahaan / Tempat Bekerja',
            'scraped_value' => $scrapedCompany ?: '(Tidak ditemukan)',
            'target_table' => 'perusahaan (relasi biodata.perusahaan_id)',
            'target_column' => 'nama_perusahaan',
            'db_value' => $dbCompany ?: '(Belum terhubung)',
            'status' => ! empty($dbCompany) ? 'Tersinkron' : 'Belum Disinkron',
            'badge' => ! empty($dbCompany) ? 'emerald' : 'amber',
            'note' => 'Terkoneksi ke tabel master perusahaan',
        ];
        $handledKeys = array_merge($handledKeys, ['company_name', 'companyName']);

        // 5. Status Verifikasi Perusahaan
        $dbStatusVerif = $alumni->perusahaan?->status_verifikasi;
        $rows[] = [
            'key' => 'perusahaan.status_verifikasi',
            'label' => 'Status Verifikasi Perusahaan',
            'scraped_value' => 'Menunggu Verifikasi (Default Approval)',
            'target_table' => 'perusahaan',
            'target_column' => 'status_verifikasi',
            'db_value' => $dbStatusVerif ?: '(Belum terdaftar)',
            'status' => $dbStatusVerif === 'Terverifikasi' ? 'Terverifikasi' : 'Menunggu Verifikasi',
            'badge' => $dbStatusVerif === 'Terverifikasi' ? 'emerald' : 'amber',
            'note' => 'Diverifikasi langsung oleh admin kampus',
        ];

        // 6. URL Profil LinkedIn
        $scrapedUrl = $item['li_profile_url'] ?? ($item['linkedin_url'] ?? ($item['url'] ?? ($item['input'] ?? $syncResult->linkedin_url)));
        $dbUrl = $alumni->linkedin_url;
        $rows[] = [
            'key' => 'li_profile_url',
            'label' => 'URL Profil LinkedIn',
            'scraped_value' => $scrapedUrl ?: '(Tidak ditemukan)',
            'target_table' => 'biodata',
            'target_column' => 'linkedin_url',
            'db_value' => $dbUrl ?: '(Belum terisi)',
            'status' => ! empty($dbUrl) ? 'Tersinkron' : 'Belum Disinkron',
            'badge' => ! empty($dbUrl) ? 'emerald' : 'amber',
            'note' => 'Tautan profil resmi alumni di LinkedIn',
        ];
        $handledKeys = array_merge($handledKeys, ['li_profile_url', 'linkedin_url', 'url', 'input', 'source_url']);

        // 7. Username LinkedIn
        $scrapedHandle = $item['li_profile_handle'] ?? ($item['handle'] ?? ($item['publicIdentifier'] ?? $syncResult->linkedin_username));
        $dbHandle = $alumni->linkedin_username;
        $isHandleMatch = ! empty($scrapedHandle) && ! empty($dbHandle) && strtolower(trim((string) $scrapedHandle)) === strtolower(trim((string) $dbHandle));
        $rows[] = [
            'key' => 'li_profile_handle',
            'label' => 'Username LinkedIn',
            'scraped_value' => $scrapedHandle ?: '(Tidak ditemukan)',
            'target_table' => 'biodata',
            'target_column' => 'linkedin_username',
            'db_value' => $dbHandle ?: '(Belum terisi)',
            'status' => $isHandleMatch ? 'Tersinkron' : (! empty($dbHandle) ? 'Berbeda dengan Profil LinkedIn' : 'Belum Disinkron'),
            'badge' => $isHandleMatch ? 'emerald' : (! empty($dbHandle) ? 'rose' : 'amber'),
            'note' => 'Identifier publik unik profil LinkedIn (otomatis diselaraskan dari URL profil)',
        ];
        $handledKeys = array_merge($handledKeys, ['li_profile_handle', 'handle', 'publicIdentifier']);

        // 8. Jenjang Pendidikan Lanjut
        $scrapedDegree = $item['education'][0]['degree_name'] ?? ($item['education'][0]['degree'] ?? null);
        $dbDegree = $alumni->pendidikan_tingkat;
        $rows[] = [
            'key' => 'education[0].degree_name',
            'label' => 'Jenjang Studi Lanjut',
            'scraped_value' => $scrapedDegree ?: '(Tidak tercantum)',
            'target_table' => 'biodata',
            'target_column' => 'pendidikan_tingkat',
            'db_value' => $dbDegree ?: '(Belum terisi)',
            'status' => ! empty($dbDegree) ? 'Tersinkron' : 'Opsional / Belum Terisi',
            'badge' => ! empty($dbDegree) ? 'emerald' : 'slate',
            'note' => 'Jenjang studi lanjutan alumni (S1/S2/S3/Spesialis)',
        ];

        // 9. Perguruan Tinggi Studi Lanjut
        $scrapedSchool = $item['education'][0]['school_name'] ?? ($item['education'][0]['school'] ?? ($item['school_name'] ?? null));
        $dbSchool = $alumni->perguruan_tinggi;
        $rows[] = [
            'key' => 'education[0].school_name',
            'label' => 'Perguruan Tinggi Studi Lanjut',
            'scraped_value' => $scrapedSchool ?: '(Tidak tercantum)',
            'target_table' => 'biodata',
            'target_column' => 'perguruan_tinggi',
            'db_value' => $dbSchool ?: '(Belum terisi)',
            'status' => ! empty($dbSchool) ? 'Tersinkron' : 'Opsional / Belum Terisi',
            'badge' => ! empty($dbSchool) ? 'emerald' : 'slate',
            'note' => 'Nama universitas / institut tempat studi lanjut',
        ];

        // 10. Program Studi Studi Lanjut
        $scrapedProdi = $item['education'][0]['field_of_study'] ?? ($item['education'][0]['field'] ?? null);
        $dbProdi = $alumni->pendidikan_prodi;
        $rows[] = [
            'key' => 'education[0].field_of_study',
            'label' => 'Program Studi Studi Lanjut',
            'scraped_value' => $scrapedProdi ?: '(Tidak tercantum)',
            'target_table' => 'biodata',
            'target_column' => 'pendidikan_prodi',
            'db_value' => $dbProdi ?: '(Belum terisi)',
            'status' => ! empty($dbProdi) ? 'Tersinkron' : 'Opsional / Belum Terisi',
            'badge' => ! empty($dbProdi) ? 'emerald' : 'slate',
            'note' => 'Nama jurusan / program studi lanjut',
        ];
        $handledKeys = array_merge($handledKeys, ['education', 'school_name', 'li_school_url']);

        // 11. Lokasi Profil LinkedIn
        // Lokasi profil bukan alamat rumah atau alamat kantor. Nilai ini hanya
        // disimpan sebagai bagian dari histori scraping dan tidak boleh diberi
        // status tersinkron hanya karena alamat biodata sudah terisi.
        $scrapedLocation = $item['location'] ?? ($item['locationName'] ?? ($item['geoCountryName'] ?? null));
        $hasScrapedLocation = ! empty($scrapedLocation);
        $rows[] = [
            'key' => 'location',
            'label' => 'Lokasi Profil LinkedIn',
            'scraped_value' => $scrapedLocation ?: '(Tidak tercantum)',
            'target_table' => 'linkedin_sync_results',
            'target_column' => 'scraped_data->location',
            'db_value' => $scrapedLocation ?: '(Tidak tersimpan)',
            'status' => $hasScrapedLocation ? 'Tersimpan di Histori' : 'Tidak Ditemukan',
            'badge' => $hasScrapedLocation ? 'emerald' : 'slate',
            'note' => 'Lokasi umum profil LinkedIn; tidak digunakan sebagai alamat rumah atau alamat perusahaan.',
        ];
        $handledKeys = array_merge($handledKeys, ['location', 'locationName', 'geoCountryName', 'li_profile_country']);

        // 12. Nama Lengkap & Verifikasi Kesesuaian
        $scrapedName = $item['full_name'] ?? ($item['fullName'] ?? (($item['first_name'] ?? '').' '.($item['last_name'] ?? '')));
        $dbName = $alumni->dataAkademik?->nama ?? $alumni->nama;
        $nameStatus = $plot?->keterangan_nama ?? 'Terkunci Data Akademik';
        $nameBadge = match ($plot?->keterangan_nama) {
            'Nama Sesuai Eksak' => 'emerald',
            'Nama Sesuai Parsial' => 'sky',
            'Nama Berbeda (Perlu Verifikasi)' => 'rose',
            default => 'slate'
        };
        $rows[] = [
            'key' => 'full_name',
            'label' => 'Nama Lengkap Mahasiswa / Alumni',
            'scraped_value' => trim((string) $scrapedName) ?: '(Tidak tercantum)',
            'target_table' => 'data_akademik (Master)',
            'target_column' => 'nama',
            'db_value' => $dbName ?: '(Kosong)',
            'status' => $nameStatus,
            'badge' => $nameBadge,
            'note' => $plot?->keterangan_nama ? 'Validasi: '.$plot->keterangan_nama : 'Data nama resmi dikunci pangkalan data kampus',
        ];
        $handledKeys = array_merge($handledKeys, ['full_name', 'fullName', 'first_name', 'last_name']);

        // 13. Status Plotting ke Biodata
        if ($plot) {
            $plotBadge = match ($plot->status_plot_label) {
                'Sudah Terplot (Approved)' => 'emerald',
                'Data Sama / Siap Diplot' => 'sky',
                'Menunggu Review (Pending)' => 'amber',
                'Ditolak (Rejected)' => 'rose',
                default => 'slate'
            };
            $rows[] = [
                'key' => 'status_plotting',
                'label' => 'Status Plotting ke Biodata',
                'scraped_value' => $plot->posisi_linkedin ? ($plot->posisi_linkedin.' di '.($plot->perusahaan_linkedin ?: '-')) : '(Data scraping kosong)',
                'target_table' => 'v_alumni_linkedin_plot',
                'target_column' => 'status_plot_label',
                'db_value' => $plot->posisi_biodata ? ($plot->posisi_biodata.' di '.($plot->perusahaan_biodata ?: '-')) : '(Belum terisi di biodata)',
                'status' => $plot->status_plot_label,
                'badge' => $plotBadge,
                'note' => $plot->is_terplot_ke_biodata ? 'Telah terplot ke profil utama biodata' : 'Memerlukan review sebelum diplot',
            ];
        }

        // 13. Headline
        if (isset($item['headline'])) {
            $rows[] = [
                'key' => 'headline',
                'label' => 'Headline LinkedIn',
                'scraped_value' => (string) $item['headline'],
                'target_table' => 'linkedin_sync_results',
                'target_column' => 'scraped_data->headline',
                'db_value' => (string) $item['headline'],
                'status' => 'Tersimpan di Histori',
                'badge' => 'emerald',
                'note' => 'Teks bio / headline profil LinkedIn',
            ];
            $handledKeys[] = 'headline';
        }

        // 14. Keahlian (Skills)
        $skillsList = $preview['skills'] ?? [];
        if (empty($skillsList) && ! empty($item['skills'])) {
            $rawSkills = $item['skills'];
            if (is_array($rawSkills)) {
                foreach ($rawSkills as $skill) {
                    if (is_string($skill)) {
                        $skillsList[] = $skill;
                    } elseif (is_array($skill) && ! empty($skill['name'])) {
                        $skillsList[] = $skill['name'];
                    }
                }
            }
        }
        $scrapedSkills = ! empty($skillsList) ? implode(', ', array_slice($skillsList, 0, 10)) : null;
        $dbSkills = $alumni->skills;
        $rows[] = [
            'key' => 'skills',
            'label' => 'Keahlian Alumni (Skills)',
            'scraped_value' => $scrapedSkills ?: '(Tidak dicantumkan publik)',
            'target_table' => 'biodata',
            'target_column' => 'skills',
            'db_value' => $dbSkills ?: '(Belum terisi)',
            'status' => ! empty($dbSkills) ? 'Tersinkron' : 'Belum Disinkron',
            'badge' => ! empty($dbSkills) ? 'emerald' : 'amber',
            'note' => 'Daftar keahlian profesional LinkedIn alumni',
        ];
        $handledKeys = array_merge($handledKeys, ['skills']);

        // 15. Experiences (Riwayat Pengalaman Kerja)
        $rawExp = $item['experiences'] ?? ($item['experience'] ?? []);
        if (is_array($rawExp) && ! empty($rawExp)) {
            $countExp = count($rawExp);
            $firstExpTitle = $rawExp[0]['title'] ?? ($rawExp[0]['position'] ?? '-');
            $firstExpComp = $rawExp[0]['company_name'] ?? ($rawExp[0]['company'] ?? '-');
            $dbExp = $alumni->experience;
            $rows[] = [
                'key' => 'experiences',
                'label' => "Riwayat Pengalaman Kerja ({$countExp} entri)",
                'scraped_value' => "Posisi Terkini: {$firstExpTitle} di {$firstExpComp} (+".($countExp - 1).' lainnya)',
                'target_table' => 'biodata',
                'target_column' => 'experience',
                'db_value' => $dbExp ?: "{$alumni->posisi_jabatan} di ".($alumni->perusahaan?->nama_perusahaan ?? '-'),
                'status' => ! empty($dbExp) ? 'Tersinkron' : 'Belum Disinkron',
                'badge' => ! empty($dbExp) ? 'emerald' : 'amber',
                'note' => 'Riwayat posisi dan tempat kerja hasil sinkronisasi LinkedIn',
            ];
            $handledKeys = array_merge($handledKeys, ['experiences', 'experience', 'positions']);
        }

        // Daftar key teknis / internal Apify & duplikat yang tidak perlu dipetakan ulang
        $ignoredTechnicalKeys = [
            'summary', 'about', 'headline', 'skills', 'experience', 'experiences', 'positions',
            'past_job_title', 'past_company_name',
            'operation', 'scraped_at', 'entity_type', 'li_profile_id', 'li_company_url',
            'li_number_followers', 'li_number_connections', 'volunteer_experiences',
            'input', 'url', 'source_url', 'li_school_url', 'publicIdentifier',
        ];
        $handledKeys = array_merge($handledKeys, $ignoredTechnicalKeys);

        return $rows;
    }
}
