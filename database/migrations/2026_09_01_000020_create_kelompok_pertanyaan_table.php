<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'kelompok_pertanyaan' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'kelompok_pertanyaan' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kelompok_pertanyaan" ("id" integer primary key autoincrement not null, "kuesioner_id" integer, "kode_kelompok" varchar, "title" varchar not null, "order" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("kuesioner_id") references "kuesioner"("id") on delete cascade);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kelompok_pertanyaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik bagian / kelompok pertanyaan kuesioner',
  `kuesioner_id` bigint unsigned DEFAULT NULL,
  `kode_kelompok` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode pengelompokan pertanyaan (misal: K1, K2, F)',
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama bagian / section kuesioner',
  `order` smallint NOT NULL DEFAULT '0' COMMENT 'Nomor urut penampilan bagian kuesioner',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kelompok_pertanyaan_kuesioner_id_foreign` (`kuesioner_id`),
  CONSTRAINT `kelompok_pertanyaan_kuesioner_id_foreign` FOREIGN KEY (`kuesioner_id`) REFERENCES `kuesioner` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'kelompok_pertanyaan' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelompok_pertanyaan');
    }
};
