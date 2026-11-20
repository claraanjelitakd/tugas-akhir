<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengubah PK ref_fakultas dari `id` bigint menjadi `kode_fakultas` varchar(10).
 * Memperbarui kolom FK di tabel prodi dan users.
 * Hanya berjalan di MySQL; SQLite menggunakan DDL baru di original migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('ref_fakultas', 'id')) {
            return;
        }

        DB::unprepared(<<<'SQL'
-- 1. Lepas FK dari tabel lain yang mereferens ref_fakultas.id
ALTER TABLE `prodi` DROP FOREIGN KEY `prodi_fakultas_id_foreign`;
ALTER TABLE `users` DROP FOREIGN KEY `users_fakultas_id_foreign`;

-- 2. Ubah tipe kolom FK ke varchar, lalu update nilai ke kode_fakultas
ALTER TABLE `prodi` MODIFY COLUMN `fakultas_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `prodi` p INNER JOIN `ref_fakultas` f ON p.fakultas_id = f.id SET p.fakultas_id = f.kode_fakultas;
ALTER TABLE `prodi` RENAME COLUMN `fakultas_id` TO `kode_fakultas`;

ALTER TABLE `users` MODIFY COLUMN `fakultas_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `users` u INNER JOIN `ref_fakultas` f ON u.fakultas_id = f.id SET u.fakultas_id = f.kode_fakultas;
ALTER TABLE `users` RENAME COLUMN `fakultas_id` TO `kode_fakultas`;

-- 3. Ubah PK ref_fakultas: hapus AUTO_INCREMENT, ganti PK ke kode_fakultas, hapus kolom id
ALTER TABLE `ref_fakultas` MODIFY COLUMN `id` bigint unsigned NOT NULL;
ALTER TABLE `ref_fakultas` DROP PRIMARY KEY;
ALTER TABLE `ref_fakultas` DROP INDEX `ref_fakultas_kode_fakultas_unique`;
ALTER TABLE `ref_fakultas` ADD PRIMARY KEY (`kode_fakultas`);
ALTER TABLE `ref_fakultas` DROP COLUMN `id`;

-- 4. Tambahkan kembali FK constraint dengan nama baru
ALTER TABLE `prodi` ADD CONSTRAINT `prodi_kode_fakultas_foreign`
  FOREIGN KEY (`kode_fakultas`) REFERENCES `ref_fakultas` (`kode_fakultas`) ON DELETE SET NULL;
ALTER TABLE `users` ADD CONSTRAINT `users_kode_fakultas_foreign`
  FOREIGN KEY (`kode_fakultas`) REFERENCES `ref_fakultas` (`kode_fakultas`) ON DELETE SET NULL;
SQL
        );
    }

    public function down(): void {}
};
