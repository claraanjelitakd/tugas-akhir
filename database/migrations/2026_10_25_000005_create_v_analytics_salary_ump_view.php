<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view perbandingan gaji dengan UMP per provinsi dan tahun.
 */
return new class extends Migration
{
    /**
     * Membuat satu baris per alumni dan tahun UMP yang tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_salary_ump');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_salary_ump AS
SELECT
    population.*,
    ump.tahun AS ump_year,
    ump.besaran AS ump,
    CASE WHEN ump.besaran IS NULL THEN NULL ELSE ROUND(ump.besaran * 1.2, 2) END AS threshold,
    CASE
        WHEN population.gaji IS NULL OR population.gaji <= 0 OR ump.besaran IS NULL THEN NULL
        WHEN population.gaji >= ROUND(ump.besaran * 1.2, 2) THEN 1
        ELSE 0
    END AS meets_ump
FROM v_analytics_population AS population
LEFT JOIN ump ON ump.kode_provinsi = population.workplace_province_code
SQL);
    }

    /**
     * Menghapus view gaji dan UMP saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_salary_ump');
    }
};
