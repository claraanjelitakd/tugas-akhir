<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Merekonstruksi seluruh view yang bergantung pada kolom atau tabel yang diubah
 * oleh refaktorisasi skema (200001-200007): prodi -> ref_prodi, prodi_id -> kode_prodi,
 * fakultas_id -> kode_fakultas, reviewed_by -> user_id.
 *
 * Migration ini berjalan setelah alter migration selesai sehingga aman untuk
 * produksi MySQL maupun fresh install SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMySQL = DB::connection()->getDriverName() !== 'sqlite';

        DB::statement('DROP VIEW IF EXISTS v_alumni_profile_summary');
        DB::statement(<<<'SQL'
CREATE VIEW v_alumni_profile_summary AS
SELECT
    b.id AS biodata_id, b.user_id, b.nim,
    COALESCE(da.nama, u.name, 'Mahasiswa UKDW') AS nama,
    COALESCE(b.email_pribadi, u.email) AS email,
    COALESCE(b.email_pribadi, u.email) AS email_pribadi,
    b.foto, b.nomor_telepon, da.tanggal_lahir, da.jenis_kelamin, da.warga_negara,
    b.alamat_saat_ini AS alamat, b.kelurahan, b.kecamatan, b.kode_pos,
    b.kode_provinsi, prov.nama_provinsi, b.kode_kabupaten, kab.nama_kabupaten,
    b.instagram_url, b.facebook_url, b.linkedin_url, b.linkedin_username, b.skills, b.experience,
    b.kode_prodi, p.nama_prodi, p.kode_fakultas, f.nama_fakultas,
    f.singkatan AS singkatan_fakultas, da.angkatan_masuk, da.tahun_lulus, da.tanggal_lulus,
    da.tahun_akademik_lulus, da.ip_kumulatif AS ipk, da.total_sks, da.total_angka_kualitas,
    da.status_mahasiswa,
    y.judul_ta, y.judul_ta_inggris, y.url_publikasi, y.jenis_publikasi, y.status_publikasi,
    y.keterangan_hasil_yudisium, COALESCE(y.status_lulus, 'Belum') AS status_yudisium,
    b.kategori_pekerjaan, b.posisi_jabatan, b.bidang_pekerjaan, b.divisi, b.posisi_wiraswasta, b.pendidikan_tingkat,
    b.perguruan_tinggi, b.pendidikan_prodi, b.gaji, b.jenis_pekerjaan, b.perusahaan_id,
    c.nama_perusahaan, c.alamat AS alamat_perusahaan, c.sektor AS sektor_perusahaan, c.skala AS skala_perusahaan,
    c.jenis_perusahaan, c.jenis_lokasi AS jenis_lokasi_perusahaan, c.kode_negara AS kode_negara_perusahaan,
    rn.nama_negara AS nama_negara_perusahaan,
    a.nama AS nama_atasan, a.email AS email_atasan, a.telepon AS telepon_atasan,
    CASE WHEN COALESCE(da.nama, u.name) IS NOT NULL AND b.nim IS NOT NULL
        AND b.nomor_telepon IS NOT NULL AND COALESCE(b.email_pribadi, u.email) IS NOT NULL
        THEN 1 ELSE 0 END AS is_profile_complete
FROM biodata b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN data_akademik da ON da.nim = b.nim
LEFT JOIN ref_prodi p ON b.kode_prodi = p.kode_prodi
LEFT JOIN ref_fakultas f ON p.kode_fakultas = f.kode_fakultas
LEFT JOIN propinsi prov ON b.kode_provinsi = prov.kode_provinsi
LEFT JOIN kabupaten kab ON b.kode_kabupaten = kab.kode_kabupaten
LEFT JOIN perusahaan c ON b.perusahaan_id = c.id
LEFT JOIN ref_negara rn ON c.kode_negara = rn.kode_negara
LEFT JOIN atasan a ON b.atasan_id = a.id
LEFT JOIN yudisium y ON y.nim = b.nim
SQL);

        DB::statement('DROP VIEW IF EXISTS v_analytics_population');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_population AS
