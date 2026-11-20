<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'ref_prodi' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'ref_prodi' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "ref_prodi" ("kode_prodi" varchar primary key not null, "kode_fakultas" varchar, "nama_prodi" varchar not null, "jenjang" varchar not null default 'S1', "created_at" datetime, "updated_at" datetime, foreign key("kode_fakultas") references "ref_fakultas"("kode_fakultas") on delete set null);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `ref_prodi` (
  `kode_prodi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode resmi program studi (misal: 71, 72)',
  `kode_fakultas` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode fakultas (FK ke ref_fakultas)',
  `nama_prodi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama resmi program studi (misal: Informatika)',
  `jenjang` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'S1' COMMENT 'Jenjang pendidikan program studi untuk perhitungan IKU',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`kode_prodi`),
  KEY `ref_prodi_kode_fakultas_foreign` (`kode_fakultas`),
  CONSTRAINT `ref_prodi_kode_fakultas_foreign` FOREIGN KEY (`kode_fakultas`) REFERENCES `ref_fakultas` (`kode_fakultas`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('kode_prodi', 'users_kode_prodi_foreign')
                ->references('kode_prodi')
                ->on('ref_prodi')
                ->nullOnDelete();
            $table->foreign('kode_fakultas', 'users_kode_fakultas_foreign')
                ->references('kode_fakultas')
                ->on('ref_fakultas')
                ->nullOnDelete();
        });

        Schema::table('data_akademik', function (Blueprint $table): void {
            $table->foreign('kode_prodi', 'data_akademik_kode_prodi_foreign')
                ->references('kode_prodi')
                ->on('ref_prodi')
                ->nullOnDelete();
        });
    }

    /**
     * Menghapus tabel 'ref_prodi' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_prodi');
    }
};
