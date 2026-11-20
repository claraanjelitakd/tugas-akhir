<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\KuesionerJawaban;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Data Alumni Jovan Marllen Yulianto (Lulusan 2026 - Sistem Informasi)
 *
 * Menyiapkan akun dan master data akademik awal untuk pengujian pengisian mandiri.
 */
class JovanMarllenSeeder extends Seeder
{
    /**
     * Jalankan seeder akun dan data akademik Jovan Marllen Yulianto.
     */
    public function run(): void
    {
        $nim = '72230608';
        $nama = 'Jovan Marllen Yulianto';
        $angkatan = 2023;
        $tahunLulus = 2026;
        $tahunAkademikLulus = 'Ganjil 2026/2027';
        $personalEmail = 'jovan.marllen@gmail.com';
        $tanggalLahir = '2004-11-22'; // 22112004

        // 1. Ambil Data Referensi Prodi Sistem Informasi & Wilayah
        $prodi = Prodi::where('kode_prodi', '72')->orWhere('nama_prodi', 'LIKE', '%Sistem Informasi%')->first();
        $diy = Propinsi::where('kode_provinsi', '34')->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')->first();
        $sleman = Kabupaten::where('kode_kabupaten', '34.04')->orWhere('nama_kabupaten', 'LIKE', '%Sleman%')->first();

        // 2. Buat Akun User Alumni (Login menggunakan NIM / Password tanggal lahir 22112004)
        $user = User::updateOrCreate(
            ['username' => $nim],
            [
                'name' => $nama,
                'email' => $personalEmail,
                'password' => Hash::make('22112004'),
                'role' => 'alumni',
                'kode_prodi' => $prodi?->kode_prodi,
                'must_change_password' => true,
            ]
        );

        // 3. Buat Data Akademik Mahasiswa (Master Identitas Akademik)
        $ipk = 3.85;
        $totalSks = 144;
        $angkaKualitas = round($ipk * $totalSks, 2);

        $dataAkademik = DataAkademik::updateOrCreate(
            ['nim' => $nim],
            [
                'nama' => $nama,
                'email_pribadi' => $personalEmail,
                'kode_prodi' => $prodi?->kode_prodi,
                'angkatan_masuk' => $angkatan,
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => $tahunAkademikLulus,
                'tahun_lulus' => $tahunLulus,
                'tanggal_lulus' => '2026-10-25',
                'ip_kumulatif' => $ipk,
                'total_sks' => $totalSks,
                'total_angka_kualitas' => $angkaKualitas,

                // Identitas Diri
                'tanggal_lahir' => $tanggalLahir,
                'jenis_kelamin' => 'Laki-laki',
                'warga_negara' => 'WNI',
            ]
        );

        // 4. Buat Data Yudisium (Status Kelulusan & Skripsi)
        $judulTa = 'Product Requirement Document for an Integrated ERP System for a Soy Sauce Factory';
        $yudisium = Yudisium::updateOrCreate(
            ['nim' => $nim],
            [
                'judul_ta' => $judulTa,
                'judul_ta_inggris' => $judulTa,
                'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                'jenis_publikasi' => 'Skripsi Konseptual',
                'status_publikasi' => 'Terbit',
                'keterangan_hasil_yudisium' => 'Lulus dengan Pujian',
                'status_lulus' => 'Lulus',
            ]
        );

        // 5. Buat Record Biodata Utama Awal (Bersih tanpa data karier agar siap diisi mandiri)
        $biodata = Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'kode_prodi' => $prodi?->kode_prodi,
                'email_pribadi' => $personalEmail,
                'nomor_telepon' => '081234567891',
                'alamat_saat_ini' => 'Jl. Wahidin Sudirohusodo No. 5-25',
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
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'gaji' => null,
                'jenis_pekerjaan' => null,
            ]
        );

        // 6. Bersihkan respon kuesioner agar fresh siap uji
        KuesionerJawaban::where('biodata_id', $biodata->id)->delete();
    }
}
