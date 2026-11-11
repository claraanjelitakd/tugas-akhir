<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan label ringkas pada master pertanyaan yang sudah berjalan.
 *
 * Label bersifat opsional agar pertanyaan lama tetap valid; payload analitik
 * akan memakai teks pertanyaan sebagai fallback sampai admin mengisinya.
 */
return new class extends Migration
{
    /**
     * Menambahkan kolom label ringkas pada bank pertanyaan terpadu.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('kuesioner_pertanyaan', 'label_pertanyaan')) {
            Schema::table('kuesioner_pertanyaan', function (Blueprint $table): void {
                $table->string('label_pertanyaan', 255)
                    ->nullable()
                    ->after('kode_pertanyaan')
                    ->comment('Label ringkas butir untuk grafik, tab, dan ringkasan analitik');
            });
        }
    }

    /**
     * Menghapus kolom label ringkas saat migrasi dibatalkan.
     */
    public function down(): void
    {
        if (Schema::hasColumn('kuesioner_pertanyaan', 'label_pertanyaan')) {
            Schema::table('kuesioner_pertanyaan', function (Blueprint $table): void {
                $table->dropColumn('label_pertanyaan');
            });
        }
    }
};
