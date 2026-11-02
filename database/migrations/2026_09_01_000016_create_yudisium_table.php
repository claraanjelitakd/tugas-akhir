<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'yudisium' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'yudisium' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "yudisium" ("id" integer primary key autoincrement not null, "nim" varchar not null, "judul_ta" text, "judul_ta_inggris" text, "url_publikasi" varchar, "jenis_publikasi" varchar, "status_publikasi" varchar, "keterangan_hasil_yudisium" varchar, "status_lulus" varchar check ("status_lulus" in ('Belum', 'Proses', 'Lulus', 'Tidak Lulus')) not null default 'Belum', "created_at" datetime, "updated_at" datetime, foreign key("nim") references "data_akademik"("nim") on delete cascade);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "yudisium_nim_unique" on "yudisium" ("nim");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `yudisium` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik rekam kelulusan yudisium',
  `nim` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NIM mahasiswa (8 karakter, FK ke data_akademik)',
  `judul_ta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Judul Skripsi/Thesis/Disertasi/Perancangan',
  `judul_ta_inggris` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Judul Skripsi/Thesis dalam Bahasa Inggris',
  `url_publikasi` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tautan repositori / jurnal publikasi karya ilmiah',
  `jenis_publikasi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kategori media publikasi ilmiah',
  `status_publikasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Status publikasi (Published, Submitted, In Review)',
  `keterangan_hasil_yudisium` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Predikat yudisium: Lulus dengan Pujian, Sangat Memuaskan',
  `status_lulus` enum('Belum','Proses','Lulus','Tidak Lulus') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Belum' COMMENT 'Status kelulusan yudisium mahasiswa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `yudisium_nim_unique` (`nim`),
  CONSTRAINT `yudisium_nim_foreign` FOREIGN KEY (`nim`) REFERENCES `data_akademik` (`nim`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sumber resmi kelulusan, tugas akhir, publikasi, dan predikat.';
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'yudisium' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('yudisium');
    }
};
