<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'atasan' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'atasan' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "atasan" ("id" integer primary key autoincrement not null, "perusahaan_id" integer, "user_id" integer, "nama" varchar not null, "email" varchar, "telepon" varchar, "created_at" datetime, "updated_at" datetime, foreign key("perusahaan_id") references "perusahaan"("id") on delete set null, foreign key("user_id") references "users"("id") on delete set null);
SQL
            );
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `atasan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik data atasan langsung',
  `perusahaan_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama lengkap atasan penilai',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alamat email resmi atasan',
  `telepon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor telepon/WhatsApp atasan penilai',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `atasan_perusahaan_id_foreign` (`perusahaan_id`),
  KEY `atasan_user_id_foreign` (`user_id`),
  CONSTRAINT `atasan_perusahaan_id_foreign` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `atasan_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'atasan' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('atasan');
    }
};
