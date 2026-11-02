<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'log_activities' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'log_activities' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "log_activities" ("id" integer primary key autoincrement not null, "user_id" integer, "action" varchar not null, "model_type" varchar, "model_id" integer, "description" text not null, "old_values" text, "new_values" text, "ip_address" varchar, "user_agent" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete set null);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE INDEX "log_activities_action_created_at_index" on "log_activities" ("action", "created_at");
SQL,
                <<<'SQL'
CREATE INDEX "log_activities_model_type_model_id_index" on "log_activities" ("model_type", "model_id");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `log_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik rekam jejak aktivitas audit',
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode jenis aktivitas (e.g. GANTI_PERUSAHAAN_MASTER)',
  `model_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama kelas model target aktivitas',
  `model_id` bigint unsigned DEFAULT NULL COMMENT 'ID record model target aktivitas',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Uraian naratif lengkap aktivitas sistem',
  `old_values` json DEFAULT NULL COMMENT 'Nilai atribut sebelum perubahan (JSON)',
  `new_values` json DEFAULT NULL COMMENT 'Nilai atribut sesudah perubahan (JSON)',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alamat IP perangkat pengakses',
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'User agent browser atau aplikasi pengakses',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `log_activities_user_id_foreign` (`user_id`),
  KEY `log_activities_action_created_at_index` (`action`,`created_at`),
  KEY `log_activities_model_type_model_id_index` (`model_type`,`model_id`),
  CONSTRAINT `log_activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'log_activities' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_activities');
    }
};
