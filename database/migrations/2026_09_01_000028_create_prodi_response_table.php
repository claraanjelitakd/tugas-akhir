<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'prodi_response' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'prodi_response' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "prodi_response" ("id" integer primary key autoincrement not null, "biodata_id" integer not null, "prodi_question_id" integer not null, "answer_text" text, "answer_json" text, "created_at" datetime, "updated_at" datetime, foreign key("biodata_id") references "biodata"("id") on delete cascade, foreign key("prodi_question_id") references "prodi_question"("id") on delete cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "prodi_response_biodata_id_prodi_question_id_unique" on "prodi_response" ("biodata_id", "prodi_question_id");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `prodi_response` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik respon kuesioner prodi',
  `biodata_id` bigint unsigned NOT NULL,
  `prodi_question_id` bigint unsigned NOT NULL,
  `answer_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Nilai jawaban teks atau opsi yang dipilih',
  `answer_json` json DEFAULT NULL COMMENT 'Nilai jawaban berupa array/JSON bila bernilai jamak',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prodi_response_biodata_id_prodi_question_id_unique` (`biodata_id`,`prodi_question_id`),
  KEY `prodi_response_prodi_question_id_foreign` (`prodi_question_id`),
  CONSTRAINT `prodi_response_biodata_id_foreign` FOREIGN KEY (`biodata_id`) REFERENCES `biodata` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prodi_response_prodi_question_id_foreign` FOREIGN KEY (`prodi_question_id`) REFERENCES `prodi_question` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'prodi_response' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('prodi_response');
    }
};
