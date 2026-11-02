<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view dasar populasi analitik yang mengambil satu baris per alumni.
 */
return new class extends Migration
{
    /**
     * Membuat sumber kolom bersama untuk seluruh view analitik.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_population');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_population AS
SELECT
    biodata.id AS biodata_id,
    biodata.user_id,
    biodata.nim,
    COALESCE(academic.nama, users.name, 'Mahasiswa UKDW') AS nama,
    biodata.prodi_id,
    study_program.nama_prodi,
    academic.tahun_lulus,
    biodata.gaji,
    biodata.nomor_telepon,
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
    company.negara AS negara_perusahaan,
    company.propinsi_id AS workplace_province_id,
    company.kabupaten_id AS workplace_regency_id,
    workplace_province.kode_provinsi AS workplace_province_code,
    workplace_province.nama_provinsi AS workplace_province_name
FROM biodata
LEFT JOIN users ON users.id = biodata.user_id
LEFT JOIN data_akademik AS academic ON academic.nim = biodata.nim
LEFT JOIN prodi AS study_program ON study_program.id = biodata.prodi_id
LEFT JOIN perusahaan AS company ON company.id = biodata.perusahaan_id
LEFT JOIN propinsi AS workplace_province ON workplace_province.id = company.propinsi_id
SQL);
    }

    /**
     * Menghapus view populasi analitik saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_population');
    }
};
