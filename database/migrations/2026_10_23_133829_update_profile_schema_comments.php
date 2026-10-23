<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memberi komentar schema aktif agar ownership field mudah dibaca dari database.
 */
return new class extends Migration
{
    /**
     * Menetapkan komentar tabel pada database yang mendukung table comment.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE biodata COMMENT = 'Profil tracer alumni; kontak, domisili, skills, dan experience adalah field aktif.'");
            DB::statement("ALTER TABLE data_akademik COMMENT = 'Sumber resmi identitas akademik dan kelulusan.'");
            DB::statement("ALTER TABLE yudisium COMMENT = 'Sumber resmi kelulusan, tugas akhir, publikasi, dan predikat.'");
        }
    }

    /**
     * Mengembalikan komentar umum tanpa mengubah struktur atau data.
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
