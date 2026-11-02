<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'data_akademik' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'data_akademik' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "data_akademik" ("id" integer primary key autoincrement not null, "nim" varchar not null, "nama" varchar, "angkatan_masuk" integer, "tempat_lahir" varchar, "tanggal_lahir" date, "jenis_kelamin" varchar check ("jenis_kelamin" in ('Laki-laki', 'Perempuan')), "warga_negara" varchar not null default 'WNI', "email_students" varchar, "asal_sekolah" varchar, "alamat_asal_sekolah" text, "kota_kabupaten_asal_sekolah" varchar, "provinsi_asal_sekolah" varchar, "jurusan_asal_sekolah" varchar, "status_mahasiswa" varchar check ("status_mahasiswa" in ('Lulus', 'Aktif', 'Cuti', 'Drop Out', 'Lainnya', 'AR', 'AK', 'AS', 'L')) not null default 'Lulus', "tahun_akademik_lulus" varchar, "tahun_lulus" integer, "tanggal_lulus" date, "ip_kumulatif" numeric, "total_sks" integer, "total_angka_kualitas" numeric, "created_at" datetime, "updated_at" datetime);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "data_akademik_nim_unique" on "data_akademik" ("nim");
SQL,
                <<<'SQL'
CREATE INDEX "data_akademik_tahun_lulus_index" on "data_akademik" ("tahun_lulus");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `data_akademik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik rekam data akademik',
  `nim` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor Induk Mahasiswa (8 karakter unik)',
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama lengkap mahasiswa',
  `angkatan_masuk` year DEFAULT NULL COMMENT 'Tahun angkatan masuk perkuliahan (YYYY)',
  `tempat_lahir` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kota/kabupaten tempat lahir',
  `tanggal_lahir` date DEFAULT NULL COMMENT 'Tanggal lahir',
  `jenis_kelamin` enum('Laki-laki','Perempuan') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jenis kelamin',
  `warga_negara` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WNI' COMMENT 'Kewarganegaraan (WNI/WNA)',
  `email_students` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alamat email resmi kampus (@students.ukdw.ac.id)',
  `asal_sekolah` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama sekolah menengah asal',
  `alamat_asal_sekolah` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Alamat lengkap sekolah menengah asal',
  `kota_kabupaten_asal_sekolah` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kota/kabupaten sekolah asal',
  `provinsi_asal_sekolah` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Provinsi sekolah asal',
  `jurusan_asal_sekolah` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jurusan sekolah asal (IPA, IPS, SMK, dll)',
  `status_mahasiswa` enum('Lulus','Aktif','Cuti','Drop Out','Lainnya','AR','AK','AS','L') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Lulus' COMMENT 'Status akademik mahasiswa',
  `tahun_akademik_lulus` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Periode lulus akademik, contoh: Gasal 2024/2025',
  `tahun_lulus` year DEFAULT NULL COMMENT 'Tahun kelulusan resmi mahasiswa (YYYY)',
  `tanggal_lulus` date DEFAULT NULL COMMENT 'Tanggal resmi dinyatakan lulus akademik',
  `ip_kumulatif` decimal(3,2) DEFAULT NULL COMMENT 'Indeks Prestasi Kumulatif (IPK)',
  `total_sks` smallint DEFAULT NULL COMMENT 'Total SKS kelulusan yang diselesaikan',
  `total_angka_kualitas` decimal(8,2) DEFAULT NULL COMMENT 'Total angka kualitas kelulusan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_akademik_nim_unique` (`nim`),
  KEY `data_akademik_tahun_lulus_index` (`tahun_lulus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sumber resmi identitas akademik dan kelulusan.';
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'data_akademik' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_akademik');
    }
};
