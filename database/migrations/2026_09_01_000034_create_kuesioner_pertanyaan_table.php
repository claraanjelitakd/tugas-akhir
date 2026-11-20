<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'kuesioner_pertanyaan' sebagai bank pertanyaan terpadu dan reusable.
 * Menyimpan butir pertanyaan master yang dapat digunakan lintas bagian, prodi, maupun universitas.
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi tabel kuesioner_pertanyaan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kuesioner_pertanyaan" (
    "id" integer primary key autoincrement not null,
    "kode_pertanyaan" varchar not null,
    "label_pertanyaan" varchar,
    "teks_pertanyaan" text not null,
    "tipe" varchar not null default 'single_choice',
    "pemilik_kode_prodi" varchar,
    "is_reusable" tinyint(1) not null default '1',
    "keterangan" varchar,
    "is_active" tinyint(1) not null default '1',
    "created_at" datetime,
    "updated_at" datetime,
    foreign key("pemilik_kode_prodi") references "ref_prodi"("kode_prodi") on delete set null
);
SQL
            );

            DB::statement('CREATE UNIQUE INDEX "kuesioner_pertanyaan_kode_unique" on "kuesioner_pertanyaan" ("kode_pertanyaan");');
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kuesioner_pertanyaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik butir pertanyaan',
  `kode_pertanyaan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik butir pertanyaan (contoh: F5B, PSI-01, ATASAN-01)',
  `label_pertanyaan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Label ringkas butir untuk grafik, tab, dan ringkasan analitik',
  `teks_pertanyaan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks redaksi pertanyaan survei',
  `tipe` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single_choice' COMMENT 'Tipe input: single_choice, multiple_choice, text, number, likert_5',
  `pemilik_kode_prodi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode prodi pembuat butir pertanyaan (NULL = milik umum/universitas)',
  `is_reusable` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Status apakah butir dapat digunakan/dipinjam oleh prodi lain',
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Keterangan operasional pertanyaan',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Status keaktifan butir instrumen',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kuesioner_pertanyaan_kode_unique` (`kode_pertanyaan`),
  KEY `kuesioner_pertanyaan_pemilik_kode_prodi_foreign` (`pemilik_kode_prodi`),
  CONSTRAINT `kuesioner_pertanyaan_pemilik_kode_prodi_foreign` FOREIGN KEY (`pemilik_kode_prodi`) REFERENCES `ref_prodi` (`kode_prodi`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Batalkan migrasi tabel kuesioner_pertanyaan.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuesioner_pertanyaan');
    }
};
