<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view aktivitas mencari pekerjaan F10.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator aktivitas pencarian kerja. */
    public function up(): void
    {
        DB::statement('CREATE VIEW v_analytics_job_search_activity AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan = \'F10\'');
    }

    /** Menghapus view aktivitas pencarian kerja. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_job_search_activity');
    }
};
