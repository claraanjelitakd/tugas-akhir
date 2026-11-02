<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view pipeline lamaran, respons, dan wawancara.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus F6, F7, dan F7A. */
    public function up(): void
    {
        DB::statement('CREATE VIEW v_analytics_application_pipeline AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan IN (\'F6\', \'F7\', \'F7A\')');
    }

    /** Menghapus view pipeline lamaran. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_application_pipeline');
    }
};
