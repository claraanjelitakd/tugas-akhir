<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view waktu tunggu kerja setelah percabangan F504.
 */
return new class extends Migration
{
    /**
     * Membuat satu baris waktu tunggu per alumni dari F502 atau F506.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_wait_time');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_wait_time AS
SELECT
    population.*,
    COALESCE(
        CAST(NULLIF(f502.answer, '') AS DECIMAL(10,2)),
        CAST(NULLIF(f506.answer, '') AS DECIMAL(10,2))
    ) AS wait_months,
    CASE
        WHEN NULLIF(f502.answer, '') IS NOT NULL THEN 'F502'
        WHEN NULLIF(f506.answer, '') IS NOT NULL THEN 'F506'
        ELSE NULL
    END AS wait_source
FROM v_analytics_population AS population
LEFT JOIN (
    SELECT tracer.biodata_id, tracer.answer
    FROM tracer
    WHERE tracer.kode_pertanyaan = 'F502'
) AS f502 ON f502.biodata_id = population.biodata_id
LEFT JOIN (
    SELECT tracer.biodata_id, tracer.answer
    FROM tracer
    WHERE tracer.kode_pertanyaan = 'F506'
) AS f506 ON f506.biodata_id = population.biodata_id
SQL);
    }

    /**
     * Menghapus view waktu tunggu saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_wait_time');
    }
};
