<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'ref_negara' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'ref_negara' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "ref_negara" ("id" integer primary key autoincrement not null, "kode_iso2" varchar not null, "kode_iso3" varchar, "nama_negara" varchar not null, "nama_resmi" varchar, "benua" varchar, "ibu_kota" varchar, "created_at" datetime, "updated_at" datetime);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "ref_negara_kode_iso2_unique" on "ref_negara" ("kode_iso2");
SQL,
                <<<'SQL'
CREATE UNIQUE INDEX "ref_negara_nama_negara_unique" on "ref_negara" ("nama_negara");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `ref_negara` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik referensi negara',
  `kode_iso2` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode 2 huruf ISO-3166-1 alpha-2 (misal: ID, SG)',
  `kode_iso3` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode 3 huruf ISO-3166-1 alpha-3 (misal: IDN, SGP)',
  `nama_negara` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama umum negara dalam Bahasa Indonesia',
  `nama_resmi` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama resmi kenegaraan lengkap',
  `benua` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Benua lokasi negara: Asia, Eropa, dll',
  `ibu_kota` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama ibu kota negara',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_negara_kode_iso2_unique` (`kode_iso2`),
  UNIQUE KEY `ref_negara_nama_negara_unique` (`nama_negara`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'ref_negara' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_negara');
    }
};
