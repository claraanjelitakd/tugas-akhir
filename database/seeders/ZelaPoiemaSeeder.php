<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Data Alumni Zela Poiema Christy Mantohana Napitupulu.
 *
 * Program Studi Sistem Informasi (S1), Fakultas Teknologi Informasi, UKDW.
 * Lulusan tahun 2025 dengan akun awal siap pakai (login menggunakan NIM & password tanggal lahir).
 */
class ZelaPoiemaSeeder extends Seeder
{
    /**
     * Jalankan seeder akun dan master data akademik Zela Poiema.
     */
    public function run(): void
    {
        $nim = '72210501';
        $nama = 'ZELA POIEMA CHRISTY MANTOHANA NAPITUPULU';
        $angkatan = 2021;
        $tahunLulus = 2025;
        $tahunAkademikLulus = 'Genap 2024/2025';
        $tahunAkademikMasuk = '2021/2022 (Semester Ganjil)';
        $emailKampus = '72210501@students.ukdw.ac.id';
        $tanggalLahir = '2006-11-22'; // 22112006
        $passwordPlain = '22112006';

        // Proteksi integritas data: Jika data alumni Zela sudah ada di database,
        // jangan pernah menimpa isian yang telah dimasukkan langsung oleh pengguna.
        if (Biodata::where('nim', $nim)->exists()) {
            return;
        }

        // 1. Ambil Referensi Program Studi Sistem Informasi
        $prodi = Prodi::where('kode_prodi', '72')
            ->orWhere('nama_prodi', 'LIKE', '%Sistem Informasi%')
            ->first();

        // 2. Buat Akun User Alumni (Login menggunakan NIM & password tanggal lahir ddmmyyyy)
        $user = User::updateOrCreate(
            ['username' => $nim],
            [
                'name' => $nama,
                'email' => $emailKampus,
                'password' => Hash::make($passwordPlain),
                'role' => 'alumni',
                'kode_prodi' => $prodi?->kode_prodi,
                'kode_fakultas' => $prodi?->kode_fakultas,
                'must_change_password' => true,
            ]
        );

        // 3. Buat Data Akademik Mahasiswa
        $ipk = 3.75;
        $totalSks = 144;
        $angkaKualitas = round($ipk * $totalSks, 2);

        DataAkademik::updateOrCreate(
            ['nim' => $nim],
            [
                'nama' => $nama,
                'email_pribadi' => $emailKampus,
                'kode_prodi' => $prodi?->kode_prodi,
                'angkatan_masuk' => $angkatan,
                'tahun_akademik_masuk' => $tahunAkademikMasuk,
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => $tahunAkademikLulus,
                'tahun_lulus' => $tahunLulus,
                'tanggal_lulus' => '2025-07-25',
                'ip_kumulatif' => $ipk,
                'total_sks' => $totalSks,
                'total_angka_kualitas' => $angkaKualitas,

                // Identitas Pribadi
                'tanggal_lahir' => $tanggalLahir,
                'jenis_kelamin' => 'Perempuan',
                'warga_negara' => 'WNI',
            ]
        );

        // 4. Buat Data Yudisium (Skripsi Resmi Terdaftar di Repositori UKDW)
        $judulTa = 'Segmentasi Wilayah Potensial Target Promosi Calon Mahasiswa Baru Menggunakan Metode K-Means Clustering (Studi Kasus: Admisi & Promosi Universitas Kristen Duta Wacana)';
        $yudisium = Yudisium::updateOrCreate(
            ['nim' => $nim],
            [
                'judul_ta' => $judulTa,
                'judul_ta_inggris' => 'Segmentation of Potential Target Areas for Promotion of Prospective New Students Using K-Means Clustering Method (Case Study: Admission & Promotion Duta Wacana Christian University)',
                'url_publikasi' => 'https://repository.ukdw.ac.id/view/divisions/sist=5Finformasi/2025.type.html',
                'jenis_publikasi' => 'Skripsi',
                'status_publikasi' => 'Terbit',
                'keterangan_hasil_yudisium' => 'Lulus',
                'status_lulus' => 'Lulus',
            ]
        );

        // 5. Buat Record Biodata Awal (Bersih tanpa isian karier agar siap diisi mandiri)
        Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'kode_prodi' => $prodi?->kode_prodi,
                'email_pribadi' => $emailKampus,
                'nomor_telepon' => null,
                'alamat_saat_ini' => null,
                'kelurahan' => null,
                'kecamatan' => null,
                'kode_kabupaten' => null,
                'kode_provinsi' => null,
                'kode_pos' => null,
                'instagram_url' => null,
                'facebook_url' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/zela-poiema-christy-mantohana-napitupulu',
                'linkedin_username' => 'zela-poiema-christy-mantohana-napitupulu',
                'skills' => null,
                'experience' => null,
                'perusahaan_id' => null,
                'atasan_id' => null,
                'kategori_pekerjaan' => null,
                'posisi_jabatan' => null,
                'posisi_wiraswasta' => null,
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'gaji' => null,
                'jenis_pekerjaan' => null,
            ]
        );
    }
}