SELECT
    biodata.id AS biodata_id,
    biodata.user_id,
    biodata.nim,
    COALESCE(academic.nama, users.name, 'Mahasiswa UKDW') AS nama,
    biodata.kode_prodi,
    study_program.nama_prodi,
    academic.tahun_lulus,
    academic.ip_kumulatif AS ipk,
    biodata.gaji,
    biodata.nomor_telepon,
    biodata.linkedin_url,
    COALESCE(biodata.email_pribadi, users.email) AS email,
    CASE
        WHEN COALESCE(academic.nama, users.name) IS NOT NULL
            AND biodata.nim IS NOT NULL
            AND biodata.nomor_telepon IS NOT NULL
            AND COALESCE(biodata.email_pribadi, users.email) IS NOT NULL
        THEN 1
        ELSE 0
    END AS is_profile_complete,
    company.nama_perusahaan,
    biodata.perusahaan_id,
    company.jenis_lokasi AS jenis_lokasi_perusahaan,
    company.kode_negara AS kode_negara_perusahaan,
    country.nama_negara AS nama_negara_perusahaan,
    company.kode_provinsi AS workplace_kode_provinsi,
    company.kode_kabupaten AS workplace_kode_kabupaten,
    workplace_province.nama_provinsi AS workplace_nama_provinsi
FROM biodata
LEFT JOIN users ON users.id = biodata.user_id
LEFT JOIN data_akademik AS academic ON academic.nim = biodata.nim
LEFT JOIN ref_prodi AS study_program ON study_program.kode_prodi = biodata.kode_prodi
LEFT JOIN perusahaan AS company ON company.id = biodata.perusahaan_id
LEFT JOIN ref_negara AS country ON country.kode_negara = company.kode_negara
LEFT JOIN propinsi AS workplace_province ON workplace_province.kode_provinsi = company.kode_provinsi
SQL);

        DB::statement('DROP VIEW IF EXISTS v_analytics_tracer_answers');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_tracer_answers AS
SELECT
    population.biodata_id,
    population.user_id,
    population.nim,
    population.nama,
    population.kode_prodi,
    population.nama_prodi,
    population.tahun_lulus,
    population.ipk,
    p.kode_pertanyaan,
    s.kode_section AS kelompok,
    p.kode_pertanyaan AS subpertanyaan,
    j.jawaban_teks AS answer,
    j.jawaban_json AS answer_json
