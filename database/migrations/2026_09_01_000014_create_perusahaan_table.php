<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'perusahaan' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'perusahaan' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "perusahaan" ("id" integer primary key autoincrement not null, "nama_perusahaan" varchar not null, "propinsi_id" integer, "kabupaten_id" integer, "alamat" text, "homepage" varchar, "no_telp_fax" varchar, "kode_pos" varchar, "sektor" varchar, "skala" varchar check ("skala" in ('Lokal', 'Regional/Lokal', 'Nasional', 'Internasional')) not null default 'Nasional', "bentuk_perusahaan" varchar, "jumlah_pegawai" varchar, "status_verifikasi" varchar check ("status_verifikasi" in ('Menunggu Verifikasi', 'Terverifikasi', 'Ditolak')) not null default 'Menunggu Verifikasi', "jenis_lokasi" varchar check ("jenis_lokasi" in ('Dalam Negeri', 'Luar Negeri')) not null default 'Dalam Negeri', "negara" varchar not null default 'Indonesia', "jenis_perusahaan" varchar, "jenis_perusahaan_lainnya" text, "created_by_user_id" integer, "created_by_prodi_id" integer, "created_at" datetime, "updated_at" datetime, foreign key("propinsi_id") references "propinsi"("id") on delete set null, foreign key("kabupaten_id") references "kabupaten"("id") on delete set null, foreign key("created_by_user_id") references "users"("id") on delete set null);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE INDEX "perusahaan_created_by_prodi_id_index" on "perusahaan" ("created_by_prodi_id");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `perusahaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik master perusahaan',
  `nama_perusahaan` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama resmi perusahaan / lembaga / institusi',
  `propinsi_id` bigint unsigned DEFAULT NULL,
  `kabupaten_id` bigint unsigned DEFAULT NULL,
  `alamat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Alamat lengkap jalan atau gedung kantor perusahaan',
  `homepage` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Website / URL homepage resmi instansi',
  `no_telp_fax` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor telepon atau fax resmi instansi',
  `kode_pos` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode pos kantor instansi (hingga 10 karakter untuk domestik/internasional)',
  `sektor` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sektor bidang industri / usaha',
  `skala` enum('Lokal','Regional/Lokal','Nasional','Internasional') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nasional' COMMENT 'Skala usaha: Lokal, Regional/Lokal, Nasional, Internasional',
  `bentuk_perusahaan` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bentuk instansi: BUMN, Perusahaan Terbatas, Koperasi, CV, Firma',
  `jumlah_pegawai` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rentang jumlah pegawai keseluruhan: < 50 Orang, 51 - 100 Orang, dll',
  `status_verifikasi` enum('Menunggu Verifikasi','Terverifikasi','Ditolak') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Menunggu Verifikasi' COMMENT 'Status verifikasi data instansi oleh admin',
  `jenis_lokasi` enum('Dalam Negeri','Luar Negeri') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dalam Negeri' COMMENT 'Lokasi operasional instansi (Dalam Negeri / Luar Negeri)',
  `negara` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Indonesia' COMMENT 'Negara kedudukan instansi',
  `jenis_perusahaan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kategori Dikti F11: Instansi pemerintah, BUMN, Swasta, dll',
  `jenis_perusahaan_lainnya` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Keterangan jenis instansi jika opsi lainnya',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_by_prodi_id` bigint unsigned DEFAULT NULL COMMENT 'Prodi asal pengusul record perusahaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `perusahaan_propinsi_id_foreign` (`propinsi_id`),
  KEY `perusahaan_kabupaten_id_foreign` (`kabupaten_id`),
  KEY `perusahaan_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `perusahaan_created_by_prodi_id_index` (`created_by_prodi_id`),
  CONSTRAINT `perusahaan_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `perusahaan_kabupaten_id_foreign` FOREIGN KEY (`kabupaten_id`) REFERENCES `kabupaten` (`id`) ON DELETE SET NULL,
  CONSTRAINT `perusahaan_propinsi_id_foreign` FOREIGN KEY (`propinsi_id`) REFERENCES `propinsi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'perusahaan' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('perusahaan');
    }
};
