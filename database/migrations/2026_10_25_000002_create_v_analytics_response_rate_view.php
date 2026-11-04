<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view response rate dengan status responden per alumni.
 */
return new class extends Migration
{
    /**
     * Membuat proyeksi response rate dari populasi analitik.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_response_rate');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_response_rate AS
SELECT
    population.*,
    COALESCE(tus.answered_mandatory_univ, 0) AS answered_mandatory_univ,
    CASE WHEN COALESCE(tus.total_mandatory_univ, 0) > 0 AND COALESCE(tus.answered_mandatory_univ, 0) >= tus.total_mandatory_univ THEN 1 ELSE 0 END AS answered,
    respondents.last_response_at
FROM v_analytics_population AS population
LEFT JOIN v_alumni_tracer_univ_status AS tus ON tus.biodata_id = population.biodata_id
LEFT JOIN (
    SELECT
        j.biodata_id,
        MAX(j.updated_at) AS last_response_at
    FROM kuesioner_jawaban j
    INNER JOIN kuesioner_section s ON s.id = j.section_id
    WHERE s.target = 'univ'
    GROUP BY j.biodata_id
) AS respondents ON respondents.biodata_id = population.biodata_id
SQL);
    }

    /**
     * Menghapus view response rate saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_response_rate');
    }
};
