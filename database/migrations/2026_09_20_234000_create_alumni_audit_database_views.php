<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Riwayat pembuatan view audit.
 *
 * Implementasi view aktif dipusatkan pada migrasi kontrak schema terakhir agar
 * fresh install dan upgrade database memakai definisi yang sama.
 */
return new class extends Migration
{
    /**
     * View dibuat oleh migrasi kontrak schema kanonik.
     */
    public function up(): void
    {
        //
    }

    /**
     * Tidak menghapus view aktif yang dikelola migrasi kontrak terbaru.
     */
    public function down(): void
    {
        //
    }
};
