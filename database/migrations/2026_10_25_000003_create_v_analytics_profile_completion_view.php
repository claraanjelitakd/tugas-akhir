<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view status kelengkapan profil alumni.
 */
return new class extends Migration
{
    /**
     * Membuat proyeksi kelengkapan profil dari view populasi.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_profile_completion');
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_profile_completion AS
SELECT
    population.*,
    CASE WHEN population.is_profile_complete = 1 THEN 'Lengkap' ELSE 'Belum lengkap' END AS profile_status
FROM v_analytics_population AS population
SQL);
    }

    /**
     * Menghapus view kelengkapan profil saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_profile_completion');
    }
};
