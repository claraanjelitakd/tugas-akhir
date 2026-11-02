<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'ref_subpertanyaan_detil' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'ref_subpertanyaan_detil' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "ref_subpertanyaan_detil" ("id" integer primary key autoincrement not null, "pertanyaan_id" integer not null, "kode_pertanyaan" varchar, "kode_opsi" varchar, "option_text" text not null, "jump_to" varchar, "order" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("pertanyaan_id") references "ref_subpertanyaan2021"("id") on delete cascade);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `ref_subpertanyaan_detil` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik opsi butir pertanyaan',
  `pertanyaan_id` bigint unsigned NOT NULL,
  `kode_pertanyaan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode pertanyaan induk',
  `kode_opsi` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode opsi jawaban (misal: 1, 2, F401)',
  `option_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label teks pilihan jawaban',
  `jump_to` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode percabangan lompat (skip logic)',
  `order` smallint NOT NULL DEFAULT '0' COMMENT 'Nomor urut penampilan opsi jawaban',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ref_subpertanyaan_detil_pertanyaan_id_foreign` (`pertanyaan_id`),
  CONSTRAINT `ref_subpertanyaan_detil_pertanyaan_id_foreign` FOREIGN KEY (`pertanyaan_id`) REFERENCES `ref_subpertanyaan2021` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'ref_subpertanyaan_detil' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_subpertanyaan_detil');
    }
};
