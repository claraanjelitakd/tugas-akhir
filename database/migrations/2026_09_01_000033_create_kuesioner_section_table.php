<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'kuesioner_section' sebagai wadah kelompok/bagian instrumen survei terpadu.
 * Mendukung pembagian skala universitas, program studi spesifik, maupun evaluasi pengguna lulusan.
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi tabel kuesioner_section.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kuesioner_section" (
    "id" integer primary key autoincrement not null,
    "kode_section" varchar not null,
    "target" varchar not null default 'univ',
    "prodi_id" integer,
    "judul" varchar not null,
    "deskripsi" text,
    "order" integer not null default '1',
    "is_active" tinyint(1) not null default '1',
    "created_at" datetime,
    "updated_at" datetime,
    foreign key("prodi_id") references "prodi"("id") on delete set null
);
SQL
            );

            DB::statement('CREATE UNIQUE INDEX "kuesioner_section_kode_unique" on "kuesioner_section" ("kode_section");');
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kuesioner_section` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik bagian kuesioner',
  `kode_section` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik bagian instrumen (contoh: SEC_UNIV_01, SEC_SI_01)',
  `target` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'univ' COMMENT 'Target kuesioner: univ, prodi, atau atasan',
  `prodi_id` bigint unsigned DEFAULT NULL COMMENT 'ID prodi jika section spesifik untuk satu program studi',
  `judul` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Judul atau nama bagian kuesioner',
  `deskripsi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Deskripsi atau petunjuk pengisian bagian ini',
  `order` smallint NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan bagian',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Status keaktifan bagian kuesioner',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kuesioner_section_kode_unique` (`kode_section`),
  KEY `kuesioner_section_prodi_id_foreign` (`prodi_id`),
  KEY `kuesioner_section_target_idx` (`target`),
  CONSTRAINT `kuesioner_section_prodi_id_foreign` FOREIGN KEY (`prodi_id`) REFERENCES `prodi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Batalkan migrasi tabel kuesioner_section.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuesioner_section');
    }
};
