<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view jawaban metode pembelajaran F21 sampai F27.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator metode pembelajaran. */
    public function up(): void
    {
        DB::statement('CREATE VIEW v_analytics_learning_methods AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan IN (\'F21\', \'F22\', \'F23\', \'F24\', \'F25\', \'F26\', \'F27\')');
    }

    /** Menghapus view metode pembelajaran. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_learning_methods');
    }
};
