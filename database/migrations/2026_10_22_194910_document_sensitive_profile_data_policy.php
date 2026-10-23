<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Riwayat dokumentasi kebijakan data sensitif.
 *
 * Kebijakan final diwujudkan pada schema dan dokumentasi saat ini. Migrasi ini
 * tidak lagi membuat kolom sensitif atau menyimpan metadata kebijakan tambahan.
 */
return new class extends Migration
{
    /**
     * Tidak ada DDL policy terpisah.
     */
    public function up(): void
    {
        //
    }

    /**
     * Tidak ada metadata policy yang perlu dihapus.
     */
    public function down(): void
    {
        //
    }
};
