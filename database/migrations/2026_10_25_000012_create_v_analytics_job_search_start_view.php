<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view waktu mulai mencari pekerjaan F3.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator waktu mulai mencari kerja. */
    public function up(): void
    {
        DB::statement('CREATE VIEW v_analytics_job_search_start AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan = \'F3\'');
    }

    /** Menghapus view waktu mulai mencari kerja. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_job_search_start');
    }
};
