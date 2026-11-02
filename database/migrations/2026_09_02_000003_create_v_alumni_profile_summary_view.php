<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_alumni_profile_summary' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi MySQL dan SQLite dipertahankan dalam satu migration karena sintaks
 * fungsi JSON, konkatenasi, dan casting berbeda antar driver database.
 */
return new class extends Migration
{
    /**
     * Membuat view 'v_alumni_profile_summary' setelah tabel sumber tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_profile_summary');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_alumni_profile_summary AS SELECT b.id AS biodata_id, b.user_id, b.nim,
    COALESCE(da.nama, u.name, 'Mahasiswa UKDW') AS nama,
    COALESCE(b.email_pribadi, u.email) AS email,
    COALESCE(b.email_pribadi, u.email) AS email_pribadi,
    da.email_students, b.nomor_telepon,
    da.tempat_lahir, da.tanggal_lahir, da.jenis_kelamin, da.warga_negara,
    b.alamat_saat_ini AS alamat, b.kelurahan, b.kecamatan, b.kode_pos,
    b.propinsi_id, prov.nama_provinsi, b.kabupaten_id, kab.nama_kabupaten,
    b.instagram_url, b.facebook_url, b.linkedin_url, b.linkedin_username, b.skills, b.experience,
    b.prodi_id, p.kode_prodi, p.nama_prodi, p.fakultas_id, f.kode_fakultas, f.nama_fakultas,
    f.singkatan AS singkatan_fakultas, da.angkatan_masuk, da.tahun_lulus, da.tanggal_lulus,
    da.tahun_akademik_lulus, da.ip_kumulatif AS ipk, da.total_sks, da.total_angka_kualitas,
    da.status_mahasiswa, da.asal_sekolah, da.alamat_asal_sekolah, da.kota_kabupaten_asal_sekolah,
    da.provinsi_asal_sekolah, da.jurusan_asal_sekolah,
    y.judul_ta, y.judul_ta_inggris, y.url_publikasi, y.jenis_publikasi, y.status_publikasi,
    y.keterangan_hasil_yudisium, COALESCE(y.status_lulus, 'Belum') AS status_yudisium,
    b.kategori_pekerjaan, b.posisi_jabatan, b.posisi_wiraswasta, b.pendidikan_tingkat,
    b.perguruan_tinggi, b.pendidikan_prodi, b.gaji, b.jenis_pekerjaan, b.perusahaan_id,
    c.nama_perusahaan, c.alamat AS alamat_perusahaan, c.sektor AS sektor_perusahaan, c.skala AS skala_perusahaan,
    c.jenis_perusahaan, c.jenis_lokasi AS jenis_lokasi_perusahaan, c.negara AS negara_perusahaan,
    a.nama AS nama_atasan, a.email AS email_atasan, a.telepon AS telepon_atasan,
    CASE WHEN COALESCE(da.nama, u.name) IS NOT NULL AND b.nim IS NOT NULL
        AND b.nomor_telepon IS NOT NULL AND COALESCE(b.email_pribadi, u.email) IS NOT NULL THEN 1 ELSE 0 END AS is_profile_complete
FROM biodata b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN data_akademik da ON da.nim = b.nim
LEFT JOIN prodi p ON b.prodi_id = p.id
LEFT JOIN ref_fakultas f ON p.fakultas_id = f.id
LEFT JOIN propinsi prov ON b.propinsi_id = prov.id
LEFT JOIN kabupaten kab ON b.kabupaten_id = kab.id
LEFT JOIN perusahaan c ON b.perusahaan_id = c.id
LEFT JOIN atasan a ON b.atasan_id = a.id
LEFT JOIN yudisium y ON y.nim = b.nim;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_profile_summary` AS select `b`.`id` AS `biodata_id`,`b`.`user_id` AS `user_id`,`b`.`nim` AS `nim`,coalesce(`da`.`nama`,`u`.`name`,'Mahasiswa UKDW') AS `nama`,coalesce(`b`.`email_pribadi`,`u`.`email`) AS `email`,coalesce(`b`.`email_pribadi`,`u`.`email`) AS `email_pribadi`,`da`.`email_students` AS `email_students`,`b`.`nomor_telepon` AS `nomor_telepon`,`da`.`tempat_lahir` AS `tempat_lahir`,`da`.`tanggal_lahir` AS `tanggal_lahir`,`da`.`jenis_kelamin` AS `jenis_kelamin`,`da`.`warga_negara` AS `warga_negara`,`b`.`alamat_saat_ini` AS `alamat`,`b`.`kelurahan` AS `kelurahan`,`b`.`kecamatan` AS `kecamatan`,`b`.`kode_pos` AS `kode_pos`,`b`.`propinsi_id` AS `propinsi_id`,`prov`.`nama_provinsi` AS `nama_provinsi`,`b`.`kabupaten_id` AS `kabupaten_id`,`kab`.`nama_kabupaten` AS `nama_kabupaten`,`b`.`instagram_url` AS `instagram_url`,`b`.`facebook_url` AS `facebook_url`,`b`.`linkedin_url` AS `linkedin_url`,`b`.`linkedin_username` AS `linkedin_username`,`b`.`skills` AS `skills`,`b`.`experience` AS `experience`,`b`.`prodi_id` AS `prodi_id`,`p`.`kode_prodi` AS `kode_prodi`,`p`.`nama_prodi` AS `nama_prodi`,`p`.`fakultas_id` AS `fakultas_id`,`f`.`kode_fakultas` AS `kode_fakultas`,`f`.`nama_fakultas` AS `nama_fakultas`,`f`.`singkatan` AS `singkatan_fakultas`,`da`.`angkatan_masuk` AS `angkatan_masuk`,`da`.`tahun_lulus` AS `tahun_lulus`,`da`.`tanggal_lulus` AS `tanggal_lulus`,`da`.`tahun_akademik_lulus` AS `tahun_akademik_lulus`,`da`.`ip_kumulatif` AS `ipk`,`da`.`total_sks` AS `total_sks`,`da`.`total_angka_kualitas` AS `total_angka_kualitas`,`da`.`status_mahasiswa` AS `status_mahasiswa`,`da`.`asal_sekolah` AS `asal_sekolah`,`da`.`alamat_asal_sekolah` AS `alamat_asal_sekolah`,`da`.`kota_kabupaten_asal_sekolah` AS `kota_kabupaten_asal_sekolah`,`da`.`provinsi_asal_sekolah` AS `provinsi_asal_sekolah`,`da`.`jurusan_asal_sekolah` AS `jurusan_asal_sekolah`,`y`.`judul_ta` AS `judul_ta`,`y`.`judul_ta_inggris` AS `judul_ta_inggris`,`y`.`url_publikasi` AS `url_publikasi`,`y`.`jenis_publikasi` AS `jenis_publikasi`,`y`.`status_publikasi` AS `status_publikasi`,`y`.`keterangan_hasil_yudisium` AS `keterangan_hasil_yudisium`,coalesce(`y`.`status_lulus`,'Belum') AS `status_yudisium`,`b`.`kategori_pekerjaan` AS `kategori_pekerjaan`,`b`.`posisi_jabatan` AS `posisi_jabatan`,`b`.`posisi_wiraswasta` AS `posisi_wiraswasta`,`b`.`pendidikan_tingkat` AS `pendidikan_tingkat`,`b`.`perguruan_tinggi` AS `perguruan_tinggi`,`b`.`pendidikan_prodi` AS `pendidikan_prodi`,`b`.`gaji` AS `gaji`,`b`.`jenis_pekerjaan` AS `jenis_pekerjaan`,`b`.`perusahaan_id` AS `perusahaan_id`,`c`.`nama_perusahaan` AS `nama_perusahaan`,`c`.`alamat` AS `alamat_perusahaan`,`c`.`sektor` AS `sektor_perusahaan`,`c`.`skala` AS `skala_perusahaan`,`c`.`jenis_perusahaan` AS `jenis_perusahaan`,`c`.`jenis_lokasi` AS `jenis_lokasi_perusahaan`,`c`.`negara` AS `negara_perusahaan`,`a`.`nama` AS `nama_atasan`,`a`.`email` AS `email_atasan`,`a`.`telepon` AS `telepon_atasan`,(case when ((coalesce(`da`.`nama`,`u`.`name`) is not null) and (`b`.`nim` is not null) and (`b`.`nomor_telepon` is not null) and (coalesce(`b`.`email_pribadi`,`u`.`email`) is not null)) then 1 else 0 end) AS `is_profile_complete` from (((((((((`biodata` `b` left join `users` `u` on((`b`.`user_id` = `u`.`id`))) left join `data_akademik` `da` on((`da`.`nim` = `b`.`nim`))) left join `prodi` `p` on((`b`.`prodi_id` = `p`.`id`))) left join `ref_fakultas` `f` on((`p`.`fakultas_id` = `f`.`id`))) left join `propinsi` `prov` on((`b`.`propinsi_id` = `prov`.`id`))) left join `kabupaten` `kab` on((`b`.`kabupaten_id` = `kab`.`id`))) left join `perusahaan` `c` on((`b`.`perusahaan_id` = `c`.`id`))) left join `atasan` `a` on((`b`.`atasan_id` = `a`.`id`))) left join `yudisium` `y` on((`y`.`nim` = `b`.`nim`)));
SQL
            );
        }
    }

    /**
     * Menghapus view 'v_alumni_profile_summary' saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_profile_summary');
    }
};
