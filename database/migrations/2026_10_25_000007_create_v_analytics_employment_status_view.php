<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view status pekerjaan dari jawaban F8 terbaru.
 */
return new class extends Migration
{
    /**
     * Membuat satu jawaban F8 kanonik per alumni.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_employment_status');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_employment_status AS
SELECT
    population.*,
    f8.answer AS f8_answer
FROM v_analytics_population AS population
LEFT JOIN (
    SELECT tracer.biodata_id, tracer.answer
    FROM tracer
    WHERE tracer.kode_pertanyaan = 'F8'
) AS f8 ON f8.biodata_id = population.biodata_id
SQL);
    }

    /**
     * Menghapus view status pekerjaan saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_employment_status');
    }
};
