<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'kabupaten' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'kabupaten' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kabupaten" ("kode_kabupaten" varchar primary key not null, "kode_provinsi" varchar not null, "nama_kabupaten" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("kode_provinsi") references "propinsi"("kode_provinsi") on delete cascade);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kabupaten` (
  `kode_kabupaten` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode BPS/Kemendagri kabupaten (misal: 34.04)',
  `kode_provinsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_kabupaten` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama resmi kabupaten/kota (misal: Kabupaten Sleman)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`kode_kabupaten`),
  KEY `kabupaten_kode_provinsi_foreign` (`kode_provinsi`),
  CONSTRAINT `kabupaten_kode_provinsi_foreign` FOREIGN KEY (`kode_provinsi`) REFERENCES `propinsi` (`kode_provinsi`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'kabupaten' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('kabupaten');
    }
};
