<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view jawaban tracer kanonik untuk indikator F2 sampai F18.
 */
return new class extends Migration
{
    /**
     * Membuat satu baris jawaban per alumni dan kode pertanyaan.
     *
     * Jawaban tracer diambil dari tabel kanonik `kuesioner_jawaban` dan
     * dihubungkan ke master pertanyaan melalui `pertanyaan_id`.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_tracer_answers');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_tracer_answers AS
SELECT
    population.biodata_id,
    population.user_id,
    population.nim,
    population.nama,
    population.prodi_id,
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
INNER JOIN kuesioner_pertanyaan p ON p.id = j.pertanyaan_id
INNER JOIN kuesioner_section s ON s.id = j.section_id
WHERE s.target = 'univ'
  AND j.responden_type = 'alumni';
SQL);
    }

    /**
     * Menghapus view jawaban tracer saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_tracer_answers');
    }
};
