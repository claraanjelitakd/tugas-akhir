<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'prodi_question' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'prodi_question' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "prodi_question" ("id" integer primary key autoincrement not null, "prodi_id" integer not null, "prodi_question_section_id" integer not null, "code" varchar not null, "question_text" text not null, "type" varchar not null default 'single_choice', "is_required" tinyint(1) not null default '1', "order" integer not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("prodi_id") references "prodi"("id") on delete cascade, foreign key("prodi_question_section_id") references "prodi_question_section"("id") on delete cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "prodi_question_prodi_id_code_unique" on "prodi_question" ("prodi_id", "code");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `prodi_question` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik butir pertanyaan kuesioner prodi',
  `prodi_id` bigint unsigned NOT NULL,
  `prodi_question_section_id` bigint unsigned NOT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode pertanyaan (contoh: PSI-01, PTI-01)',
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks redaksi pertanyaan',
  `type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single_choice' COMMENT 'Tipe input: single_choice, multiple_choice, text, number, rating_5, radio_input',
  `is_required` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indikator kewajiban pengisian',
  `order` smallint NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan pertanyaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prodi_question_prodi_id_code_unique` (`prodi_id`,`code`),
  KEY `prodi_question_prodi_question_section_id_foreign` (`prodi_question_section_id`),
  CONSTRAINT `prodi_question_prodi_id_foreign` FOREIGN KEY (`prodi_id`) REFERENCES `prodi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prodi_question_prodi_question_section_id_foreign` FOREIGN KEY (`prodi_question_section_id`) REFERENCES `prodi_question_section` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'prodi_question' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('prodi_question');
    }
};
