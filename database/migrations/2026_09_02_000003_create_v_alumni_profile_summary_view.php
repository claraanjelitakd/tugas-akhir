<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat view ringkasan profil alumni dari sumber data canonical.
 *
 * Wilayah memakai kode natural dari tabel master. Data tempat lahir dan
 * seluruh field sekolah asal tidak termasuk schema aktif.
 */
return new class extends Migration
{
    /** Membuat view ringkasan profil untuk admin, export, dan audit. */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_profile_summary');
        DB::statement(<<<'SQL'
CREATE VIEW v_alumni_profile_summary AS
SELECT
    b.id AS biodata_id, b.user_id, b.nim,
    COALESCE(da.nama, u.name, 'Mahasiswa UKDW') AS nama,
    COALESCE(b.email_pribadi, u.email) AS email,
    COALESCE(b.email_pribadi, u.email) AS email_pribadi,
    b.foto, b.nomor_telepon, da.tanggal_lahir, da.jenis_kelamin, da.warga_negara,
    b.alamat_saat_ini AS alamat, b.kelurahan, b.kecamatan, b.kode_pos,
    b.kode_provinsi, prov.nama_provinsi, b.kode_kabupaten, kab.nama_kabupaten,
    b.instagram_url, b.facebook_url, b.linkedin_url, b.linkedin_username, b.skills, b.experience,
    b.prodi_id, p.kode_prodi, p.nama_prodi, p.fakultas_id, f.kode_fakultas, f.nama_fakultas,
    f.singkatan AS singkatan_fakultas, da.angkatan_masuk, da.tahun_lulus, da.tanggal_lulus,
    da.tahun_akademik_lulus, da.ip_kumulatif AS ipk, da.total_sks, da.total_angka_kualitas,
    da.status_mahasiswa,
    y.judul_ta, y.judul_ta_inggris, y.url_publikasi, y.jenis_publikasi, y.status_publikasi,
    y.keterangan_hasil_yudisium, COALESCE(y.status_lulus, 'Belum') AS status_yudisium,
    b.kategori_pekerjaan, b.posisi_jabatan, b.bidang_pekerjaan, b.divisi, b.posisi_wiraswasta, b.pendidikan_tingkat,
    b.perguruan_tinggi, b.pendidikan_prodi, b.gaji, b.jenis_pekerjaan, b.perusahaan_id,
    c.nama_perusahaan, c.alamat AS alamat_perusahaan, c.sektor AS sektor_perusahaan, c.skala AS skala_perusahaan,
    c.jenis_perusahaan, c.jenis_lokasi AS jenis_lokasi_perusahaan, c.kode_negara AS kode_negara_perusahaan,
    rn.nama_negara AS nama_negara_perusahaan,
    a.nama AS nama_atasan, a.email AS email_atasan, a.telepon AS telepon_atasan,
    CASE WHEN COALESCE(da.nama, u.name) IS NOT NULL AND b.nim IS NOT NULL
        AND b.nomor_telepon IS NOT NULL AND COALESCE(b.email_pribadi, u.email) IS NOT NULL
        THEN 1 ELSE 0 END AS is_profile_complete
FROM biodata b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN data_akademik da ON da.nim = b.nim
LEFT JOIN prodi p ON b.prodi_id = p.id
LEFT JOIN ref_fakultas f ON p.fakultas_id = f.id
LEFT JOIN propinsi prov ON b.kode_provinsi = prov.kode_provinsi
LEFT JOIN kabupaten kab ON b.kode_kabupaten = kab.kode_kabupaten
LEFT JOIN perusahaan c ON b.perusahaan_id = c.id
LEFT JOIN ref_negara rn ON c.kode_negara = rn.kode_negara
LEFT JOIN atasan a ON b.atasan_id = a.id
LEFT JOIN yudisium y ON y.nim = b.nim
SQL);
    }

    /** Menghapus view ringkasan profil saat migration dibatalkan. */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_alumni_profile_summary');
    }
};
