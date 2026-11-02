<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'ump' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'ump' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "ump" ("id" integer primary key autoincrement not null, "kode_provinsi" varchar not null, "tahun" integer not null default '2026', "besaran" numeric, "catatan" varchar, "created_at" datetime, "updated_at" datetime, foreign key("kode_provinsi") references "propinsi"("kode_provinsi") on delete cascade on update cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "ump_kode_provinsi_tahun_unique" on "ump" ("kode_provinsi", "tahun");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `ump` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik referensi Upah Minimum Provinsi (UMP)',
  `kode_provinsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode provinsi BPS referensi',
  `tahun` year NOT NULL DEFAULT '2026' COMMENT 'Tahun penetapan UMP (YYYY)',
  `besaran` decimal(15,2) DEFAULT NULL COMMENT 'Nominal besaran rupiah UMP',
  `catatan` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Catatan atau dasar hukum SK Gubernur penetapan UMP',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ump_kode_provinsi_tahun_unique` (`kode_provinsi`,`tahun`),
  CONSTRAINT `ump_kode_provinsi_foreign` FOREIGN KEY (`kode_provinsi`) REFERENCES `propinsi` (`kode_provinsi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'ump' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('ump');
    }
};
