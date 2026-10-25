<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view cara alumni mencari pekerjaan F4.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator cara mencari kerja. */
    public function up(): void
    {
        DB::statement('CREATE VIEW v_analytics_job_search_methods AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan = \'F4\'');
    }

    /** Menghapus view cara mencari kerja. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_job_search_methods');
    }
};
