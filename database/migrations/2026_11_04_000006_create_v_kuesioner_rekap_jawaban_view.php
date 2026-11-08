<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat Database View 'v_kuesioner_rekap_jawaban'.
 * Menyajikan data jawaban kuesioner terpadu secara denormalisasi dengan kode pertanyaan,
 * teks pertanyaan, label opsi, skor, dan identitas responden (alumni/atasan) tanpa perlu JOIN manual.
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi pembuatan database view v_kuesioner_rekap_jawaban.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS `v_kuesioner_rekap_jawaban`;');

        $sql = <<<'SQL'
CREATE VIEW `v_kuesioner_rekap_jawaban` AS
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
LEFT JOIN prodi pr ON b.prodi_id = pr.id
LEFT JOIN evaluasi_atasan ea ON j.evaluasi_atasan_id = ea.id
LEFT JOIN atasan at ON ea.atasan_id = at.id
LEFT JOIN perusahaan pt ON ea.perusahaan_id = pt.id;
SQL;

        DB::statement($sql);
    }

    /**
     * Batalkan migrasi dengan menghapus database view.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `v_kuesioner_rekap_jawaban`;');
    }
};
