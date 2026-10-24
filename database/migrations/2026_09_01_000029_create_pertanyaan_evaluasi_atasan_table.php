<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'pertanyaan_evaluasi_atasan' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'pertanyaan_evaluasi_atasan' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "pertanyaan_evaluasi_atasan" ("id" integer primary key autoincrement not null, "kode" varchar not null, "aspek" varchar not null, "deskripsi" text, "kategori" varchar not null default 'Kinerja', "tipe" varchar not null default 'likert_5', "pilihan_jawaban" text, "is_active" tinyint(1) not null default '1', "order" integer not null default '1', "created_at" datetime, "updated_at" datetime);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "pertanyaan_evaluasi_atasan_kode_unique" on "pertanyaan_evaluasi_atasan" ("kode");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `pertanyaan_evaluasi_atasan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik butir pertanyaan evaluasi atasan',
  `kode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik butir pertanyaan (misal: integritas, tingkat_kesiapan_kerja)',
  `aspek` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label aspek penilaian kinerja atau kesiapan alumni',
  `deskripsi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Deskripsi penjelasan operasional aspek yang dinilai',
  `kategori` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Kinerja' COMMENT 'Kategori instrumen: Kesiapan Kerja, Kinerja, dll',
  `tipe` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'likert_5' COMMENT 'Tipe input pertanyaan: likert_5 (Sangat Tinggi s/d Sangat Kurang) atau pilihan_ganda',
  `pilihan_jawaban` json DEFAULT NULL COMMENT 'Daftar opsi pilihan jawaban bila bertipe pilihan_ganda',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Status keaktifan butir instrumen dalam formulir',
  `order` int NOT NULL DEFAULT '1' COMMENT 'Nomor urut penampilan pertanyaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pertanyaan_evaluasi_atasan_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'pertanyaan_evaluasi_atasan' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('pertanyaan_evaluasi_atasan');
    }
};
