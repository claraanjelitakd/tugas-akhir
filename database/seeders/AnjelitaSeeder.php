<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Data Alumni Anjelita (Sistem Informasi - Angkatan 2023).
 *
 * Menyiapkan akun pengguna dan master data akademik awal dengan password tanggal lahir
 * serta penanda wajib ganti kata sandi pada saat pertama kali masuk.
 */
class AnjelitaSeeder extends Seeder
{
    /**
     * Jalankan seeder akun dan data akademik Anjelita.
     */
    public function run(): void
    {
        $nim = '72230688';
        $nama = 'Anjelita';
        $angkatan = 2023;
        $tahunLulus = 2026;
        $tahunAkademikLulus = 'Genap 2025/2026';
        $tahunAkademikMasuk = '2023/2024 (Semester Ganjil)';
        $emailKampus = '72230688@students.ukdw.ac.id';
        $personalEmail = 'anjelita@students.ukdw.ac.id';
        $tanggalLahir = '2006-11-22'; // 22112006
        $passwordPlain = '22112006';

        // 1. Ambil Data Referensi Prodi Sistem Informasi & Wilayah
        $prodi = Prodi::where('kode_prodi', '72')->orWhere('nama_prodi', 'LIKE', '%Sistem Informasi%')->first();
        $diy = Propinsi::where('kode_provinsi', '34')->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')->first();
        $sleman = Kabupaten::where('kode_kabupaten', '34.04')->orWhere('nama_kabupaten', 'LIKE', '%Sleman%')->first();

        // 2. Buat Akun User Alumni (Login menggunakan NIM / Password tanggal lahir 22112006)
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

        // 3. Buat Data Akademik Mahasiswa (Master Identitas Akademik)
        $ipk = 3.75;
        $totalSks = 144;
        $angkaKualitas = round($ipk * $totalSks, 2);

        $dataAkademik = DataAkademik::updateOrCreate(
            ['nim' => $nim],
            [
                'nama' => $nama,
                'email_pribadi' => $personalEmail,
                'kode_prodi' => $prodi?->kode_prodi,
                'angkatan_masuk' => $angkatan,
                'tahun_akademik_masuk' => $tahunAkademikMasuk,
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => $tahunAkademikLulus,
                'tahun_lulus' => $tahunLulus,
                'tanggal_lulus' => '2026-08-20',
                'ip_kumulatif' => $ipk,
                'total_sks' => $totalSks,
                'total_angka_kualitas' => $angkaKualitas,

                // Identitas Diri
                'tanggal_lahir' => $tanggalLahir,
                'jenis_kelamin' => 'Perempuan',
                'warga_negara' => 'WNI',
            ]
        );

        // 4. Buat Data Yudisium (Status Kelulusan & Skripsi)
        $judulTa = 'Penerapan Sistem Informasi Manajemen Layanan Alumni Universitas Kristen Duta Wacana';
        $yudisium = Yudisium::updateOrCreate(
            ['nim' => $nim],
            [
                'judul_ta' => $judulTa,
                'judul_ta_inggris' => 'Implementation of Alumni Service Management Information System at Duta Wacana Christian University',
                'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                'jenis_publikasi' => 'Skripsi',
                'status_publikasi' => 'Terbit',
                'keterangan_hasil_yudisium' => 'Lulus',
                'status_lulus' => 'Lulus',
            ]
        );

        // 5. Buat Record Biodata Utama Awal (Bersih untuk pengujian pengisian mandiri)
        Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'kode_prodi' => $prodi?->kode_prodi,
                'email_pribadi' => $personalEmail,
                'nomor_telepon' => '081234567888',
                'alamat_saat_ini' => 'Jl. Dr. Wahidin Sudirohusodo No. 5-25',
                'kode_kabupaten' => $sleman?->kode_kabupaten,
                'kode_provinsi' => $diy?->kode_provinsi,
                'kode_pos' => '55222',
                'instagram_url' => null,
                'facebook_url' => null,
                'linkedin_url' => null,
                'linkedin_username' => null,
                'skills' => null,
                'experience' => null,
                'perusahaan_id' => null,
                'atasan_id' => null,
                'kategori_pekerjaan' => null,
                'posisi_jabatan' => null,
                'posisi_wiraswasta' => null,
                'pendidikan_tingkat' => null,
            ]
        );
    }
}
