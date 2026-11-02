<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat tabel 'biodata' sebagai bagian dari schema aktif aplikasi.
 *
 * Struktur MySQL dan SQLite disimpan di migration yang sama agar fresh install,
 * pengujian, dan runtime memakai kontrak kolom serta indeks yang identik.
 */
return new class extends Migration
{
    /**
     * Membuat tabel 'biodata' beserta indeks SQLite yang diperlukan.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TABLE "biodata" ("id" integer primary key autoincrement not null, "user_id" integer not null, "nim" varchar not null, "yudisium_id" integer, "prodi_id" integer, "foto" varchar, "nomor_telepon" varchar, "email_pribadi" varchar, "alamat_saat_ini" text, "kelurahan" varchar, "kecamatan" varchar, "kabupaten_id" integer, "propinsi_id" integer, "kode_pos" varchar, "instagram_url" varchar, "facebook_url" varchar, "linkedin_url" varchar, "linkedin_username" varchar, "skills" text, "experience" text, "perusahaan_id" integer, "atasan_id" integer, "kategori_pekerjaan" varchar, "posisi_jabatan" varchar, "posisi_wiraswasta" varchar, "pendidikan_tingkat" varchar, "perguruan_tinggi" varchar, "pendidikan_prodi" varchar, "gaji" numeric, "jenis_pekerjaan" varchar, "created_at" datetime, "updated_at" datetime, "bidang_pekerjaan" varchar, foreign key("user_id") references "users"("id") on delete cascade, foreign key("nim") references "data_akademik"("nim") on delete cascade, foreign key("yudisium_id") references "yudisium"("id") on delete set null, foreign key("prodi_id") references "prodi"("id") on delete set null, foreign key("kabupaten_id") references "kabupaten"("id") on delete set null, foreign key("propinsi_id") references "propinsi"("id") on delete set null, foreign key("perusahaan_id") references "perusahaan"("id") on delete set null, foreign key("atasan_id") references "atasan"("id") on delete set null);
SQL
            );

            foreach ([
                <<<'SQL'
CREATE UNIQUE INDEX "biodata_nim_unique" on "biodata" ("nim");
SQL,
            ] as $indexSql) {
                DB::statement($indexSql);
            }
        } else {
            DB::unprepared(<<<'SQL'
CREATE TABLE `biodata` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID unik profil biodata alumni',
  `user_id` bigint unsigned NOT NULL,
  `nim` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NIM mahasiswa/alumni (8 karakter, FK ke data_akademik)',
  `yudisium_id` bigint unsigned DEFAULT NULL,
  `prodi_id` bigint unsigned DEFAULT NULL,
  `foto` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path berkas foto profil alumni',
  `nomor_telepon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor telepon/WhatsApp aktif alumni saat ini',
  `email_pribadi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alamat email aktif korespondensi alumni',
  `alamat_saat_ini` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Alamat domisili tempat tinggal alumni saat ini',
  `kelurahan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kelurahan/desa domisili alumni saat ini',
  `kecamatan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kecamatan domisili alumni saat ini',
  `kabupaten_id` bigint unsigned DEFAULT NULL,
  `propinsi_id` bigint unsigned DEFAULT NULL,
  `kode_pos` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode pos tempat tinggal alumni saat ini (5 digit)',
  `instagram_url` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tautan profil Instagram',
  `facebook_url` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tautan profil Facebook',
  `linkedin_url` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tautan profil LinkedIn',
  `linkedin_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Username akun LinkedIn alumni',
  `skills` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Daftar keahlian / skills spesifik alumni',
  `experience` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Pengalaman kerja / riwayat karier alumni',
  `perusahaan_id` bigint unsigned DEFAULT NULL,
  `atasan_id` bigint unsigned DEFAULT NULL,
  `kategori_pekerjaan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kategori aktivitas karier: Bekerja, Wiraswasta, Melanjutkan Pendidikan, dll',
  `posisi_jabatan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jabatan atau posisi pekerjaan alumni',
  `bidang_pekerjaan` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Bidang pekerjaan atau divisi alumni',
  `posisi_wiraswasta` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jenis usaha atau posisi wirausaha alumni',
  `pendidikan_tingkat` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jenjang studi lanjut: S2, S3, Profesi, Spesialis',
  `perguruan_tinggi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama perguruan tinggi studi lanjut',
  `pendidikan_prodi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Program studi pada jenjang studi lanjut',
  `gaji` decimal(15,2) DEFAULT NULL COMMENT 'Nominal gaji / penghasilan pertama per bulan',
  `jenis_pekerjaan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jenis atau klasifikasi bidang pekerjaan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `biodata_nim_unique` (`nim`),
  KEY `biodata_user_id_foreign` (`user_id`),
  KEY `biodata_yudisium_id_foreign` (`yudisium_id`),
  KEY `biodata_prodi_id_foreign` (`prodi_id`),
  KEY `biodata_kabupaten_id_foreign` (`kabupaten_id`),
  KEY `biodata_propinsi_id_foreign` (`propinsi_id`),
  KEY `biodata_perusahaan_id_foreign` (`perusahaan_id`),
  KEY `biodata_atasan_id_foreign` (`atasan_id`),
  CONSTRAINT `biodata_atasan_id_foreign` FOREIGN KEY (`atasan_id`) REFERENCES `atasan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `biodata_kabupaten_id_foreign` FOREIGN KEY (`kabupaten_id`) REFERENCES `kabupaten` (`id`) ON DELETE SET NULL,
  CONSTRAINT `biodata_nim_foreign` FOREIGN KEY (`nim`) REFERENCES `data_akademik` (`nim`) ON DELETE CASCADE,
  CONSTRAINT `biodata_perusahaan_id_foreign` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `biodata_prodi_id_foreign` FOREIGN KEY (`prodi_id`) REFERENCES `prodi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `biodata_propinsi_id_foreign` FOREIGN KEY (`propinsi_id`) REFERENCES `propinsi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `biodata_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `biodata_yudisium_id_foreign` FOREIGN KEY (`yudisium_id`) REFERENCES `yudisium` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profil tracer alumni; kontak, domisili, skills, dan experience adalah field aktif.';
SQL
            );
        }
    }

    /**
     * Menghapus tabel 'biodata' saat migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('biodata');
    }
};
