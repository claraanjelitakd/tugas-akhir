<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengubah PK yudisium dari `id` bigint menjadi `nim` char(8).
 * Menghapus kolom biodata.yudisium_id (redundan karena biodata sudah punya nim).
 * Hanya berjalan di MySQL; SQLite menggunakan DDL baru di original migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('yudisium', 'id')) {
            return;
        }

        DB::unprepared(<<<'SQL'
SET FOREIGN_KEY_CHECKS = 0;
-- 1. Lepas FK biodata.yudisium_id dan hapus kolom (redundan; nim sudah cukup sebagai join key)
ALTER TABLE `biodata` DROP FOREIGN KEY `biodata_yudisium_id_foreign`;
ALTER TABLE `biodata` DROP INDEX `biodata_yudisium_id_foreign`;
ALTER TABLE `biodata` DROP COLUMN `yudisium_id`;

-- 2. Ubah PK yudisium dari id ke nim
ALTER TABLE `yudisium` MODIFY COLUMN `id` bigint unsigned NOT NULL;
ALTER TABLE `yudisium` DROP PRIMARY KEY;
ALTER TABLE `yudisium` ADD PRIMARY KEY (`nim`);
ALTER TABLE `yudisium` DROP INDEX `yudisium_nim_unique`;
ALTER TABLE `yudisium` DROP COLUMN `id`;
SET FOREIGN_KEY_CHECKS = 1;
SQL
        );
    }

    public function down(): void {}
};
