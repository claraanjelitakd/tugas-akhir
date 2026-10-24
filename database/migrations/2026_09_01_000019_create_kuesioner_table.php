<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'kuesioner' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'kuesioner' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kuesioner" ("id" integer primary key autoincrement not null, "title" varchar not null, "description" text, "year" integer not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kuesioner` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik instrumen kuesioner induk',
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Judul instrumen kuesioner (misal: Tracer Study 2026)',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Deskripsi penjelasan instrumen kuesioner',
  `year` year NOT NULL COMMENT 'Tahun periode pelaksanaan tracer study',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Status keaktifan kuesioner bagi responden',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'kuesioner' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuesioner');
    }
};
