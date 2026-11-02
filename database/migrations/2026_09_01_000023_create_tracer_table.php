<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'tracer' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'tracer' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "tracer" ("id" integer primary key autoincrement not null, "biodata_id" integer not null, "question_id" integer, "nim" varchar not null, "kelompok" varchar not null, "kode_pertanyaan" varchar not null, "subpertanyaan" text not null, "answer" text, "answer_json" text, "keterangan" varchar, "tahun_lulus" integer, "created_at" datetime, "updated_at" datetime, foreign key("biodata_id") references "biodata"("id") on delete cascade, foreign key("question_id") references "ref_subpertanyaan2021"("id") on delete cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE INDEX "tracer_biodata_id_kode_pertanyaan_index" on "tracer" ("biodata_id", "kode_pertanyaan");
SQL,
                <<<'SQL'
CREATE UNIQUE INDEX "tracer_biodata_kode_pertanyaan_unique" on "tracer" ("biodata_id", "kode_pertanyaan");
SQL,
                <<<'SQL'
CREATE UNIQUE INDEX "tracer_biodata_question_unique" on "tracer" ("biodata_id", "question_id");
SQL,
                <<<'SQL'
CREATE INDEX "tracer_kelompok_index" on "tracer" ("kelompok");
SQL,
                <<<'SQL'
CREATE INDEX "tracer_nim_index" on "tracer" ("nim");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `tracer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik respon kuesioner tracer study',
  `biodata_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned DEFAULT NULL,
  `nim` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NIM mahasiswa responden (8 karakter)',
  `kelompok` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode kelompok kuesioner tracer (misal: K1, K2, F)',
  `kode_pertanyaan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode butir pertanyaan (misal: F5B, F504, F11)',
  `subpertanyaan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks redaksi pertanyaan',
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Nilai jawaban teks atau opsi yang dipilih',
  `answer_json` json DEFAULT NULL COMMENT 'Data jawaban berbentuk array / JSON bila bernilai ganda',
  `keterangan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Keterangan label opsi terpilih',
  `tahun_lulus` year DEFAULT NULL COMMENT 'Tahun kelulusan responden saat mengisi kuesioner (YYYY)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracer_biodata_kode_pertanyaan_unique` (`biodata_id`,`kode_pertanyaan`),
  UNIQUE KEY `tracer_biodata_question_unique` (`biodata_id`,`question_id`),
  KEY `tracer_question_id_foreign` (`question_id`),
  KEY `tracer_biodata_id_kode_pertanyaan_index` (`biodata_id`,`kode_pertanyaan`),
  KEY `tracer_nim_index` (`nim`),
  KEY `tracer_kelompok_index` (`kelompok`),
  CONSTRAINT `tracer_biodata_id_foreign` FOREIGN KEY (`biodata_id`) REFERENCES `biodata` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tracer_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `ref_subpertanyaan2021` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'tracer' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracer');
    }
};
