<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'prodi_question_section' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'prodi_question_section' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "prodi_question_section" ("id" integer primary key autoincrement not null, "prodi_id" integer not null, "title" varchar not null, "description" text, "order" integer not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("prodi_id") references "prodi"("id") on delete cascade);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `prodi_question_section` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik bagian kuesioner prodi',
  `prodi_id` bigint unsigned NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama bagian / section kuesioner prodi',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Deskripsi penjelasan bagian kuesioner prodi',
  `order` smallint NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan bagian',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prodi_question_section_prodi_id_foreign` (`prodi_id`),
  CONSTRAINT `prodi_question_section_prodi_id_foreign` FOREIGN KEY (`prodi_id`) REFERENCES `prodi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'prodi_question_section' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('prodi_question_section');
    }
};
