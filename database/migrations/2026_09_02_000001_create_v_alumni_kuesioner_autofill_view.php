<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat database view 'v_alumni_kuesioner_autofill' untuk pembacaan data teragregasi aplikasi.
 *
 * Definisi MySQL dan SQLite dipertahankan dalam satu migration karena sintaks
 * fungsi JSON, konkatenasi, dan casting berbeda antar driver database.
 */
return new class extends Migration
{
    /**
     * Membuat view 'v_alumni_kuesioner_autofill' setelah tabel sumber tersedia.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_kuesioner_autofill');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_alumni_kuesioner_autofill AS SELECT u.id AS user_id, b.id AS biodata_id, b.nim AS F1, COALESCE(da.nama, u.name) AS F2A, b.nomor_telepon AS F2B, COALESCE(b.email_pribadi, u.email) AS F2C, b.alamat_saat_ini AS F2D, p.nama_perusahaan AS F2E, a.nama AS F2E1, a.telepon AS F2E2, a.email AS F2E3, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(p.negara || ', ', '') || COALESCE(p.kode_pos, ''), ', ') ELSE TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(kab.nama_kabupaten || ', ', '') || COALESCE(prov.nama_provinsi || ', ', '') || COALESCE(p.kode_pos, ''), ', ') END AS F2F, b.posisi_jabatan AS F2G, p.skala AS F2H, p.nama_perusahaan AS F5B, CASE WHEN b.kategori_pekerjaan = 'Wiraswasta' THEN b.posisi_wiraswasta ELSE NULL END AS F5C, p.skala AS F5D, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(p.negara || ', ', '') || COALESCE(p.kode_pos, ''), ', ') ELSE TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(kab.nama_kabupaten || ', ', '') || COALESCE(prov.nama_provinsi || ', ', '') || COALESCE(p.kode_pos, ''), ', ') END AS F510, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN p.negara ELSE prov.nama_provinsi END AS F5a1, CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN p.negara ELSE kab.nama_kabupaten END AS F5a2, p.jenis_perusahaan AS F11, CAST(b.gaji AS TEXT) AS F505, b.kategori_pekerjaan AS F8, b.pendidikan_tingkat AS BIO_PENDIDIKAN_TINGKAT, b.perguruan_tinggi AS F18B, b.pendidikan_prodi AS F18C, da.tempat_lahir AS BIO_TEMPAT_LAHIR, da.tanggal_lahir AS BIO_TANGGAL_LAHIR, da.jenis_kelamin AS BIO_JK, da.tanggal_lulus AS BIO_TGL_LULUS, y.judul_ta AS BIO_JUDUL_TA FROM users u LEFT JOIN biodata b ON b.user_id = u.id LEFT JOIN data_akademik da ON da.nim = b.nim LEFT JOIN perusahaan p ON p.id = b.perusahaan_id LEFT JOIN atasan a ON a.id = b.atasan_id LEFT JOIN yudisium y ON y.nim = b.nim LEFT JOIN propinsi prov ON prov.id = p.propinsi_id LEFT JOIN kabupaten kab ON kab.id = p.kabupaten_id;
SQL
            );
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_kuesioner_autofill` AS select `u`.`id` AS `user_id`,`b`.`id` AS `biodata_id`,`b`.`nim` AS `F1`,coalesce(`da`.`nama`,`u`.`name`) AS `F2A`,`b`.`nomor_telepon` AS `F2B`,coalesce(`b`.`email_pribadi`,`u`.`email`) AS `F2C`,`b`.`alamat_saat_ini` AS `F2D`,`p`.`nama_perusahaan` AS `F2E`,`a`.`nama` AS `F2E1`,`a`.`telepon` AS `F2E2`,`a`.`email` AS `F2E3`,(case when (`p`.`jenis_lokasi` = 'Luar Negeri') then concat_ws(', ',`p`.`alamat`,`p`.`negara`,`p`.`kode_pos`) else concat_ws(', ',`p`.`alamat`,`kab`.`nama_kabupaten`,`prov`.`nama_provinsi`,`p`.`kode_pos`) end) AS `F2F`,`b`.`posisi_jabatan` AS `F2G`,`p`.`skala` AS `F2H`,`p`.`nama_perusahaan` AS `F5B`,(case when (`b`.`kategori_pekerjaan` = 'Wiraswasta') then `b`.`posisi_wiraswasta` else NULL end) AS `F5C`,`p`.`skala` AS `F5D`,(case when (`p`.`jenis_lokasi` = 'Luar Negeri') then concat_ws(', ',`p`.`alamat`,`p`.`negara`,`p`.`kode_pos`) else concat_ws(', ',`p`.`alamat`,`kab`.`nama_kabupaten`,`prov`.`nama_provinsi`,`p`.`kode_pos`) end) AS `F510`,(case when (`p`.`jenis_lokasi` = 'Luar Negeri') then `p`.`negara` else `prov`.`nama_provinsi` end) AS `F5a1`,(case when (`p`.`jenis_lokasi` = 'Luar Negeri') then `p`.`negara` else `kab`.`nama_kabupaten` end) AS `F5a2`,`p`.`jenis_perusahaan` AS `F11`,cast(`b`.`gaji` as char charset utf8mb4) AS `F505`,`b`.`kategori_pekerjaan` AS `F8`,`b`.`pendidikan_tingkat` AS `BIO_PENDIDIKAN_TINGKAT`,`b`.`perguruan_tinggi` AS `F18B`,`b`.`pendidikan_prodi` AS `F18C`,`da`.`tempat_lahir` AS `BIO_TEMPAT_LAHIR`,`da`.`tanggal_lahir` AS `BIO_TANGGAL_LAHIR`,`da`.`jenis_kelamin` AS `BIO_JK`,`da`.`tanggal_lulus` AS `BIO_TGL_LULUS`,`y`.`judul_ta` AS `BIO_JUDUL_TA` from (((((((`users` `u` left join `biodata` `b` on((`b`.`user_id` = `u`.`id`))) left join `data_akademik` `da` on((`da`.`nim` = `b`.`nim`))) left join `perusahaan` `p` on((`p`.`id` = `b`.`perusahaan_id`))) left join `atasan` `a` on((`a`.`id` = `b`.`atasan_id`))) left join `yudisium` `y` on((`y`.`nim` = `b`.`nim`))) left join `propinsi` `prov` on((`prov`.`id` = `p`.`propinsi_id`))) left join `kabupaten` `kab` on((`kab`.`id` = `p`.`kabupaten_id`)));
SQL
            );
        }
    }

    /**
     * Menghapus view 'v_alumni_kuesioner_autofill' saat migration dibatalkan.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_kuesioner_autofill');
    }
};
