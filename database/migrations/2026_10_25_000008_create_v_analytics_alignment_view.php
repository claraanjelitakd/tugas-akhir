<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view jawaban keselarasan F14 dan F15.
 */
return new class extends Migration
{
    /**
     * Membuat dua baris indikator per alumni untuk F14 dan F15.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_alignment');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_alignment AS
SELECT population.*, 'F14' AS indicator_code, f14.answer AS answer_value
FROM v_analytics_population AS population
LEFT JOIN (
    SELECT tracer.biodata_id, tracer.answer
    FROM tracer
    WHERE tracer.kode_pertanyaan = 'F14'
) AS f14 ON f14.biodata_id = population.biodata_id
UNION ALL
SELECT population.*, 'F15' AS indicator_code, f15.answer AS answer_value
FROM v_analytics_population AS population
LEFT JOIN (
    SELECT tracer.biodata_id, tracer.answer
    FROM tracer
    WHERE tracer.kode_pertanyaan = 'F15'
) AS f15 ON f15.biodata_id = population.biodata_id
SQL);
    }

    /**
     * Menghapus view keselarasan saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_alignment');
    }
};
