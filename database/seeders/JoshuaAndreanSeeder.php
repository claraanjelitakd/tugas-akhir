<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Prodi;
use App\Models\ProdiResponse;
use App\Models\Propinsi;
use App\Models\Tracer;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Data Alumni Joshua Andrean (Lulusan 2026 - Sistem Informasi)
 *
 * Menyiapkan akun dan master data akademik untuk pengujian pengisian mandiri.
 */
class JoshuaAndreanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nim = '72220001';
        $nama = 'Joshua Andrean';
        $angkatan = 2022;
        $tahunLulus = 2026;
        $tahunAkademikLulus = 'Genap 2025/2026';
        $personalEmail = 'joshua.andrean@gmail.com';

        // 1. Ambil Data Referensi Prodi Sistem Informasi & Wilayah
        $prodi = Prodi::where('kode_prodi', '72')->orWhere('nama_prodi', 'LIKE', '%Sistem Informasi%')->first();
        $diy = Propinsi::where('kode_provinsi', '34')->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')->first();
        $sleman = Kabupaten::where('kode_kabupaten', '34.04')->orWhere('nama_kabupaten', 'LIKE', '%Sleman%')->first();

        // 2. Buat Akun User Alumni (Menggunakan Email Pribadi)
        $user = User::updateOrCreate(
            ['username' => $nim],
            [
                'name' => $nama,
                'email' => $personalEmail,
                'password' => Hash::make('15082004'),
                'role' => 'alumni',
                'prodi_id' => $prodi?->id,
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
                // Data Universitas
                'angkatan_masuk' => $angkatan,
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => $tahunAkademikLulus,
                'tahun_lulus' => $tahunLulus,
                'tanggal_lulus' => '2026-07-25',
                'ip_kumulatif' => $ipk,
                'total_sks' => $totalSks,
                'total_angka_kualitas' => $angkaKualitas,

                // Identitas Diri
                'tempat_lahir' => 'Yogyakarta',
                'tanggal_lahir' => '2004-08-15',
                'agama' => 'Kristen Protestan',
                'jenis_kelamin' => 'Laki-laki',
                'golongan_darah' => 'O',
                'warga_negara' => 'WNI',

                // Identitas pendukung akademik
                'nisn' => '0041234567',
                'no_bpjs' => '0001897654321',

                'email_students' => $nim.'@students.ukdw.ac.id',

                // Data Asal Sekolah
                'asal_sekolah' => 'SMA Negeri 3 Yogyakarta',
                'alamat_asal_sekolah' => 'Jl. Yos Sudarso No. 7',
                'kota_kabupaten_asal_sekolah' => 'Kota Yogyakarta',
                'provinsi_asal_sekolah' => 'DI Yogyakarta',
                'jurusan_asal_sekolah' => 'IPA',
            ]
        );

        // 4. Buat Data Yudisium (Status Kelulusan & Skripsi)
        $yudisium = Yudisium::updateOrCreate(
            ['nim' => $nim],
            [
                'judul_ta' => 'Perancangan dan Pengembangan Sistem Informasi Tracer Study Terintegrasi Berbasis Web',
                'judul_ta_inggris' => 'Design and Development of Web-Based Integrated Tracer Study Information System',
                'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                'jenis_publikasi' => 'Jurnal Nasional',
                'status_publikasi' => 'Terbit',
                'keterangan_hasil_yudisium' => 'Lulus dengan Pujian',
                'status_lulus' => 'Lulus',
            ]
        );

        // 5. Buat Record Biodata Utama (Bebas dari kolom duplikasi data_akademik)
        $biodata = Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nim' => $nim,
                'yudisium_id' => $yudisium->id,
                'prodi_id' => $prodi?->id,
                'email_pribadi' => $personalEmail,
                'nomor_telepon' => '081234567890',
                'alamat_saat_ini' => 'Jl. Timoho No. 45, RT 02/RW 08',
                'kabupaten_id' => $sleman?->id,
                'propinsi_id' => $diy?->id,
                'kode_pos' => '55281',
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

        // 6. Bersihkan respon tracer lama untuk Joshua agar fresh siap uji
        Tracer::where('biodata_id', $biodata->id)->delete();
        ProdiResponse::where('biodata_id', $biodata->id)->delete();
    }
}
