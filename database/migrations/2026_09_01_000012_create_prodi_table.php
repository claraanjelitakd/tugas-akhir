<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'prodi' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'prodi' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "prodi" ("id" integer primary key autoincrement not null, "fakultas_id" integer, "kode_prodi" varchar not null, "nama_prodi" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("fakultas_id") references "ref_fakultas"("id") on delete set null);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "prodi_kode_prodi_unique" on "prodi" ("kode_prodi");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `prodi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik master program studi',
  `fakultas_id` bigint unsigned DEFAULT NULL,
  `kode_prodi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode resmi program studi (misal: 71, 72)',
  `nama_prodi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama resmi program studi (misal: Informatika)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prodi_kode_prodi_unique` (`kode_prodi`),
  KEY `prodi_fakultas_id_foreign` (`fakultas_id`),
  CONSTRAINT `prodi_fakultas_id_foreign` FOREIGN KEY (`fakultas_id`) REFERENCES `ref_fakultas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'prodi' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('prodi');
    }
};
