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
    CASE WHEN respondents.biodata_id IS NOT NULL THEN 1 ELSE 0 END AS answered,
    respondents.last_response_at
FROM v_analytics_population AS population
LEFT JOIN (
    SELECT
        j.biodata_id,
        MAX(j.updated_at) AS last_response_at
    FROM kuesioner_jawaban j
    INNER JOIN kuesioner_pertanyaan p ON p.id = j.pertanyaan_id
    INNER JOIN kuesioner_section s ON s.id = j.section_id
    INNER JOIN kuesioner_section_pertanyaan sp ON sp.pertanyaan_id = p.id
    WHERE s.target = 'univ'
      AND (
        (
            sp.wajib = 1
            AND p.tipe != 'header'
            AND UPPER(p.kode_pertanyaan) NOT IN (
                'F1', 'F2A', 'F2B', 'F2C', 'F2D',
                'BIO_TEMPAT_LAHIR', 'BIO_TANGGAL_LAHIR', 'BIO_JK',
                'BIO_TGL_LULUS', 'BIO_JUDUL_TA',
                'F5A1', 'F5A2', 'F5B', 'F5C', 'F5D', 'F510',
                'F2E', 'F2E1', 'F2E2', 'F2E3', 'F2F', 'F2G', 'F2H',
                'F11', 'F505', 'F502', 'F506'
            )
        )
        OR UPPER(p.kode_pertanyaan) IN ('F502', 'F506')
      )
      AND (
        (j.jawaban_teks IS NOT NULL AND TRIM(j.jawaban_teks) != '')
        OR (j.jawaban_json IS NOT NULL AND j.jawaban_json NOT IN ('', '[]', '{}'))
      )
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
