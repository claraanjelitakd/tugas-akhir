<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Riwayat keputusan penghapusan field sensitif dan relasi orang tua.
 *
 * Implementasi upgrade yang aman, backfill, dan pembuatan ulang view berada
 * pada migrasi kontrak schema kanonik setelah file histori ini.
 */
return new class extends Migration
{
    /**
     * Tidak mengulang perubahan yang sudah dipusatkan pada migrasi final.
     */
    public function up(): void
    {
        //
    }

    /**
     * Tidak mengembalikan field yang sengaja dihapus.
     */
    public function down(): void
    {
        //
    }
};
