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
 * Seeder Data Alumni Sistem Informasi Angkatan 2022 Lulus Genap 2025/2026:
 * 1. Amarillis Crostarosa Putria Kresnadi (NIM: 72220582)
 * 2. Lidya Puspitasari Furimbe (NIM: 72220552)
 * 3. Angela Listya Pradipta Kristi (NIM: 72220521)
 *
 * Menyiapkan akun user, data akademik, dan yudisium dengan password tanggal lahir (22112006),
 * status must_change_password = true, tanpa data domisili.
 */
class AlumniBatch2022Seeder extends Seeder
{
    /**
     * Jalankan seeder akun dan master data alumni angkatan 2022.
     */
    public function run(): void
    {
        $prodi = Prodi::where('kode_prodi', '72')
            ->orWhere('nama_prodi', 'LIKE', '%Sistem Informasi%')
            ->first();

        $alumniList = [
            [
                'nim' => '72220582',
                'nama' => 'AMARILLIS CROSTAROSA PUTRIA KRESNADI',
                'email' => '72220582@students.ukdw.ac.id',
                'ipk' => 3.82,
                'judul_ta' => 'Perancangan Desain Pengalaman Pengguna (UI/UX) Sistem Informasi Tracer Study Berbasis Human-Centered Design',
                'judul_ta_inggris' => 'User Experience (UI/UX) Design of Tracer Study Information System Based on Human-Centered Design',
            ],
            [
                'nim' => '72220552',
                'nama' => 'LIDYA PUSPITASARI FURIMBE',
                'email' => '72220552@students.ukdw.ac.id',
                'ipk' => 3.78,
                'judul_ta' => 'Analisis Penerimaan Pengguna Terhadap Sistem Informasi Akademik Menggunakan Model Technology Acceptance Model (TAM)',
                'judul_ta_inggris' => 'Analysis of User Acceptance of Academic Information Systems Using the Technology Acceptance Model (TAM)',
            ],
            [
                'nim' => '72220521',
                'nama' => 'ANGELA LISTYA PRADIPTA KRISTI',
                'email' => '72220521@students.ukdw.ac.id',
                'ipk' => 3.80,
                'judul_ta' => 'Evaluasi Kualitas Layanan Sistem Informasi Tracer Study Menggunakan Metode E-GovQual dan Importance Performance Analysis (IPA)',
                'judul_ta_inggris' => 'Evaluation of Service Quality of Tracer Study Information System Using E-GovQual Method and Importance Performance Analysis (IPA)',
            ],
        ];

        $angkatan = 2022;
        $tahunLulus = 2026;
        $tahunAkademikLulus = 'Genap 2025/2026';
        $tahunAkademikMasuk = '2022/2023 (Semester Ganjil)';
        $tanggalLahir = '2006-11-22';
        $passwordPlain = '22112006';

        foreach ($alumniList as $data) {
            $nim = $data['nim'];
            $nama = $data['nama'];
            $email = $data['email'];

            // 1. Akun Pengguna (User)
            $user = User::updateOrCreate(
                ['username' => $nim],
                [
                    'name' => $nama,
                    'email' => $email,
                    'password' => Hash::make($passwordPlain),
                    'role' => 'alumni',
                    'kode_prodi' => $prodi?->kode_prodi,
                    'kode_fakultas' => $prodi?->kode_fakultas,
                    'must_change_password' => true,
                ]
            );

            // 2. Data Akademik
            $ipk = $data['ipk'];
            $totalSks = 144;
            $angkaKualitas = round($ipk * $totalSks, 2);

            DataAkademik::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama' => $nama,
                    'email_pribadi' => $email,
                    'kode_prodi' => $prodi?->kode_prodi,
                    'angkatan_masuk' => $angkatan,
                    'tahun_akademik_masuk' => $tahunAkademikMasuk,
                    'status_mahasiswa' => 'Lulus',
                    'tahun_akademik_lulus' => $tahunAkademikLulus,
                    'tahun_lulus' => $tahunLulus,
                    'tanggal_lulus' => '2026-07-25',
                    'ip_kumulatif' => $ipk,
                    'total_sks' => $totalSks,
                    'total_angka_kualitas' => $angkaKualitas,
                    'tanggal_lahir' => $tanggalLahir,
                    'jenis_kelamin' => 'Perempuan',
                    'warga_negara' => 'WNI',
                ]
            );

            // 3. Yudisium
            $yudisium = Yudisium::updateOrCreate(
                ['nim' => $nim],
                [
                    'judul_ta' => $data['judul_ta'],
                    'judul_ta_inggris' => $data['judul_ta_inggris'],
                    'url_publikasi' => 'https://repository.ukdw.ac.id/handle/123456789/'.$nim,
                    'jenis_publikasi' => 'Skripsi',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => 'Lulus dengan Pujian',
                    'status_lulus' => 'Lulus',
                ]
            );

            // 4. Biodata Awal (tanpa domisili & karier)
            Biodata::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $nim,
                    'kode_prodi' => $prodi?->kode_prodi,
                    'email_pribadi' => $email,
                    'nomor_telepon' => null,
                    'alamat_saat_ini' => null,
                    'kelurahan' => null,
                    'kecamatan' => null,
                    'kode_kabupaten' => null,
                    'kode_provinsi' => null,
                    'kode_pos' => null,
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
        }
    }
}
