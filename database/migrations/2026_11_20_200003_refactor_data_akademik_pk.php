<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengubah PK data_akademik dari `id` bigint menjadi `nim` char(8).
 * Hanya berjalan di MySQL; SQLite menggunakan DDL baru di original migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('data_akademik', 'id')) {
            return;
        }

        DB::unprepared(<<<'SQL'
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `data_akademik` MODIFY COLUMN `id` bigint unsigned NOT NULL;
ALTER TABLE `data_akademik` DROP PRIMARY KEY;
ALTER TABLE `data_akademik` ADD PRIMARY KEY (`nim`);
ALTER TABLE `data_akademik` DROP INDEX `data_akademik_nim_unique`;
ALTER TABLE `data_akademik` DROP COLUMN `id`;
SET FOREIGN_KEY_CHECKS = 1;
SQL
        );
    }

    public function down(): void {}
};
