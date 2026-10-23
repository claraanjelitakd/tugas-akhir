<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Propinsi;
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

        // Ambil wilayah untuk relasi ID yang valid
        $diy = Propinsi::where('kode_provinsi', '34')->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')->first();
        $sleman = Kabupaten::where('kode_kabupaten', '34.04')->orWhere('nama_kabupaten', 'LIKE', '%Sleman%')->first();

        $jakarta = Propinsi::where('kode_provinsi', '31')->orWhere('nama_provinsi', 'LIKE', '%Jakarta%')->first();
        $jakpus = Kabupaten::where('kode_kabupaten', '31.71')->orWhere('nama_kabupaten', 'LIKE', '%Jakarta Pusat%')->first();

        $aceh = Propinsi::where('kode_provinsi', '11')->orWhere('nama_provinsi', 'LIKE', '%Aceh%')->first();
        $acehSelatan = Kabupaten::where('kode_kabupaten', '11.01')->orWhere('nama_kabupaten', 'LIKE', '%Aceh Selatan%')->first();

        // Wilayah referensi per alumni secara bergantian
        $lokasiList = [
            ['prov' => $diy?->id, 'kab' => $sleman?->id, 'kota' => 'Kabupaten Sleman', 'kec' => 'Depok', 'kel' => 'Caturtunggal', 'kodepos' => '55281'],
            ['prov' => $jakarta?->id, 'kab' => $jakpus?->id, 'kota' => 'Kota Jakarta Pusat', 'kec' => 'Gambir', 'kel' => 'Kebon Kelapa', 'kodepos' => '10120'],
            ['prov' => $aceh?->id, 'kab' => $acehSelatan?->id, 'kota' => 'Kabupaten Aceh Selatan', 'kec' => 'Tapaktuan', 'kel' => 'Lhok Ketapang', 'kodepos' => '23715'],
        ];

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
            $lokasi = $lokasiList[$index % count($lokasiList)];
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

                    // Kontak & Domisili
                    'nomor_telepon' => '0812'.rand(1000, 9999).str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'email_pribadi' => strtolower(str_replace(' ', '', explode(' ', $nama)[0])).($index + 1).'@gmail.com',
                    'email_students' => $nim.'@students.ukdw.ac.id',
                    'alamat_saat_ini' => 'Jl. Kenanga No. '.($index + 12).', RT 03/RW 05',
                    'kelurahan' => $lokasi['kel'],
                    'kecamatan' => $lokasi['kec'],
                    'kabupaten_id' => $lokasi['kab'],
                    'propinsi_id' => $lokasi['prov'],
                    'kode_pos' => $lokasi['kodepos'],

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
