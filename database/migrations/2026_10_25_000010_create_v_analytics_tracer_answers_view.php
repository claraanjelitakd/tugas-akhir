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
     * Tabel tracer sudah menjamin keunikan pasangan biodata dan kode melalui
     * unique key sehingga tidak diperlukan agregasi MAX(id) pada setiap baca.
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
    tracer.kode_pertanyaan,
    tracer.kelompok,
    tracer.subpertanyaan,
    tracer.answer,
    tracer.answer_json
FROM v_analytics_population AS population
INNER JOIN tracer ON tracer.biodata_id = population.biodata_id
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
