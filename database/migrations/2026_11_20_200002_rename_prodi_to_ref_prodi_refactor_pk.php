<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti nama tabel prodi -> ref_prodi dan mengubah PK dari `id` ke `kode_prodi`.
 * Memperbarui semua kolom FK di tabel terkait (data_akademik, users, biodata,
 * kuesioner_section, kuesioner_pertanyaan, perusahaan).
 * Hanya berjalan di MySQL; SQLite menggunakan DDL baru di original migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('prodi')) {
            return;
        }

        DB::unprepared(<<<'SQL'
-- 1. Lepas semua FK yang mereferens prodi.id
ALTER TABLE `data_akademik` DROP FOREIGN KEY `data_akademik_prodi_id_foreign`;
ALTER TABLE `users` DROP FOREIGN KEY `users_prodi_id_foreign`;
ALTER TABLE `biodata` DROP FOREIGN KEY `biodata_prodi_id_foreign`;
ALTER TABLE `kuesioner_section` DROP FOREIGN KEY `kuesioner_section_prodi_id_foreign`;
ALTER TABLE `kuesioner_pertanyaan` DROP FOREIGN KEY `kuesioner_pertanyaan_pemilik_prodi_foreign`;
ALTER TABLE `perusahaan` DROP FOREIGN KEY `perusahaan_created_by_prodi_id_foreign`;

-- 2. data_akademik.prodi_id → kode_prodi (varchar)
ALTER TABLE `data_akademik` MODIFY COLUMN `prodi_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `data_akademik` da INNER JOIN `prodi` p ON da.prodi_id = p.id SET da.prodi_id = p.kode_prodi;
ALTER TABLE `data_akademik` RENAME COLUMN `prodi_id` TO `kode_prodi`;
ALTER TABLE `data_akademik` DROP INDEX `data_akademik_prodi_id_index`;
ALTER TABLE `data_akademik` ADD INDEX `data_akademik_kode_prodi_index` (`kode_prodi`);

-- 3. users.prodi_id → kode_prodi (varchar)
ALTER TABLE `users` MODIFY COLUMN `prodi_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `users` u INNER JOIN `prodi` p ON u.prodi_id = p.id SET u.prodi_id = p.kode_prodi;
ALTER TABLE `users` RENAME COLUMN `prodi_id` TO `kode_prodi`;

-- 4. biodata.prodi_id → kode_prodi (varchar)
ALTER TABLE `biodata` MODIFY COLUMN `prodi_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `biodata` b INNER JOIN `prodi` p ON b.prodi_id = p.id SET b.prodi_id = p.kode_prodi;
ALTER TABLE `biodata` RENAME COLUMN `prodi_id` TO `kode_prodi`;
ALTER TABLE `biodata` DROP INDEX `biodata_prodi_id_foreign`;
ALTER TABLE `biodata` ADD INDEX `biodata_kode_prodi_index` (`kode_prodi`);

-- 5. kuesioner_section.prodi_id → kode_prodi (varchar)
ALTER TABLE `kuesioner_section` MODIFY COLUMN `prodi_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `kuesioner_section` ks INNER JOIN `prodi` p ON ks.prodi_id = p.id SET ks.prodi_id = p.kode_prodi;
ALTER TABLE `kuesioner_section` RENAME COLUMN `prodi_id` TO `kode_prodi`;
ALTER TABLE `kuesioner_section` DROP INDEX `kuesioner_section_prodi_id_foreign`;
ALTER TABLE `kuesioner_section` ADD INDEX `kuesioner_section_kode_prodi_index` (`kode_prodi`);

-- 6. kuesioner_pertanyaan.pemilik_prodi_id → pemilik_kode_prodi (varchar)
ALTER TABLE `kuesioner_pertanyaan` MODIFY COLUMN `pemilik_prodi_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `kuesioner_pertanyaan` kp INNER JOIN `prodi` p ON kp.pemilik_prodi_id = p.id SET kp.pemilik_prodi_id = p.kode_prodi;
ALTER TABLE `kuesioner_pertanyaan` RENAME COLUMN `pemilik_prodi_id` TO `pemilik_kode_prodi`;
ALTER TABLE `kuesioner_pertanyaan` DROP INDEX `kuesioner_pertanyaan_pemilik_prodi_foreign`;
ALTER TABLE `kuesioner_pertanyaan` ADD INDEX `kuesioner_pertanyaan_pemilik_kode_prodi_index` (`pemilik_kode_prodi`);

-- 7. perusahaan.created_by_prodi_id → created_by_kode_prodi (varchar)
ALTER TABLE `perusahaan` MODIFY COLUMN `created_by_prodi_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
UPDATE `perusahaan` pe INNER JOIN `prodi` p ON pe.created_by_prodi_id = p.id SET pe.created_by_prodi_id = p.kode_prodi;
ALTER TABLE `perusahaan` RENAME COLUMN `created_by_prodi_id` TO `created_by_kode_prodi`;
ALTER TABLE `perusahaan` DROP INDEX `perusahaan_created_by_prodi_id_index`;
ALTER TABLE `perusahaan` ADD INDEX `perusahaan_created_by_kode_prodi_index` (`created_by_kode_prodi`);

-- 8. Ubah PK prodi: hapus AUTO_INCREMENT, ganti PK ke kode_prodi, hapus kolom id
ALTER TABLE `prodi` MODIFY COLUMN `id` bigint unsigned NOT NULL;
ALTER TABLE `prodi` DROP PRIMARY KEY;
ALTER TABLE `prodi` DROP INDEX `prodi_kode_prodi_unique`;
ALTER TABLE `prodi` ADD PRIMARY KEY (`kode_prodi`);
ALTER TABLE `prodi` DROP COLUMN `id`;

-- 9. Ganti nama tabel prodi → ref_prodi
RENAME TABLE `prodi` TO `ref_prodi`;

-- 10. Tambahkan kembali semua FK constraint dengan nama baru
ALTER TABLE `data_akademik` ADD CONSTRAINT `data_akademik_kode_prodi_foreign`
  FOREIGN KEY (`kode_prodi`) REFERENCES `ref_prodi` (`kode_prodi`) ON DELETE SET NULL;
ALTER TABLE `users` ADD CONSTRAINT `users_kode_prodi_foreign`
  FOREIGN KEY (`kode_prodi`) REFERENCES `ref_prodi` (`kode_prodi`) ON DELETE SET NULL;
ALTER TABLE `biodata` ADD CONSTRAINT `biodata_kode_prodi_foreign`
  FOREIGN KEY (`kode_prodi`) REFERENCES `ref_prodi` (`kode_prodi`) ON DELETE SET NULL;
ALTER TABLE `kuesioner_section` ADD CONSTRAINT `kuesioner_section_kode_prodi_foreign`
  FOREIGN KEY (`kode_prodi`) REFERENCES `ref_prodi` (`kode_prodi`) ON DELETE SET NULL;
ALTER TABLE `kuesioner_pertanyaan` ADD CONSTRAINT `kuesioner_pertanyaan_pemilik_kode_prodi_foreign`
  FOREIGN KEY (`pemilik_kode_prodi`) REFERENCES `ref_prodi` (`kode_prodi`) ON DELETE SET NULL;
ALTER TABLE `perusahaan` ADD CONSTRAINT `perusahaan_created_by_kode_prodi_foreign`
  FOREIGN KEY (`created_by_kode_prodi`) REFERENCES `ref_prodi` (`kode_prodi`) ON DELETE SET NULL;
SQL
        );
    }

    public function down(): void {}
};
