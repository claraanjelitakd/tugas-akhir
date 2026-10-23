<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE biodata COMMENT = 'Profil tracer alumni; alamat_saat_ini, skills, dan experience adalah field kanonik. Data NIK, NPWP, dan relasi orang tua telah dihapus karena tidak digunakan.'");
            DB::statement("ALTER TABLE data_akademik COMMENT = 'Sumber resmi identitas akademik dan kelulusan. NISN dan BPJS dipertahankan; NIK, No KK, dan NPWP dihapus karena tidak digunakan.'");
            DB::statement("ALTER TABLE yudisium COMMENT = 'Sumber resmi kelulusan, tugas akhir, publikasi, dan predikat. Data pembimbing/penguji dihapus karena tidak digunakan tracer.'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE biodata COMMENT = 'Profil aktif tracer alumni.'");
            DB::statement("ALTER TABLE data_akademik COMMENT = 'Sumber resmi data akademik.'");
            DB::statement("ALTER TABLE yudisium COMMENT = 'Sumber resmi kelulusan dan tugas akhir.'");
        }
    }
};