FROM v_analytics_population AS population
INNER JOIN (
    SELECT
        biodata_id,
        pertanyaan_id,
        MAX(id) AS latest_id
    FROM kuesioner_jawaban
    WHERE responden_type = 'alumni'
      AND biodata_id IS NOT NULL
    GROUP BY biodata_id, pertanyaan_id
) AS latest ON latest.biodata_id = population.biodata_id
INNER JOIN kuesioner_jawaban j ON j.id = latest.latest_id
INNER JOIN kuesioner_pertanyaan AS p ON p.id = j.pertanyaan_id
INNER JOIN kuesioner_section AS s ON s.id = (
    SELECT section_id FROM kuesioner_section_pertanyaan
    WHERE pertanyaan_id = p.id LIMIT 1
)
SQL);

        DB::statement('DROP VIEW IF EXISTS v_kuesioner_rekap_jawaban');
        DB::statement(<<<'SQL'
CREATE VIEW v_kuesioner_rekap_jawaban AS
SELECT
    j.id AS jawaban_id,
    j.responden_type,
    s.target AS target_kuesioner,
    s.kode_section,
    s.judul AS judul_section,
    COALESCE(j.kode_pertanyaan, p.kode_pertanyaan) AS kode_pertanyaan,
    p.teks_pertanyaan,
    p.tipe AS tipe_pertanyaan,
    COALESCE(j.kode_opsi, o.kode_opsi) AS kode_opsi,
    o.label AS label_opsi,
    o.label_singkat AS label_opsi_singkat,
    o.nilai_skor,
    j.jawaban_teks,
    j.jawaban_json,
    b.nim,
    da.nama AS nama_alumni,
    pr.nama_prodi,
    at.nama AS nama_atasan,
    pt.nama_perusahaan,
    j.created_at AS waktu_jawab
FROM kuesioner_jawaban j
JOIN kuesioner_section s ON j.section_id = s.id
JOIN kuesioner_pertanyaan p ON j.pertanyaan_id = p.id
LEFT JOIN kuesioner_opsi o ON j.opsi_id = o.id
LEFT JOIN biodata b ON j.biodata_id = b.id
LEFT JOIN data_akademik da ON b.nim = da.nim
LEFT JOIN ref_prodi pr ON b.kode_prodi = pr.kode_prodi
LEFT JOIN evaluasi_atasan ea ON j.evaluasi_atasan_id = ea.id
LEFT JOIN atasan at ON ea.atasan_id = at.id
LEFT JOIN perusahaan pt ON ea.perusahaan_id = pt.id
SQL);

        DB::statement('DROP VIEW IF EXISTS v_iku_s1_lulusan');
        DB::statement(<<<'SQL'
CREATE VIEW v_iku_s1_lulusan AS
SELECT
    academic.nim AS academic_id,
    academic.nim,
    academic.nama,
    academic.kode_prodi,
    study_program.nama_prodi,
    study_program.jenjang,
    academic.tahun_lulus,
    academic.tanggal_lulus,
    biodata.id AS biodata_id,
    biodata.kategori_pekerjaan,
    biodata.posisi_wiraswasta,
    biodata.gaji,
    company.kode_provinsi,
    COALESCE(
        biodata.kategori_pekerjaan,
        (SELECT answer.jawaban_teks FROM kuesioner_jawaban AS answer WHERE answer.biodata_id = biodata.id AND answer.responden_type = 'alumni' AND answer.kode_pertanyaan = 'F8' ORDER BY answer.id DESC LIMIT 1)
    ) AS status_karier,
    (SELECT answer.jawaban_teks FROM kuesioner_jawaban AS answer WHERE answer.biodata_id = biodata.id AND answer.responden_type = 'alumni' AND answer.kode_pertanyaan = 'F504' ORDER BY answer.id DESC LIMIT 1) AS bekerja_sebelum_lulus,
    (SELECT answer.jawaban_teks FROM kuesioner_jawaban AS answer WHERE answer.biodata_id = biodata.id AND answer.responden_type = 'alumni' AND answer.kode_pertanyaan = 'F502' ORDER BY answer.id DESC LIMIT 1) AS bulan_sebelum_lulus,
    (SELECT answer.jawaban_teks FROM kuesioner_jawaban AS answer WHERE answer.biodata_id = biodata.id AND answer.responden_type = 'alumni' AND answer.kode_pertanyaan = 'F506' ORDER BY answer.id DESC LIMIT 1) AS bulan_setelah_lulus,
    (SELECT answer.jawaban_teks FROM kuesioner_jawaban AS answer WHERE answer.biodata_id = biodata.id AND answer.responden_type = 'alumni' AND answer.kode_pertanyaan = 'F18d' ORDER BY answer.id DESC LIMIT 1) AS tanggal_mulai_studi,
    (SELECT MAX(answer.updated_at) FROM kuesioner_jawaban AS answer WHERE answer.biodata_id = biodata.id AND answer.responden_type = 'alumni') AS last_tracer_at
FROM data_akademik AS academic
INNER JOIN ref_prodi AS study_program ON study_program.kode_prodi = academic.kode_prodi
LEFT JOIN biodata ON biodata.nim = academic.nim
LEFT JOIN perusahaan AS company ON company.id = biodata.perusahaan_id
WHERE academic.status_mahasiswa = 'Lulus'
  AND study_program.jenjang = 'S1'
SQL);

        DB::statement('DROP VIEW IF EXISTS v_iku_s1_aee');
        if (! $isMySQL) {
            DB::statement(<<<'SQL'
CREATE VIEW v_iku_s1_aee AS
SELECT
    academic.nim AS academic_id,
    academic.nim,
    academic.nama,
    academic.kode_prodi,
    study_program.nama_prodi,
    study_program.jenjang,
    COALESCE(academic.angkatan_masuk, CAST('20' || SUBSTR(academic.nim, 3, 2) AS INTEGER)) AS angkatan_masuk,
    COALESCE(
        academic.tahun_akademik_masuk,
        COALESCE(academic.angkatan_masuk, CAST('20' || SUBSTR(academic.nim, 3, 2) AS INTEGER)) || '/' || (COALESCE(academic.angkatan_masuk, CAST('20' || SUBSTR(academic.nim, 3, 2) AS INTEGER)) + 1) || ' (Semester Ganjil)'
    ) AS tahun_akademik_masuk,
    academic.status_mahasiswa,
    academic.tahun_akademik_lulus,
    academic.tahun_lulus,
    CASE WHEN academic.status_mahasiswa IN ('Drop Out', 'Pindah', 'Cuti') THEN 1 ELSE 0 END AS is_excluded,
    CASE
        WHEN academic.status_mahasiswa = 'Lulus'
            AND (
                academic.tahun_akademik_lulus = 'Genap ' || (academic.angkatan_masuk + 3) || '/' || (academic.angkatan_masuk + 4)
                OR academic.tahun_akademik_lulus = 'Ganjil ' || (academic.angkatan_masuk + 3) || '/' || (academic.angkatan_masuk + 4)
                OR (academic.tahun_lulus - academic.angkatan_masuk) < 4
            )
        THEN 1 ELSE 0
    END AS is_lulus_tepat_waktu
FROM data_akademik AS academic
INNER JOIN ref_prodi AS study_program ON study_program.kode_prodi = academic.kode_prodi
WHERE study_program.jenjang = 'S1'
  AND academic.status_mahasiswa = 'Lulus'
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW v_iku_s1_aee AS
SELECT
    academic.nim AS academic_id,
    academic.nim,
    academic.nama,
    academic.kode_prodi,
    study_program.nama_prodi,
    study_program.jenjang,
    COALESCE(academic.angkatan_masuk, CAST(CONCAT('20', SUBSTRING(academic.nim, 3, 2)) AS UNSIGNED)) AS angkatan_masuk,
    COALESCE(
        academic.tahun_akademik_masuk,
        CONCAT(
            COALESCE(academic.angkatan_masuk, CAST(CONCAT('20', SUBSTRING(academic.nim, 3, 2)) AS UNSIGNED)),
            '/',
            COALESCE(academic.angkatan_masuk, CAST(CONCAT('20', SUBSTRING(academic.nim, 3, 2)) AS UNSIGNED)) + 1,
            ' (Semester Ganjil)'
        )
    ) AS tahun_akademik_masuk,
    academic.status_mahasiswa,
    academic.tahun_akademik_lulus,
    academic.tahun_lulus,
    CASE WHEN academic.status_mahasiswa IN ('Drop Out', 'Pindah', 'Cuti') THEN 1 ELSE 0 END AS is_excluded,
    CASE
        WHEN academic.status_mahasiswa = 'Lulus'
            AND (
                academic.tahun_akademik_lulus = CONCAT('Genap ', academic.angkatan_masuk + 3, '/', academic.angkatan_masuk + 4)
                OR academic.tahun_akademik_lulus = CONCAT('Ganjil ', academic.angkatan_masuk + 3, '/', academic.angkatan_masuk + 4)
                OR (academic.tahun_lulus - academic.angkatan_masuk) < 4
            )
        THEN 1 ELSE 0
    END AS is_lulus_tepat_waktu
FROM data_akademik AS academic
INNER JOIN ref_prodi AS study_program ON study_program.kode_prodi = academic.kode_prodi
WHERE study_program.jenjang = 'S1'
  AND academic.status_mahasiswa = 'Lulus'
SQL
            );
        }

        // Re-eksekusi seluruh view turunan yang ter-invalidasi oleh perubahan view induk di MySQL
        $dependentMigrationFiles = [
            '2026_09_02_000004_create_v_alumni_tracer_prodi_status_view.php',
            '2026_09_02_000006_create_v_alumni_audit_rekap_view.php',
            '2026_09_02_000002_create_v_alumni_linkedin_plot_view.php',
            '2026_10_25_000002_create_v_analytics_response_rate_view.php',
            '2026_10_25_000003_create_v_analytics_profile_completion_view.php',
            '2026_10_25_000004_create_v_analytics_wait_time_view.php',
            '2026_10_25_000005_create_v_analytics_salary_ump_view.php',
            '2026_10_25_000006_create_v_analytics_work_location_view.php',
            '2026_10_25_000007_create_v_analytics_employment_status_view.php',
            '2026_10_25_000008_create_v_analytics_alignment_view.php',
            '2026_10_25_000011_create_v_analytics_learning_methods_view.php',
            '2026_10_25_000012_create_v_analytics_job_search_start_view.php',
            '2026_10_25_000013_create_v_analytics_job_search_methods_view.php',
            '2026_10_25_000014_create_v_analytics_application_pipeline_view.php',
            '2026_10_25_000015_create_v_analytics_job_search_activity_view.php',
            '2026_10_25_000016_create_v_analytics_workplace_type_view.php',
            '2026_10_25_000017_create_v_analytics_further_study_view.php',
            '2026_10_25_000018_create_v_analytics_mismatch_reasons_view.php',
            '2026_10_25_000019_create_v_analytics_competency_gap_view.php',
        ];

        foreach ($dependentMigrationFiles as $file) {
            $migration = require database_path("migrations/{$file}");
            $migration->up();
        }
    }

    public function down(): void {}
};
