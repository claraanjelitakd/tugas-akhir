<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'prodi_question_option' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'prodi_question_option' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "prodi_question_option" ("id" integer primary key autoincrement not null, "prodi_question_id" integer not null, "code" varchar, "option_text" text not null, "jump_to" varchar, "order" integer not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("prodi_question_id") references "prodi_question"("id") on delete cascade);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `prodi_question_option` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik pilihan opsi jawaban kuesioner prodi',
  `prodi_question_id` bigint unsigned NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode opsi jawaban',
  `option_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label teks pilihan jawaban',
  `jump_to` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode percabangan lompat pertanyaan',
  `order` smallint NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan opsi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prodi_question_option_prodi_question_id_foreign` (`prodi_question_id`),
  CONSTRAINT `prodi_question_option_prodi_question_id_foreign` FOREIGN KEY (`prodi_question_id`) REFERENCES `prodi_question` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'prodi_question_option' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('prodi_question_option');
    }
};
