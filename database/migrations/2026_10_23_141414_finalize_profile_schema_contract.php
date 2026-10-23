<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menetapkan kontrak schema profil yang berlaku untuk instalasi lama dan baru.
 *
 * Migrasi ini melakukan backfill kontak/domisi ke biodata terlebih dahulu,
 * lalu menghapus kolom duplikat dari data_akademik. Data lain tidak dihapus.
 * View audit juga dibuat ulang setelah struktur tabel siap digunakan.
 */
return new class extends Migration
{
    /**
     * Menerapkan kontrak data profil kanonik dan membuat ulang view runtime.
     */
    public function up(): void
    {
        $this->dropViews();
        $this->ensureCanonicalProfileColumns();
        $this->backfillContactAndAddress();
        $this->removeLegacyColumns();
        $this->createViews();
    }

    /**
     * Tidak mengembalikan field sensitif atau data duplikat yang memang sudah
     * dihentikan. Nilai tersebut sengaja tidak dipulihkan agar schema tetap bersih.
     */
    public function down(): void
    {
        // Migrasi ini bersifat irreversible untuk menjaga keputusan penghapusan data sensitif.
    }

    /**
     * Menghapus view yang bergantung pada kolom sebelum schema tabel diubah.
     */
    private function dropViews(): void
    {
        foreach ([
            'v_alumni_audit_rekap',
            'v_alumni_profile_summary',
            'v_alumni_tracer_univ_status',
            'v_alumni_tracer_prodi_status',
            'v_alumni_kuesioner_autofill',
            'v_question_mappings',
        ] as $view) {
            DB::statement("DROP VIEW IF EXISTS {$view}");
        }
    }

    /**
     * Memastikan field alamat yang aktif tersedia pada biodata untuk instalasi lama.
     */
    private function ensureCanonicalProfileColumns(): void
    {
        if (! Schema::hasTable('biodata')) {
            return;
        }

        $missingColumns = collect(['kelurahan', 'kecamatan'])
            ->filter(fn (string $column): bool => ! Schema::hasColumn('biodata', $column))
            ->values()
            ->all();

        if ($missingColumns === []) {
            return;
        }

        Schema::table('biodata', function (Blueprint $table) use ($missingColumns): void {
            foreach ($missingColumns as $column) {
                $table->string($column, 50)->nullable();
            }
        });
    }

    /**
     * Memindahkan nilai lama hanya ketika biodata belum memiliki nilai.
     */
    private function backfillContactAndAddress(): void
    {
        if (! Schema::hasTable('biodata') || ! Schema::hasTable('data_akademik')) {
            return;
        }

        $academicColumns = collect([
            'nomor_telepon',
            'email_pribadi',
            'alamat_saat_ini',
            'kelurahan',
            'kecamatan',
            'kabupaten_id',
            'propinsi_id',
            'kode_pos',
        ])->filter(fn (string $column): bool => Schema::hasColumn('data_akademik', $column));

        if ($academicColumns->isEmpty()) {
            return;
        }

        DB::table('biodata')->orderBy('id')->chunkById(250, function ($biodataRows) use ($academicColumns): void {
            foreach ($biodataRows as $biodataRow) {
                $academicRow = DB::table('data_akademik')->where('nim', $biodataRow->nim)->first();

                if (! $academicRow) {
                    continue;
                }

                $updates = [];
                foreach ($academicColumns as $column) {
                    $currentValue = $biodataRow->{$column} ?? null;
                    $legacyValue = $academicRow->{$column} ?? null;

                    if (($currentValue === null || $currentValue === '') && $legacyValue !== null && $legacyValue !== '') {
                        $updates[$column] = $legacyValue;
                    }
                }

                if ($updates !== []) {
                    DB::table('biodata')->where('id', $biodataRow->id)->update($updates);
                }
            }
        });
    }

    /**
     * Menghapus field sensitif, relasi orang tua, dan field kontak duplikat secara defensif.
     */
    private function removeLegacyColumns(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->dropForeignKeyIfPresent('biodata', 'orang_tua_id');
        $this->dropForeignKeyIfPresent('data_akademik', 'kabupaten_id');
        $this->dropForeignKeyIfPresent('data_akademik', 'propinsi_id');
        $this->dropColumnsIfPresent('biodata', ['orang_tua_id', 'nik', 'npwp']);
        $this->dropColumnsIfPresent('data_akademik', [
            'nik', 'no_kk', 'npwp', 'nomor_telepon', 'email_pribadi',
            'alamat_saat_ini', 'kelurahan', 'kecamatan', 'kabupaten_id',
            'propinsi_id', 'kode_pos',
        ]);
        $this->dropColumnsIfPresent('yudisium', [
            'dosen_pembimbing_1', 'dosen_pembimbing_2', 'dosen_penguji_1', 'dosen_penguji_2',
        ]);

        if (Schema::hasTable('data_orang_tua')) {
            Schema::drop('data_orang_tua');
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Menghapus foreign key legacy bila memang masih terpasang pada database lama.
     */
    private function dropForeignKeyIfPresent(string $tableName, string $column): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });
        } catch (Throwable) {
            // Instalasi yang sudah bersih tidak memiliki constraint legacy ini.
        }
    }

    /**
     * Menghapus kolom yang masih ada tanpa mengganggu instalasi yang sudah bersih.
     */
    private function dropColumnsIfPresent(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existingColumns = collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn($tableName, $column))
            ->values()
            ->all();

        if ($existingColumns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existingColumns): void {
            foreach ($existingColumns as $column) {
                $table->dropColumn($column);
            }
        });
    }

    /**
     * Membuat seluruh view audit dengan referensi field kanonik terbaru.
     */
    private function createViews(): void
    {
        $this->createProfileSummaryView();
        $this->createUniversityStatusView();
        $this->createProdiStatusView();
        $this->createAuditView();
        $this->createAutofillView();
        $this->createQuestionMappingsView();
    }

    /**
     * View ringkasan profil untuk dashboard dan administrasi.
     */
    private function createProfileSummaryView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW v_alumni_profile_summary AS
            SELECT b.id AS biodata_id, b.user_id, b.nim,
                COALESCE(da.nama, u.name, 'Mahasiswa UKDW') AS nama, da.no_bpjs, da.nisn,
                COALESCE(b.email_pribadi, u.email) AS email,
                COALESCE(b.email_pribadi, u.email) AS email_pribadi,
                da.email_students, b.nomor_telepon,
                da.tempat_lahir, da.tanggal_lahir, da.jenis_kelamin, da.agama, da.golongan_darah, da.warga_negara,
                b.alamat_saat_ini AS alamat, b.kelurahan, b.kecamatan, b.kode_pos,
                b.propinsi_id, prov.nama_provinsi, b.kabupaten_id, kab.nama_kabupaten,
                b.instagram_url, b.facebook_url, b.linkedin_url, b.linkedin_username, b.skills, b.experience,
                b.prodi_id, p.kode_prodi, p.nama_prodi, p.fakultas_id, f.kode_fakultas, f.nama_fakultas,
                f.singkatan AS singkatan_fakultas, da.angkatan_masuk, da.tahun_lulus, da.tanggal_lulus,
                da.tahun_akademik_lulus, da.ip_kumulatif AS ipk, da.total_sks, da.total_angka_kualitas,
                da.status_mahasiswa, da.asal_sekolah, da.alamat_asal_sekolah, da.kota_kabupaten_asal_sekolah,
                da.provinsi_asal_sekolah, da.jurusan_asal_sekolah,
                y.judul_ta, y.judul_ta_inggris, y.url_publikasi, y.jenis_publikasi, y.status_publikasi,
                y.keterangan_hasil_yudisium, COALESCE(y.status_lulus, 'Belum') AS status_yudisium,
                b.kategori_pekerjaan, b.posisi_jabatan, b.posisi_wiraswasta, b.pendidikan_tingkat,
                b.perguruan_tinggi, b.pendidikan_prodi, b.gaji, b.jenis_pekerjaan, b.perusahaan_id,
                c.nama_perusahaan, c.alamat AS alamat_perusahaan, c.sektor AS sektor_perusahaan, c.skala AS skala_perusahaan,
                c.jenis_perusahaan, c.jenis_lokasi AS jenis_lokasi_perusahaan, c.negara AS negara_perusahaan,
                a.nama AS nama_atasan, a.email AS email_atasan, a.telepon AS telepon_atasan,
                CASE WHEN COALESCE(da.nama, u.name) IS NOT NULL AND b.nim IS NOT NULL
                    AND b.nomor_telepon IS NOT NULL AND COALESCE(b.email_pribadi, u.email) IS NOT NULL THEN 1 ELSE 0 END AS is_profile_complete
            FROM biodata b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN data_akademik da ON da.nim = b.nim
            LEFT JOIN prodi p ON b.prodi_id = p.id
            LEFT JOIN ref_fakultas f ON p.fakultas_id = f.id
            LEFT JOIN propinsi prov ON b.propinsi_id = prov.id
            LEFT JOIN kabupaten kab ON b.kabupaten_id = kab.id
            LEFT JOIN perusahaan c ON b.perusahaan_id = c.id
            LEFT JOIN atasan a ON b.atasan_id = a.id
            LEFT JOIN yudisium y ON y.nim = b.nim
            SQL);
    }

    /**
     * View status jawaban wajib kuesioner universitas dengan skip logic yang berlaku.
     */
    private function createUniversityStatusView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW v_alumni_tracer_univ_status AS
            SELECT b.id AS biodata_id,
                ((SELECT COUNT(*) FROM ref_subpertanyaan2021 q2 WHERE q2.wajib = 1 AND q2.type != 'header'
                 AND UPPER(q2.kode_pertanyaan) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506'))
                 + CASE WHEN EXISTS (SELECT 1 FROM ref_subpertanyaan2021 q3 WHERE q3.wajib = 1 AND UPPER(q3.kode_pertanyaan) IN ('F502','F506')) THEN 1 ELSE 0 END) AS total_mandatory_univ,
                COUNT(DISTINCT CASE WHEN q.wajib = 1 AND q.type != 'header'
                    AND UPPER(q.kode_pertanyaan) NOT IN ('F1','F2A','F2B','F2C','F2D','BIO_TEMPAT_LAHIR','BIO_TANGGAL_LAHIR','BIO_JK','BIO_TGL_LULUS','BIO_JUDUL_TA','F5A1','F5A2','F5B','F5C','F5D','F510','F2E','F2E1','F2E2','F2E3','F2F','F2G','F2H','F11','F505','F502','F506')
                    AND ((t.answer IS NOT NULL AND TRIM(t.answer) != '') OR (t.answer_json IS NOT NULL AND t.answer_json NOT IN ('','[]','{}'))) THEN q.id ELSE NULL END) AS answered_mandatory_univ,
                COUNT(DISTINCT CASE WHEN (t.answer IS NOT NULL AND TRIM(t.answer) != '') OR (t.answer_json IS NOT NULL AND t.answer_json NOT IN ('','[]','{}')) THEN t.question_id ELSE NULL END) AS total_answered_univ
            FROM biodata b LEFT JOIN tracer t ON t.biodata_id = b.id LEFT JOIN ref_subpertanyaan2021 q ON t.question_id = q.id GROUP BY b.id
            SQL);
    }

    /**
     * View status kuesioner program studi untuk satu alumni.
     */
    private function createProdiStatusView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW v_alumni_tracer_prodi_status AS
            SELECT b.id AS biodata_id, b.prodi_id,
                COALESCE((SELECT COUNT(*) FROM prodi_question pq WHERE pq.prodi_id = b.prodi_id AND pq.type != 'header'), 0) AS total_prodi_questions,
                COUNT(DISTINCT CASE WHEN ((pr.answer_text IS NOT NULL AND TRIM(pr.answer_text) != '') OR (pr.answer_json IS NOT NULL AND pr.answer_json NOT IN ('','[]','{}'))) THEN pr.prodi_question_id ELSE NULL END) AS answered_prodi_questions
            FROM biodata b LEFT JOIN prodi_response pr ON pr.biodata_id = b.id GROUP BY b.id, b.prodi_id
            SQL);
    }

    /**
     * View audit gabungan profil, kuesioner universitas, dan kuesioner prodi.
     */
    private function createAuditView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW v_alumni_audit_rekap AS
            SELECT ps.*, COALESCE(tus.total_mandatory_univ, 0) AS total_mandatory_univ, COALESCE(tus.answered_mandatory_univ, 0) AS answered_mandatory_univ, COALESCE(tus.total_answered_univ, 0) AS total_answered_univ,
                CASE WHEN COALESCE(tus.total_mandatory_univ, 0) > 0 THEN ROUND((COALESCE(tus.answered_mandatory_univ, 0) * 100.0) / tus.total_mandatory_univ) ELSE 0 END AS univ_percentage,
                CASE WHEN COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ THEN 1 ELSE 0 END AS is_complete_univ,
                COALESCE(tps.total_prodi_questions, 0) AS total_prodi_questions, COALESCE(tps.answered_prodi_questions, 0) AS answered_prodi_questions,
                CASE WHEN COALESCE(tps.total_prodi_questions, 0) > 0 THEN ROUND((COALESCE(tps.answered_prodi_questions, 0) * 100.0) / tps.total_prodi_questions) ELSE NULL END AS prodi_percentage,
                CASE WHEN COALESCE(tps.total_prodi_questions, 0) > 0 AND COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions THEN 1 WHEN COALESCE(tps.total_prodi_questions, 0) > 0 THEN 0 ELSE NULL END AS is_complete_prodi,
                CASE WHEN ps.is_profile_complete = 1 AND COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions) THEN 1 ELSE 0 END AS is_complete_total,
                CASE WHEN ps.is_profile_complete = 1 AND COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ AND (COALESCE(tps.total_prodi_questions, 0) = 0 OR COALESCE(tps.answered_prodi_questions, 0) >= tps.total_prodi_questions) THEN 'Selesai' ELSE 'Belum Selesai' END AS status_tracer_label
            FROM v_alumni_profile_summary ps LEFT JOIN v_alumni_tracer_univ_status tus ON ps.biodata_id = tus.biodata_id LEFT JOIN v_alumni_tracer_prodi_status tps ON ps.biodata_id = tps.biodata_id
            SQL);
    }

    /**
     * View autofill kuesioner yang membaca data profil aktif dan sumber akademik resmi.
     */
    private function createAutofillView(): void
    {
        $driver = DB::getDriverName();
        $castGaji = $driver === 'sqlite' ? 'CAST(b.gaji AS TEXT)' : 'CAST(b.gaji AS CHAR)';
        $concatLuar = $driver === 'sqlite' ? "TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(p.negara || ', ', '') || COALESCE(p.kode_pos, ''), ', ')" : "CONCAT_WS(', ', p.alamat, p.negara, p.kode_pos)";
        $concatDalam = $driver === 'sqlite' ? "TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(kab.nama_kabupaten || ', ', '') || COALESCE(prov.nama_provinsi || ', ', '') || COALESCE(p.kode_pos, ''), ', ')" : "CONCAT_WS(', ', p.alamat, kab.nama_kabupaten, prov.nama_provinsi, p.kode_pos)";

        DB::statement("CREATE VIEW v_alumni_kuesioner_autofill AS SELECT u.id AS user_id, b.id AS biodata_id, b.nim AS F1, COALESCE(da.nama, u.name) AS F2A, b.nomor_telepon AS F2B, COALESCE(b.email_pribadi, u.email) AS F2C, b.alamat_saat_ini AS F2D, p.nama_perusahaan AS F2E, a.nama AS F2E1, a.telepon AS F2E2, a.email AS F2E3, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN {$concatLuar} ELSE {$concatDalam} END AS F2F, b.posisi_jabatan AS F2G, p.skala AS F2H, p.nama_perusahaan AS F5B, CASE WHEN b.kategori_pekerjaan = 'Wiraswasta' THEN b.posisi_wiraswasta ELSE NULL END AS F5C, p.skala AS F5D, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN {$concatLuar} ELSE {$concatDalam} END AS F510, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN p.negara ELSE prov.nama_provinsi END AS F5a1, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN p.negara ELSE kab.nama_kabupaten END AS F5a2, p.jenis_perusahaan AS F11, {$castGaji} AS F505, b.kategori_pekerjaan AS F8, b.pendidikan_tingkat AS BIO_PENDIDIKAN_TINGKAT, b.perguruan_tinggi AS F18B, b.pendidikan_prodi AS F18C, da.tempat_lahir AS BIO_TEMPAT_LAHIR, da.tanggal_lahir AS BIO_TANGGAL_LAHIR, da.jenis_kelamin AS BIO_JK, da.tanggal_lulus AS BIO_TGL_LULUS, y.judul_ta AS BIO_JUDUL_TA FROM users u LEFT JOIN biodata b ON b.user_id = u.id LEFT JOIN data_akademik da ON da.nim = b.nim LEFT JOIN perusahaan p ON p.id = b.perusahaan_id LEFT JOIN atasan a ON a.id = b.atasan_id LEFT JOIN yudisium y ON y.nim = b.nim LEFT JOIN propinsi prov ON prov.id = p.propinsi_id LEFT JOIN kabupaten kab ON kab.id = p.kabupaten_id");
    }

    /**
     * View metadata pemetaan butir ke sumber data kanonik.
     */
    private function createQuestionMappingsView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW v_question_mappings AS
            SELECT 1 AS id, 'data_akademik' AS table_name, 'nim' AS column_name, q.id AS question_id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F1'
            UNION ALL SELECT 2, 'data_akademik', 'nama', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2A'
            UNION ALL SELECT 3, 'biodata', 'nomor_telepon', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2B'
            UNION ALL SELECT 4, 'biodata', 'email_pribadi', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2C'
            UNION ALL SELECT 5, 'biodata', 'alamat_saat_ini', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2D'
            UNION ALL SELECT 6, 'data_akademik', 'tempat_lahir', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_TEMPAT_LAHIR'
            UNION ALL SELECT 7, 'data_akademik', 'tanggal_lahir', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_TANGGAL_LAHIR'
            UNION ALL SELECT 8, 'data_akademik', 'jenis_kelamin', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_JK'
            UNION ALL SELECT 9, 'data_akademik', 'tanggal_lulus', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_TGL_LULUS'
            UNION ALL SELECT 10, 'yudisium', 'judul_ta', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'BIO_JUDUL_TA'
            UNION ALL SELECT 11, 'perusahaan', 'nama_perusahaan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E'
            UNION ALL SELECT 12, 'atasan', 'nama', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E1'
            UNION ALL SELECT 13, 'atasan', 'telepon', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E2'
            UNION ALL SELECT 14, 'atasan', 'email', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2E3'
            UNION ALL SELECT 15, 'perusahaan', 'alamat', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2F'
            UNION ALL SELECT 16, 'biodata', 'posisi_jabatan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2G'
            UNION ALL SELECT 17, 'perusahaan', 'skala', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F2H'
            UNION ALL SELECT 18, 'perusahaan', 'nama_perusahaan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F5B'
            UNION ALL SELECT 19, 'biodata', 'posisi_jabatan', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F5C'
            UNION ALL SELECT 20, 'perusahaan', 'skala', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F5D'
            UNION ALL SELECT 21, 'perusahaan', 'alamat', q.id, q.kode_pertanyaan FROM ref_subpertanyaan2021 q WHERE q.kode_pertanyaan = 'F510'
            SQL);
    }
};
