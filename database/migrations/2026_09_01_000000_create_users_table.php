<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'users' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'users' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar, "username" varchar not null, "email" varchar, "role" varchar not null default 'alumni', "prodi_id" integer, "fakultas_id" integer, "must_change_password" tinyint(1) not null default '0', "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE INDEX "users_fakultas_id_index" on "users" ("fakultas_id");
SQL,
                <<<'SQL'
CREATE INDEX "users_prodi_id_index" on "users" ("prodi_id");
SQL,
                <<<'SQL'
CREATE UNIQUE INDEX "users_username_unique" on "users" ("username");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik akun pengguna',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama pengguna / alumni',
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username akun login (NIM untuk alumni, identitas untuk staf)',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alamat email akun login',
  `role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alumni' COMMENT 'Peran pengguna: superadmin, admin_biro3, admin_fakultas, admin_prodi, alumni',
  `prodi_id` bigint unsigned DEFAULT NULL COMMENT 'Relasi ke prodi jika admin prodi atau alumni',
  `fakultas_id` bigint unsigned DEFAULT NULL COMMENT 'Relasi ke fakultas jika admin fakultas',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Penanda kewajiban ganti sandi saat pertama login',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hash kata sandi akun',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_prodi_id_index` (`prodi_id`),
  KEY `users_fakultas_id_index` (`fakultas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'users' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
