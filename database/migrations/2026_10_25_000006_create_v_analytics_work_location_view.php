<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view lokasi kerja berdasarkan provinsi perusahaan.
 */
return new class extends Migration
{
    /**
     * Membuat proyeksi lokasi kerja untuk peta dan tabel detail.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_work_location');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_work_location AS
SELECT
    population.*,
    CASE WHEN population.workplace_province_code IS NULL THEN 0 ELSE 1 END AS has_work_location
FROM v_analytics_population AS population
SQL);
    }

    /**
     * Menghapus view lokasi kerja saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_work_location');
    }
};
