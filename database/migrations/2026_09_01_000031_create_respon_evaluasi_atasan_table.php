<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'respon_evaluasi_atasan' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'respon_evaluasi_atasan' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "respon_evaluasi_atasan" ("id" integer primary key autoincrement not null, "evaluasi_atasan_id" integer not null, "pertanyaan_id" integer not null, "nilai" varchar not null, "catatan" text, "created_at" datetime, "updated_at" datetime, foreign key("evaluasi_atasan_id") references "evaluasi_atasan"("id") on delete cascade, foreign key("pertanyaan_id") references "pertanyaan_evaluasi_atasan"("id") on delete cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "eval_atasan_pertanyaan_unique" on "respon_evaluasi_atasan" ("evaluasi_atasan_id", "pertanyaan_id");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `respon_evaluasi_atasan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik respon evaluasi',
  `evaluasi_atasan_id` bigint unsigned NOT NULL,
  `pertanyaan_id` bigint unsigned NOT NULL,
  `nilai` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Jawaban respon yang dipilih oleh atasan penilai',
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Catatan tambahan atau saran kualitatif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `eval_atasan_pertanyaan_unique` (`evaluasi_atasan_id`,`pertanyaan_id`),
  KEY `respon_evaluasi_atasan_pertanyaan_id_foreign` (`pertanyaan_id`),
  CONSTRAINT `respon_evaluasi_atasan_evaluasi_atasan_id_foreign` FOREIGN KEY (`evaluasi_atasan_id`) REFERENCES `evaluasi_atasan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `respon_evaluasi_atasan_pertanyaan_id_foreign` FOREIGN KEY (`pertanyaan_id`) REFERENCES `pertanyaan_evaluasi_atasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'respon_evaluasi_atasan' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('respon_evaluasi_atasan');
    }
};
