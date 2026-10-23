<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Riwayat perbaikan status kuesioner prodi.
 *
 * Definisi final view audit sekarang dikelola satu migrasi kontrak schema.
 */
return new class extends Migration
{
    /**
     * Tidak ada DDL terpisah.
     */
    public function up(): void
    {
        //
    }

    /**
     * Tidak mengubah view aktif saat rollback histori.
     */
    public function down(): void
    {
        //
    }
};
