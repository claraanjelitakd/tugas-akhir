<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Riwayat peningkatan view profil dan audit.
 *
 * Definisi view aktif dipusatkan pada migrasi kontrak schema kanonik agar
 * perubahan field tidak tersebar di banyak migrasi pembentuk view.
 */
return new class extends Migration
{
    /**
     * View aktif dibuat ulang oleh migrasi kontrak schema terakhir.
     */
    public function up(): void
    {
        //
    }

    /**
     * Tidak menghapus view aktif.
     */
    public function down(): void
    {
        //
    }
};
