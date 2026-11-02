<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'propinsi' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'propinsi' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "propinsi" ("id" integer primary key autoincrement not null, "kode_provinsi" varchar, "nama_provinsi" varchar not null, "created_at" datetime, "updated_at" datetime);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "propinsi_kode_provinsi_unique" on "propinsi" ("kode_provinsi");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `propinsi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik referensi provinsi',
  `kode_provinsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode BPS/Kemendagri provinsi (misal: 34)',
  `nama_provinsi` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama resmi provinsi (misal: DI Yogyakarta)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `propinsi_kode_provinsi_unique` (`kode_provinsi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'propinsi' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('propinsi');
    }
};
