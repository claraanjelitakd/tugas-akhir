<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan status buka/tutup permintaan pembuatan akun dari Data Akademik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel pengaturan sinkronisasi akun dan status default terbuka.
     */
    public function up(): void
    {
        Schema::create('account_sync_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_open')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('account_sync_settings')->insert([
            'id' => 1,
            'is_open' => true,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Menghapus tabel pengaturan sinkronisasi akun.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_sync_settings');
    }
};
