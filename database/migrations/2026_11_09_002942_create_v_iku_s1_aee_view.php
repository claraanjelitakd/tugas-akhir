<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat read-model populasi AEE S1 dari seluruh data akademik.
 */
return new class extends Migration
{
    /**
     * Membuat view AEE dengan penanda pengecualian dan kelulusan tepat waktu.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_iku_s1_aee');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_iku_s1_aee AS
SELECT
    academic.id AS academic_id,
    academic.nim,
    academic.nama,
    academic.prodi_id,
    study_program.kode_prodi,
    study_program.nama_prodi,
    study_program.jenjang,
    academic.angkatan_masuk,
    academic.tahun_akademik_masuk,
    academic.status_mahasiswa,
    academic.tahun_akademik_lulus,
    academic.tahun_lulus,
    CASE WHEN academic.status_mahasiswa IN ('Drop Out', 'Pindah', 'Cuti') THEN 1 ELSE 0 END AS is_excluded,
    CASE
        WHEN academic.status_mahasiswa = 'Lulus'
            AND academic.tahun_akademik_lulus = 'Genap ' || (academic.angkatan_masuk + 3) || '/' || (academic.angkatan_masuk + 4)
        THEN 1 ELSE 0
    END AS is_lulus_tepat_waktu
FROM data_akademik AS academic
INNER JOIN prodi AS study_program ON study_program.id = academic.prodi_id
WHERE study_program.jenjang = 'S1'
  AND academic.status_mahasiswa = 'Lulus'
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW v_iku_s1_aee AS
SELECT
    academic.id AS academic_id,
    academic.nim,
    academic.nama,
    academic.prodi_id,
    study_program.kode_prodi,
    study_program.nama_prodi,
    study_program.jenjang,
    academic.angkatan_masuk,
    academic.tahun_akademik_masuk,
    academic.status_mahasiswa,
    academic.tahun_akademik_lulus,
    academic.tahun_lulus,
    CASE WHEN academic.status_mahasiswa IN ('Drop Out', 'Pindah', 'Cuti') THEN 1 ELSE 0 END AS is_excluded,
    CASE
        WHEN academic.status_mahasiswa = 'Lulus'
            AND academic.tahun_akademik_lulus = CONCAT('Genap ', academic.angkatan_masuk + 3, '/', academic.angkatan_masuk + 4)
        THEN 1 ELSE 0
    END AS is_lulus_tepat_waktu
FROM data_akademik AS academic
INNER JOIN prodi AS study_program ON study_program.id = academic.prodi_id
WHERE study_program.jenjang = 'S1'
  AND academic.status_mahasiswa = 'Lulus'
SQL
            );
        }
    }

    /**
     * Menghapus view AEE saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_iku_s1_aee');
    }
};
