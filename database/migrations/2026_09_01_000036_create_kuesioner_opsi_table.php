<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'kuesioner_opsi' yang memuat opsi pilihan jawaban master untuk pertanyaan
 * bertipe pilihan tunggal, pilihan jamak, maupun skala Likert/rating.
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi tabel kuesioner_opsi.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kuesioner_opsi" (
    "id" integer primary key autoincrement not null,
    "pertanyaan_id" integer not null,
    "kode_opsi" varchar,
    "label" text not null,
    "label_singkat" varchar,
    "nilai_skor" integer,
    "lompat_ke_pertanyaan_id" varchar,
    "order" integer not null default '1',
    "created_at" datetime,
    "updated_at" datetime,
    foreign key("pertanyaan_id") references "kuesioner_pertanyaan"("id") on delete cascade
);
SQL
            );

            DB::statement('CREATE INDEX "kuesioner_opsi_pertanyaan_id_index" on "kuesioner_opsi" ("pertanyaan_id");');
            DB::statement('CREATE UNIQUE INDEX "kuesioner_opsi_question_code_unique" on "kuesioner_opsi" ("pertanyaan_id", "kode_opsi");');
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kuesioner_opsi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik pilihan jawaban',
  `pertanyaan_id` bigint unsigned NOT NULL COMMENT 'ID butir pertanyaan pemilik opsi',
  `kode_opsi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode identifikasi opsi jika ada (contoh: F501, F502)',
  `label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks label pilihan jawaban yang terbaca responden',
  `label_singkat` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teks label ringkas pilihan jawaban untuk visualisasi grafik dan analitik',
  `nilai_skor` smallint DEFAULT NULL COMMENT 'Nilai bobot numerik / skala Likert (contoh: 1-5)',
  `lompat_ke_pertanyaan_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode pertanyaan target untuk percabangan jawaban',
  `order` smallint NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan pilihan jawaban',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kuesioner_opsi_pertanyaan_id_foreign` (`pertanyaan_id`),
  UNIQUE KEY `kuesioner_opsi_question_code_unique` (`pertanyaan_id`, `kode_opsi`),
  CONSTRAINT `kuesioner_opsi_pertanyaan_id_foreign` FOREIGN KEY (`pertanyaan_id`) REFERENCES `kuesioner_pertanyaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Batalkan migrasi tabel kuesioner_opsi.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuesioner_opsi');
    }
};
