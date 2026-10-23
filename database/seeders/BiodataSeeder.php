<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;

/**
 * Seeder Biodata
 *
 * Mengisi data profil aktif alumni untuk sistem Tracer Study UKDW.
 * Bersih dari duplikasi master akademik (nama, tanggal lahir, agama, dll berada di DataAkademik).
 */
class BiodataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $alumniUsers = User::where('role', 'alumni')
            ->where('username', '!=', '72220001')
            ->get();
        $perusahaans = Perusahaan::all();

        // Ambil wilayah untuk relasi ID yang valid
        $diy = Propinsi::where('kode_provinsi', '34')->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')->first();
        $sleman = Kabupaten::where('kode_kabupaten', '34.04')->orWhere('nama_kabupaten', 'LIKE', '%Sleman%')->first();

        $jakarta = Propinsi::where('kode_provinsi', '31')->orWhere('nama_provinsi', 'LIKE', '%Jakarta%')->first();
        $jakpus = Kabupaten::where('kode_kabupaten', '31.71')->orWhere('nama_kabupaten', 'LIKE', '%Jakarta Pusat%')->first();

        $aceh = Propinsi::where('kode_provinsi', '11')->orWhere('nama_provinsi', 'LIKE', '%Aceh%')->first();
        $acehSelatan = Kabupaten::where('kode_kabupaten', '11.01')->orWhere('nama_kabupaten', 'LIKE', '%Aceh Selatan%')->first();

        $lokasiList = [
            ['prov' => $diy?->id, 'kab' => $sleman?->id, 'kota' => 'Kabupaten Sleman', 'kec' => 'Depok', 'kel' => 'Caturtunggal', 'kodepos' => '55281'],
            ['prov' => $jakarta?->id, 'kab' => $jakpus?->id, 'kota' => 'Kota Jakarta Pusat', 'kec' => 'Gambir', 'kel' => 'Kebon Kelapa', 'kodepos' => '10120'],
            ['prov' => $aceh?->id, 'kab' => $acehSelatan?->id, 'kota' => 'Kabupaten Aceh Selatan', 'kec' => 'Tapaktuan', 'kel' => 'Lhok Ketapang', 'kodepos' => '23715'],
        ];

        $jabatanList = [
            'Staff',
            'Supervisor',
            'Low manager',
            'Midle Manager',
            'Direksi / Top Manager',
            'Staff',
            'Supervisor',
            'Midle Manager',
            'Staff',
            'Supervisor',
        ];

        $skillsList = [
            'Web Development & Cloud Computing',
            'Data Science & Predictive Analytics',
            'UI/UX Design & User Research',
            'Cyber Security & Penetration Testing',
            'Financial Auditing & Taxation',
            'Architectural 3D Modeling & BIM',
            'Food Quality Assurance & HACCP',
            'Clinical Medicine & Diagnostics',
            'Pastoral Care & Christian Ministry',
            'English Curriculum & Instructional Design',
        ];

        $experienceList = [
            'Open Source, IoT, Artificial Intelligence',
            'Big Data, Deep Learning, Cloud Architecture',
            'Design Systems, Accessibility, Interaction Design',
            'Network Security, Cryptography, Blockchain',
            'Investment Portfolio, Stock Analysis, FinTech',
            'Sustainable Architecture, Green Building Design',
            'Food Biotechnology, Fermentation Science',
            'Public Health, Medical Research, Telemedicine',
            'Community Leadership, Interfaith Dialogue',
            'Language Acquisition, EdTech, Translation',
        ];

        $atasanDataList = [
            ['nama' => 'Ir. Bambang Trihatmojo, M.Eng.', 'email' => 'bambang.t@perusahaan.co.id', 'telepon' => '081223344551'],
            ['nama' => 'Dra. Sri Wahyuni, M.Si.', 'email' => 'sri.wahyuni@perusahaan.co.id', 'telepon' => '081223344552'],
            ['nama' => 'Hartono Kusuma, S.E., Ak.', 'email' => 'hartono.k@perusahaan.co.id', 'telepon' => '081223344553'],
            ['nama' => 'Maya Indrawati, S.T., M.Kom.', 'email' => 'maya.indrawati@perusahaan.co.id', 'telepon' => '081223344554'],
            ['nama' => 'Rendra Pratama, S.Kom., MBA.', 'email' => 'rendra.pratama@perusahaan.co.id', 'telepon' => '081223344555'],
        ];

        foreach ($alumniUsers as $index => $user) {
            $nim = $user->username;
            $parsedInfo = Biodata::parseNim($nim);
            $kodeProdi = $parsedInfo ? $parsedInfo['kode_prodi'] : '71';
            $lokasi = $lokasiList[$index % count($lokasiList)];

            $prodiId = null;
            if ($parsedInfo) {
                $prodiDb = Prodi::where('kode_prodi', $parsedInfo['kode_prodi'])->first();
                if ($prodiDb) {
                    $prodiId = $prodiDb->id;
                }
            }

            // Assign perusahaan secara bergantian jika ada perusahaan
            $perusahaanId = null;
            if ($perusahaans->isNotEmpty()) {
                $comp = $perusahaans[$index % $perusahaans->count()];
                $perusahaanId = $comp->id;
            }

            // Buat atau kaitkan data atasan
            $atasanInfo = $atasanDataList[$index % count($atasanDataList)];
            $atasan = Atasan::firstOrCreate(
                ['email' => 'atasan.'.$index.'.'.$atasanInfo['email']],
                [
                    'nama' => $atasanInfo['nama'],
                    'telepon' => $atasanInfo['telepon'],
                ]
            );

            $cleanUsername = strtolower(str_replace(' ', '', explode(' ', $user->name ?? 'alumni')[0])).$index;

            $dataAkademik = DataAkademik::where('nim', $nim)->first();
            $yudisium = Yudisium::where('nim', $nim)->first();

            $gajiList = [5000000, 6500000, 8000000, 10000000, 12500000, 7500000, 9000000];
            $personalEmail = strtolower(str_replace(' ', '', explode(' ', $user->name ?? 'alumni')[0])).($index + 1).'@gmail.com';

            Biodata::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $nim,
                    'yudisium_id' => $yudisium?->id,
                    'prodi_id' => $prodiId,
                    'nomor_telepon' => '0812'.rand(1000, 9999).str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'email_pribadi' => $personalEmail,
                    'alamat_saat_ini' => 'Jl. Kenanga No. '.($index + 12).', RT 03/RW 05',
                    'kabupaten_id' => $lokasi['kab'],
                    'propinsi_id' => $lokasi['prov'],
                    'kode_pos' => $lokasi['kodepos'],
                    'instagram_url' => 'https://instagram.com/'.$cleanUsername,
                    'facebook_url' => 'https://facebook.com/'.$cleanUsername,
                    'linkedin_url' => 'https://linkedin.com/in/'.$cleanUsername,
                    'linkedin_username' => $cleanUsername,
                    'skills' => $skillsList[$index % count($skillsList)],
                    'experience' => $experienceList[$index % count($experienceList)],
                    'perusahaan_id' => $perusahaanId,
                    'atasan_id' => $atasan?->id,
                    'kategori_pekerjaan' => 'Pekerja',
                    'posisi_jabatan' => $jabatanList[$index % count($jabatanList)],
                    'gaji' => $gajiList[$index % count($gajiList)],
                    'jenis_pekerjaan' => ($parsedInfo && $parsedInfo['kode_prodi'] === '31') ? 'Gerejawi' : null,
                ]
            );
        }
    }
}
