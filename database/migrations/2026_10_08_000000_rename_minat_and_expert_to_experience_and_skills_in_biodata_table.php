<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Riwayat penggantian alias legacy karier.
 *
 * Schema awal saat ini langsung memakai experience dan skills; file ini
 * dipertahankan sebagai penanda histori migrasi tanpa mengulang DDL lama.
 */
return new class extends Migration
{
    /**
     * Tidak ada perubahan tambahan pada schema aktif.
     */
    public function up(): void
    {
        //
    }

    /**
     * Tidak mengembalikan alias legacy yang sudah dihentikan.
     */
    public function down(): void
    {
        //
    }
};
