<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view pasangan kompetensi F17a dan F17b.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator gap kompetensi. */
    public function up(): void
    {
        DB::statement("CREATE VIEW v_analytics_competency_gap AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan IN ('F17a1', 'F17b1', 'F17a2', 'F17b2', 'F17a3', 'F17b3', 'F17a4', 'F17b4', 'F17a5', 'F17b5', 'F17a6', 'F17b6', 'F17a7', 'F17b7')");
    }

    /** Menghapus view gap kompetensi. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_competency_gap');
    }
};
