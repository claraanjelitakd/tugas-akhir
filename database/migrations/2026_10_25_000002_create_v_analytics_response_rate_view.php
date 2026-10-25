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
    CASE WHEN respondents.biodata_id IS NOT NULL THEN 1 ELSE 0 END AS answered_mandatory_univ,
    CASE WHEN respondents.biodata_id IS NOT NULL THEN 1 ELSE 0 END AS answered
FROM v_analytics_population AS population
LEFT JOIN (
    SELECT DISTINCT tracer.biodata_id
    FROM tracer
    INNER JOIN ref_subpertanyaan2021 AS questions ON questions.id = tracer.question_id
    WHERE (
        (
            questions.wajib = 1
            AND questions.type != 'header'
            AND UPPER(questions.kode_pertanyaan) NOT IN (
                'F1', 'F2A', 'F2B', 'F2C', 'F2D',
                'BIO_TEMPAT_LAHIR', 'BIO_TANGGAL_LAHIR', 'BIO_JK',
                'BIO_TGL_LULUS', 'BIO_JUDUL_TA',
                'F5A1', 'F5A2', 'F5B', 'F5C', 'F5D', 'F510',
                'F2E', 'F2E1', 'F2E2', 'F2E3', 'F2F', 'F2G', 'F2H',
                'F11', 'F505', 'F502', 'F506'
            )
        )
        OR UPPER(questions.kode_pertanyaan) IN ('F502', 'F506')
    )
        AND (
            (tracer.answer IS NOT NULL AND TRIM(tracer.answer) != '')
            OR (tracer.answer_json IS NOT NULL AND tracer.answer_json NOT IN ('', '[]', '{}'))
        )
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
