<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view autofill kuesioner dari profil alumni dan referensi wilayah.
 */
return new class extends Migration
{
    /**
     * Membuat view portable untuk SQLite dan MySQL.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_kuesioner_autofill');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE VIEW v_alumni_kuesioner_autofill AS
SELECT u.id AS user_id, b.id AS biodata_id, b.nim AS F1,
    COALESCE(da.nama, u.name) AS F2A, b.nomor_telepon AS F2B,
    COALESCE(b.email_pribadi, u.email) AS F2C, b.alamat_saat_ini AS F2D,
    p.nama_perusahaan AS F2E, a.nama AS F2E1, a.telepon AS F2E2, a.email AS F2E3,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri'
        THEN TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(rn.nama_negara || ', ', '') || COALESCE(p.kode_pos, ''), ', ')
        ELSE TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(kab.nama_kabupaten || ', ', '') || COALESCE(prov.nama_provinsi || ', ', '') || COALESCE(p.kode_pos, ''), ', ')
    END AS F2F,
    b.posisi_jabatan AS F2G, p.skala AS F2H, p.nama_perusahaan AS F5B,
    CASE WHEN b.kategori_pekerjaan = 'Wiraswasta' THEN b.posisi_wiraswasta ELSE NULL END AS F5C,
    p.skala AS F5D,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri'
        THEN TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(rn.nama_negara || ', ', '') || COALESCE(p.kode_pos, ''), ', ')
        ELSE TRIM(COALESCE(p.alamat || ', ', '') || COALESCE(kab.nama_kabupaten || ', ', '') || COALESCE(prov.nama_provinsi || ', ', '') || COALESCE(p.kode_pos, ''), ', ')
    END AS F510,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN rn.nama_negara ELSE prov.nama_provinsi END AS F5a1,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN rn.nama_negara ELSE kab.nama_kabupaten END AS F5a2,
    p.jenis_perusahaan AS F11, CAST(b.gaji AS TEXT) AS F505, b.kategori_pekerjaan AS F8,
    b.pendidikan_tingkat AS BIO_PENDIDIKAN_TINGKAT, b.perguruan_tinggi AS F18B,
    b.pendidikan_prodi AS F18C, da.tanggal_lahir AS BIO_TANGGAL_LAHIR,
    da.jenis_kelamin AS BIO_JK, da.tanggal_lulus AS BIO_TGL_LULUS,
    y.judul_ta AS BIO_JUDUL_TA
FROM users u
LEFT JOIN biodata b ON b.user_id = u.id
LEFT JOIN data_akademik da ON da.nim = b.nim
LEFT JOIN perusahaan p ON p.id = b.perusahaan_id
LEFT JOIN atasan a ON a.id = b.atasan_id
LEFT JOIN yudisium y ON y.nim = b.nim
LEFT JOIN ref_negara rn ON rn.kode_negara = p.kode_negara
LEFT JOIN propinsi prov ON prov.kode_provinsi = p.kode_provinsi
LEFT JOIN kabupaten kab ON kab.kode_kabupaten = p.kode_kabupaten;
SQL);
        } else {
            DB::statement(<<<'SQL'
CREATE VIEW `v_alumni_kuesioner_autofill` AS
SELECT u.id AS user_id, b.id AS biodata_id, b.nim AS F1,
    COALESCE(da.nama, u.name) AS F2A, b.nomor_telepon AS F2B,
    COALESCE(b.email_pribadi, u.email) AS F2C, b.alamat_saat_ini AS F2D,
    p.nama_perusahaan AS F2E, a.nama AS F2E1, a.telepon AS F2E2, a.email AS F2E3,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN CONCAT_WS(', ', p.alamat, rn.nama_negara, p.kode_pos)
        ELSE CONCAT_WS(', ', p.alamat, kab.nama_kabupaten, prov.nama_provinsi, p.kode_pos) END AS F2F,
    b.posisi_jabatan AS F2G, p.skala AS F2H, p.nama_perusahaan AS F5B,
    CASE WHEN b.kategori_pekerjaan = 'Wiraswasta' THEN b.posisi_wiraswasta ELSE NULL END AS F5C,
    p.skala AS F5D,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN CONCAT_WS(', ', p.alamat, rn.nama_negara, p.kode_pos)
        ELSE CONCAT_WS(', ', p.alamat, kab.nama_kabupaten, prov.nama_provinsi, p.kode_pos) END AS F510,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN rn.nama_negara ELSE prov.nama_provinsi END AS F5a1,
    CASE WHEN p.jenis_lokasi = 'Luar Negeri' THEN rn.nama_negara ELSE kab.nama_kabupaten END AS F5a2,
    p.jenis_perusahaan AS F11, CAST(b.gaji AS CHAR) AS F505, b.kategori_pekerjaan AS F8,
    b.pendidikan_tingkat AS BIO_PENDIDIKAN_TINGKAT, b.perguruan_tinggi AS F18B,
    b.pendidikan_prodi AS F18C, da.tanggal_lahir AS BIO_TANGGAL_LAHIR,
    da.jenis_kelamin AS BIO_JK, da.tanggal_lulus AS BIO_TGL_LULUS,
    y.judul_ta AS BIO_JUDUL_TA
FROM users u
LEFT JOIN biodata b ON b.user_id = u.id
LEFT JOIN data_akademik da ON da.nim = b.nim
LEFT JOIN perusahaan p ON p.id = b.perusahaan_id
LEFT JOIN atasan a ON a.id = b.atasan_id
LEFT JOIN yudisium y ON y.nim = b.nim
LEFT JOIN ref_negara rn ON rn.kode_negara = p.kode_negara
LEFT JOIN propinsi prov ON prov.kode_provinsi = p.kode_provinsi
LEFT JOIN kabupaten kab ON kab.kode_kabupaten = p.kode_kabupaten;
SQL);
        }
    }

    /**
     * Menghapus view autofill.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_kuesioner_autofill');
    }
};
