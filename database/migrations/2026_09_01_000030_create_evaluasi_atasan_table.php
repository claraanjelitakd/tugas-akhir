<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'evaluasi_atasan' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'evaluasi_atasan' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "evaluasi_atasan" ("id" integer primary key autoincrement not null, "biodata_id" integer not null, "perusahaan_id" integer, "atasan_id" integer, "token" varchar not null, "is_submitted" tinyint(1) not null default '0', "submitted_at" datetime, "jumlah_alumni_ukdw" varchar, "standar_gaji_pertama" varchar, "created_at" datetime, "updated_at" datetime, foreign key("biodata_id") references "biodata"("id") on delete cascade, foreign key("perusahaan_id") references "perusahaan"("id") on delete set null, foreign key("atasan_id") references "atasan"("id") on delete set null);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "evaluasi_atasan_token_unique" on "evaluasi_atasan" ("token");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `evaluasi_atasan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik transaksi evaluasi atasan',
  `biodata_id` bigint unsigned NOT NULL,
  `perusahaan_id` bigint unsigned DEFAULT NULL,
  `atasan_id` bigint unsigned DEFAULT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token acak unik untuk akses formulir publik tanpa login',
  `is_submitted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Penanda status apakah evaluasi sudah disubmit oleh atasan',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu penyerahan / submit respon evaluasi',
  `jumlah_alumni_ukdw` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rentang jumlah alumni UKDW yang bekerja di instansi: < 5 Orang, 6 - 10 Orang, dll',
  `standar_gaji_pertama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Standar gaji pertama per bulan untuk alumni UKDW di instansi ini',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evaluasi_atasan_token_unique` (`token`),
  KEY `evaluasi_atasan_biodata_id_foreign` (`biodata_id`),
  KEY `evaluasi_atasan_perusahaan_id_foreign` (`perusahaan_id`),
  KEY `evaluasi_atasan_atasan_id_foreign` (`atasan_id`),
  CONSTRAINT `evaluasi_atasan_atasan_id_foreign` FOREIGN KEY (`atasan_id`) REFERENCES `atasan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `evaluasi_atasan_biodata_id_foreign` FOREIGN KEY (`biodata_id`) REFERENCES `biodata` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluasi_atasan_perusahaan_id_foreign` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'evaluasi_atasan' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluasi_atasan');
    }
};
