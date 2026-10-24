<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'ref_fakultas' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'ref_fakultas' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "ref_fakultas" ("id" integer primary key autoincrement not null, "kode_fakultas" varchar not null, "nama_fakultas" varchar not null, "singkatan" varchar, "created_at" datetime, "updated_at" datetime);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "ref_fakultas_kode_fakultas_unique" on "ref_fakultas" ("kode_fakultas");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `ref_fakultas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik master fakultas',
  `kode_fakultas` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode resmi fakultas (misal: 7, 1)',
  `nama_fakultas` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama resmi fakultas (misal: Fakultas Teknologi Informasi)',
  `singkatan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Singkatan nama fakultas (misal: FTI, FB)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_fakultas_kode_fakultas_unique` (`kode_fakultas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'ref_fakultas' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_fakultas');
    }
};
