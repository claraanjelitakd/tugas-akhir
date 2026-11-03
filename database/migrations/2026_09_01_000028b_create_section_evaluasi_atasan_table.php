<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'section_evaluasi_atasan' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'section_evaluasi_atasan' beserta indeks yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "section_evaluasi_atasan" (
  "id" integer primary key autoincrement not null,
  "key" varchar,
  "title" varchar not null,
  "description" text,
  "order" integer not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `section_evaluasi_atasan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik bagian / section evaluasi atasan',
  `key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kunci langkah stepper internal',
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama bagian / section kuesioner evaluasi atasan',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Deskripsi bagian evaluasi atasan',
  `order` int NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan bagian',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'section_evaluasi_atasan' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_evaluasi_atasan');
    }
};
