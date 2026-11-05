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
LEFT JOIN prodi AS study_program ON study_program.id = biodata.prodi_id
LEFT JOIN perusahaan AS company ON company.id = biodata.perusahaan_id
LEFT JOIN ref_negara AS country ON country.kode_negara = company.kode_negara
LEFT JOIN propinsi AS workplace_province ON workplace_province.kode_provinsi = company.kode_provinsi
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
