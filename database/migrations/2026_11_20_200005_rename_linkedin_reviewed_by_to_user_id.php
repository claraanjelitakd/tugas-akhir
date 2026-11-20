<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti nama kolom linkedin_sync_results.reviewed_by -> user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('linkedin_sync_results', 'reviewed_by')) {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE `linkedin_sync_results` DROP FOREIGN KEY `linkedin_sync_results_reviewed_by_foreign`;
ALTER TABLE `linkedin_sync_results` DROP INDEX `linkedin_sync_results_reviewed_by_foreign`;
ALTER TABLE `linkedin_sync_results` RENAME COLUMN `reviewed_by` TO `user_id`;
ALTER TABLE `linkedin_sync_results` ADD INDEX `linkedin_sync_results_user_id_index` (`user_id`);
ALTER TABLE `linkedin_sync_results` ADD CONSTRAINT `linkedin_sync_results_user_id_foreign`
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
SQL
        );
    }

    public function down(): void {}
};
