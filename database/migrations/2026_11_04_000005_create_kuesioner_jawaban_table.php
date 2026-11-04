<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'kuesioner_jawaban' sebagai penyimpanan seluruh respon survei terpadu.
 * Menggantikan silo tabel tracer, prodi_response, dan respon_evaluasi_atasan.
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi tabel kuesioner_jawaban.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "kuesioner_jawaban" (
    "id" integer primary key autoincrement not null,
    "responden_type" varchar not null default 'alumni',
    "biodata_id" integer,
    "evaluasi_atasan_id" integer,
    "section_id" integer not null,
    "pertanyaan_id" integer not null,
    "opsi_id" integer,
    "jawaban_teks" text,
    "jawaban_json" text,
    "created_at" datetime,
    "updated_at" datetime,
    foreign key("biodata_id") references "biodata"("id") on delete cascade,
    foreign key("evaluasi_atasan_id") references "evaluasi_atasan"("id") on delete cascade,
    foreign key("section_id") references "kuesioner_section"("id") on delete cascade,
    foreign key("pertanyaan_id") references "kuesioner_pertanyaan"("id") on delete cascade,
    foreign key("opsi_id") references "kuesioner_opsi"("id") on delete set null
);
SQL
            );

            foreach ([
                'CREATE INDEX "kuesioner_jawaban_alumni_idx" on "kuesioner_jawaban" ("biodata_id", "pertanyaan_id");',
                'CREATE INDEX "kuesioner_jawaban_atasan_idx" on "kuesioner_jawaban" ("evaluasi_atasan_id", "pertanyaan_id");',
                'CREATE INDEX "kuesioner_jawaban_section_idx" on "kuesioner_jawaban" ("section_id");',
            ] as $sql) {
                DB::statement($sql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `kuesioner_jawaban` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik respon jawaban survei',
  `responden_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alumni' COMMENT 'Tipe responden: alumni atau atasan',
  `biodata_id` bigint unsigned DEFAULT NULL COMMENT 'ID biodata alumni pengisi (jika responden = alumni)',
  `evaluasi_atasan_id` bigint unsigned DEFAULT NULL COMMENT 'ID evaluasi atasan (jika responden = atasan)',
  `section_id` bigint unsigned NOT NULL COMMENT 'ID bagian kuesioner tempat pertanyaan diisi',
  `pertanyaan_id` bigint unsigned NOT NULL COMMENT 'ID butir pertanyaan yang dijawab',
  `opsi_id` bigint unsigned DEFAULT NULL COMMENT 'ID pilihan jawaban master terpilih (jika pilihan ganda)',
  `jawaban_teks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Nilai jawaban teks bebas atau angka',
  `jawaban_json` json DEFAULT NULL COMMENT 'Nilai jawaban terstruktur jika multi-select',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kuesioner_jawaban_biodata_id_foreign` (`biodata_id`),
  KEY `kuesioner_jawaban_evaluasi_atasan_id_foreign` (`evaluasi_atasan_id`),
  KEY `kuesioner_jawaban_section_id_foreign` (`section_id`),
  KEY `kuesioner_jawaban_pertanyaan_id_foreign` (`pertanyaan_id`),
  KEY `kuesioner_jawaban_opsi_id_foreign` (`opsi_id`),
  KEY `kuesioner_jawaban_lookup_idx` (`responden_type`,`biodata_id`,`pertanyaan_id`),
  CONSTRAINT `kuesioner_jawaban_biodata_id_foreign` FOREIGN KEY (`biodata_id`) REFERENCES `biodata` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuesioner_jawaban_evaluasi_atasan_id_foreign` FOREIGN KEY (`evaluasi_atasan_id`) REFERENCES `evaluasi_atasan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuesioner_jawaban_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `kuesioner_section` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuesioner_jawaban_pertanyaan_id_foreign` FOREIGN KEY (`pertanyaan_id`) REFERENCES `kuesioner_pertanyaan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuesioner_jawaban_opsi_id_foreign` FOREIGN KEY (`opsi_id`) REFERENCES `kuesioner_opsi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Batalkan migrasi tabel kuesioner_jawaban.
     */
    public function down(): void
    {
        Schema::dropIfExists('kuesioner_jawaban');
    }
};
