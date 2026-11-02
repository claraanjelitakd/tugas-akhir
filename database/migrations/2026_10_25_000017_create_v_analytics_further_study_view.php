<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view pembiayaan dan detail studi lanjut F12/F18.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator studi lanjut. */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE VIEW v_analytics_further_study AS
SELECT *
FROM v_analytics_tracer_answers
WHERE kode_pertanyaan IN ('F12', 'F18b', 'F18c', 'F18d')
SQL);
    }

    /** Menghapus view studi lanjut. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_further_study');
    }
};
