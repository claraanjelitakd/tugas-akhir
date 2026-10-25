<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view jenis tempat kerja F11.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator jenis tempat kerja. */
    public function up(): void
    {
        DB::statement('CREATE VIEW v_analytics_workplace_type AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan = \'F11\'');
    }

    /** Menghapus view jenis tempat kerja. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_workplace_type');
    }
};
