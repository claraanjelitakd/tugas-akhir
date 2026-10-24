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
CREATE TABLE "kabupaten" ("id" integer primary key autoincrement not null, "propinsi_id" integer not null, "kode_kabupaten" varchar, "nama_kabupaten" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("propinsi_id") references "propinsi"("id") on delete cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "kabupaten_kode_kabupaten_unique" on "kabupaten" ("kode_kabupaten");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kabupaten` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik referensi kabupaten/kota',
  `propinsi_id` bigint unsigned NOT NULL,
  `kode_kabupaten` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode BPS/Kemendagri kabupaten (misal: 34.04)',
  `nama_kabupaten` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama resmi kabupaten/kota (misal: Kabupaten Sleman)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kabupaten_kode_kabupaten_unique` (`kode_kabupaten`),
  KEY `kabupaten_propinsi_id_foreign` (`propinsi_id`),
  CONSTRAINT `kabupaten_propinsi_id_foreign` FOREIGN KEY (`propinsi_id`) REFERENCES `propinsi` (`id`) ON DELETE CASCADE
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
