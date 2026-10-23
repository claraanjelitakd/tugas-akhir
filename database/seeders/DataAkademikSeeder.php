<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder DataAkademik
 *
 * Mengisi data master riwayat akademik mahasiswa/alumni UKDW.
 */
class DataAkademikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $alumniUsers = User::where('role', 'alumni')
            ->where('username', '!=', '72220001')
            ->get();

        $namaDummy = [
            'Clara Anjelita Kusumadewi',
            'Andreas Timothy Wijaya',
            'Grace Nathania Putri',
            'Kevin Sanjaya Pratama',
            'Daniel Christian Haryanto',
            'Jessica Laurencia Santoso',
            'Michael Steven Kurniawan',
            'Rachel Amanda Sihombing',
            'Samuel Evan Prasetya',
            'Vanessa Stephanie Tanudjaja',
        ];

        $agamaList = ['Kristen Protestan', 'Katolik', 'Islam', 'Kristen Protestan', 'Katolik'];
        $golDarahList = ['O', 'A', 'B', 'AB'];
        $periodeLulusList = ['Gasal 2023/2024', 'Genap 2023/2024', 'Gasal 2024/2025', 'Genap 2024/2025'];
        $ipkList = [3.85, 3.72, 3.90, 3.65, 3.80, 3.75, 3.95, 3.68, 3.88, 3.78];

        $sekolahList = [
            ['nama' => 'SMA Kolese De Britto Yogyakarta', 'alamat' => 'Jl. Laksda Adisucipto No. 161', 'kota' => 'Kabupaten Sleman', 'prov' => 'DI Yogyakarta', 'jurusan' => 'IPA'],
            ['nama' => 'SMA Negeri 1 Yogyakarta', 'alamat' => 'Jl. HOS Cokroaminoto No. 10', 'kota' => 'Kota Yogyakarta', 'prov' => 'DI Yogyakarta', 'jurusan' => 'IPA'],
            ['nama' => 'SMA Santa Ursula Jakarta', 'alamat' => 'Jl. Pos No. 2', 'kota' => 'Kota Jakarta Pusat', 'prov' => 'DKI Jakarta', 'jurusan' => 'IPS'],
            ['nama' => 'SMA Negeri 3 Yogyakarta', 'alamat' => 'Jl. Yos Sudarso No. 7', 'kota' => 'Kota Yogyakarta', 'prov' => 'DI Yogyakarta', 'jurusan' => 'IPA'],
        ];

        foreach ($alumniUsers as $index => $user) {
            $nim = $user->username;
            $parsedInfo = Biodata::parseNim($nim);
            $angkatan = $parsedInfo ? (int) $parsedInfo['angkatan'] : 2020;
            $nama = $namaDummy[$index % count($namaDummy)];
            $sekolah = $sekolahList[$index % count($sekolahList)];

            $personalEmail = strtolower(str_replace(' ', '', explode(' ', $nama)[0])).($index + 1).'@gmail.com';

            // Update nama user & email pribadi agar sinkron di auth, manajemen akun, dan notifikasi
            $user->update([
                'name' => $nama,
                'email' => $personalEmail,
            ]);

            $ipk = $ipkList[$index % count($ipkList)];
            $totalSks = 144;
            $angkaKualitas = round($ipk * $totalSks, 2);

            DataAkademik::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama' => $nama,
                    // Data Paten Universitas
                    'angkatan_masuk' => $angkatan,
                    'status_mahasiswa' => 'Lulus',
                    'tahun_akademik_lulus' => $periodeLulusList[$index % count($periodeLulusList)],
                    'tahun_lulus' => $angkatan + 4,
                    'ip_kumulatif' => $ipk,
                    'total_sks' => $totalSks,
                    'total_angka_kualitas' => $angkaKualitas,

                    // Identitas Diri
                    'tempat_lahir' => ($index % 3 == 0) ? 'Sleman' : (($index % 3 == 1) ? 'Jakarta' : 'Tapaktuan'),
                    'tanggal_lahir' => '2001-08-15',
                    'agama' => $agamaList[$index % count($agamaList)],
                    'jenis_kelamin' => ($index % 2 == 0) ? 'Perempuan' : 'Laki-laki',
                    'golongan_darah' => $golDarahList[$index % count($golDarahList)],
                    'warga_negara' => 'WNI',

                    // Identitas pendukung akademik
                    'nisn' => '003'.str_pad((string) (1234567 + $index), 7, '0', STR_PAD_LEFT),
                    'no_bpjs' => '000189'.str_pad((string) (7654321 + $index), 7, '0', STR_PAD_LEFT),

                    // Email institusi tetap menjadi bagian dari sumber data akademik resmi.
                    'email_students' => $nim.'@students.ukdw.ac.id',
                    // Data Asal Sekolah
                    'asal_sekolah' => $sekolah['nama'],
                    'alamat_asal_sekolah' => $sekolah['alamat'],
                    'kota_kabupaten_asal_sekolah' => $sekolah['kota'],
                    'provinsi_asal_sekolah' => $sekolah['prov'],
                    'jurusan_asal_sekolah' => $sekolah['jurusan'],
                ]
            );
        }
    }
}
