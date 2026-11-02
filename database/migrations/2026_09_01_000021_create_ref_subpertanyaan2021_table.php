<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'ref_subpertanyaan2021' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'ref_subpertanyaan2021' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "ref_subpertanyaan2021" ("id" integer primary key autoincrement not null, "kelompok" varchar not null, "kelompok_pertanyaan_id" integer, "kode_pertanyaan" varchar not null, "subpertanyaan" text not null, "type" varchar not null, "keterangan" varchar, "wajib" integer not null default '1', "tampil_di" varchar, "order" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("kelompok_pertanyaan_id") references "kelompok_pertanyaan"("id") on delete cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "ref_subpertanyaan2021_kode_pertanyaan_unique" on "ref_subpertanyaan2021" ("kode_pertanyaan");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `ref_subpertanyaan2021` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik butir pertanyaan kuesioner',
  `kelompok` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode kelompok pertanyaan (misal: K1, K2)',
  `kelompok_pertanyaan_id` bigint unsigned DEFAULT NULL,
  `kode_pertanyaan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode butir pertanyaan (misal: F5B, F11, F2A)',
  `subpertanyaan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks redaksi pertanyaan',
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipe input pertanyaan: text, radio, multiple_choice, number, matrix, dll',
  `keterangan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Keterangan operasional sub-pertanyaan',
  `wajib` tinyint NOT NULL DEFAULT '1' COMMENT 'Indikator kewajiban: 1 = Wajib, 0 = Opsional',
  `tampil_di` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Logika percabangan / kondisi penampil pertanyaan',
  `order` smallint NOT NULL DEFAULT '0' COMMENT 'Nomor urut penampilan pertanyaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_subpertanyaan2021_kode_pertanyaan_unique` (`kode_pertanyaan`),
  KEY `ref_subpertanyaan2021_kelompok_pertanyaan_id_foreign` (`kelompok_pertanyaan_id`),
  CONSTRAINT `ref_subpertanyaan2021_kelompok_pertanyaan_id_foreign` FOREIGN KEY (`kelompok_pertanyaan_id`) REFERENCES `kelompok_pertanyaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'ref_subpertanyaan2021' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_subpertanyaan2021');
    }
};
