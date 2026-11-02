<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'linkedin_sync_results' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'linkedin_sync_results' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "linkedin_sync_results" ("id" integer primary key autoincrement not null, "biodata_id" integer not null, "linkedin_url" varchar not null, "linkedin_username" varchar, "scraped_data" text not null, "status" varchar check ("status" in ('pending', 'approved', 'rejected')) not null default 'pending', "reviewed_by" integer, "reviewed_at" datetime, "scraped_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("biodata_id") references "biodata"("id") on delete cascade, foreign key("reviewed_by") references "users"("id") on delete set null);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE INDEX "linkedin_sync_results_biodata_id_status_index" on "linkedin_sync_results" ("biodata_id", "status");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `linkedin_sync_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik hasil sinkronisasi LinkedIn',
  `biodata_id` bigint unsigned NOT NULL,
  `linkedin_url` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL profil LinkedIn yang disinkronkan',
  `linkedin_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Username akun LinkedIn',
  `scraped_data` json NOT NULL COMMENT 'Payload data mentah hasil scraping LinkedIn (JSON)',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Status persetujuan hasil sinkronisasi',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu persetujuan peninjauan',
  `scraped_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu pengambilan data scraping',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `linkedin_sync_results_reviewed_by_foreign` (`reviewed_by`),
  KEY `linkedin_sync_results_biodata_id_status_index` (`biodata_id`,`status`),
  CONSTRAINT `linkedin_sync_results_biodata_id_foreign` FOREIGN KEY (`biodata_id`) REFERENCES `biodata` (`id`) ON DELETE CASCADE,
  CONSTRAINT `linkedin_sync_results_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'linkedin_sync_results' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('linkedin_sync_results');
    }
};
