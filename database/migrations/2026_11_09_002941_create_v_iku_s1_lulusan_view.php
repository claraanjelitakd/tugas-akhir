<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat read-model lulusan S1 untuk kalkulasi bobot IKU karier.
 */
return new class extends Migration
{
    /**
     * Membuat satu baris per lulusan S1 dengan jawaban tracer yang relevan.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_iku_s1_lulusan');
        DB::statement(<<<'SQL'
CREATE VIEW v_iku_s1_lulusan AS
SELECT
    academic.id AS academic_id,
    academic.nim,
    academic.nama,
    academic.prodi_id,
    study_program.kode_prodi,
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
INNER JOIN prodi AS study_program ON study_program.id = academic.prodi_id
LEFT JOIN biodata ON biodata.nim = academic.nim
LEFT JOIN perusahaan AS company ON company.id = biodata.perusahaan_id
WHERE academic.status_mahasiswa = 'Lulus'
  AND study_program.jenjang = 'S1'
SQL
        );
    }

    /**
     * Menghapus view lulusan S1 saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_iku_s1_lulusan');
    }
};
