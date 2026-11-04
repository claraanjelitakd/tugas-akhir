<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel pivot 'kuesioner_section_pertanyaan' yang mengatur penempatan butir pertanyaan
 * ke dalam bagian (section) instrumen kuesioner bersangkutan.
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi tabel kuesioner_section_pertanyaan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kuesioner_section_pertanyaan" (
    "id" integer primary key autoincrement not null,
    "section_id" integer not null,
    "pertanyaan_id" integer not null,
    "order" integer not null default '1',
    "wajib" tinyint(1) not null default '1',
    "tampil_di" varchar,
    "created_at" datetime,
    "updated_at" datetime,
    foreign key("section_id") references "kuesioner_section"("id") on delete cascade,
    foreign key("pertanyaan_id") references "kuesioner_pertanyaan"("id") on delete cascade
);
SQL
            );

            DB::statement('CREATE UNIQUE INDEX "sec_pertanyaan_unique" on "kuesioner_section_pertanyaan" ("section_id", "pertanyaan_id");');
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kuesioner_section_pertanyaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik relasi penempatan pertanyaan ke bagian',
  `section_id` bigint unsigned NOT NULL COMMENT 'ID bagian kuesioner',
  `pertanyaan_id` bigint unsigned NOT NULL COMMENT 'ID butir pertanyaan dari bank soal',
  `order` smallint NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan pertanyaan di dalam section',
  `wajib` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Kewajiban pengisian (1 = wajib, 0 = opsional)',
  `tampil_di` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Logika percabangan / kondisi penampil',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sec_pertanyaan_unique` (`section_id`,`pertanyaan_id`),
  KEY `kuesioner_sec_pertanyaan_pertanyaan_foreign` (`pertanyaan_id`),
  CONSTRAINT `kuesioner_sec_pertanyaan_section_foreign` FOREIGN KEY (`section_id`) REFERENCES `kuesioner_section` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuesioner_sec_pertanyaan_pertanyaan_foreign` FOREIGN KEY (`pertanyaan_id`) REFERENCES `kuesioner_pertanyaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Batalkan migrasi tabel kuesioner_section_pertanyaan.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuesioner_section_pertanyaan');
    }
};
