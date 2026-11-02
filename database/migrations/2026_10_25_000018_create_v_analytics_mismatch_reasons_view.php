<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view alasan pekerjaan tidak sesuai pendidikan F16.
 */
return new class extends Migration
{
    /** Membuat proyeksi khusus indikator alasan ketidaksesuaian. */
    public function up(): void
    {
        DB::statement('CREATE VIEW v_analytics_mismatch_reasons AS SELECT * FROM v_analytics_tracer_answers WHERE kode_pertanyaan = \'F16\'');
    }

    /** Menghapus view alasan ketidaksesuaian. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_analytics_mismatch_reasons');
    }
};
