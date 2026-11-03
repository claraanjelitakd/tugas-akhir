<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Class AlumniSistemInformasiSeeder
 *
 * Menyiapkan master data induk, akun user, data akademik, skripsi yudisium,
 * institusi/perusahaan, dan biodata karier nyata untuk 8 profil alumni
 * berprestasi Program Studi Sistem Informasi (FTI UKDW):
 * 1. Laurentia Lisa Setiawan (Lulus 2009 - Founder PT Glori Indah Farm, S2 James Cook University)
 * 2. Yafet Andromeda (Lulus 2014 - Lead Data Engineer FinAccel / Kredivo)
 * 3. Magdalena Evelyn Halim (Lulus 2022 - IT Business Analyst PT AJ Central Asia Raya)
 * 4. Manila Kristin (Lulus 2016 - CEO & Founder Kreasitech / CV Adaptive Kreasi Teknologi)
 * 5. Gilbert Christyano Day (Lulus 2024 - Fullstack Developer PT Cita Rasa Mendunia / Bakpiaku)
 * 6. Agustinus Arsa Paskha Avaleriantha (Lulus 2021 - IT Specialist Data Engineer BCA)
 * 7. Teddy Wirawan (Lulus 2011 - Engineering Manager ICUBE by SIRCLO)
 * 8. Patrik Kurniawan Saputra (Lulus 2025 - Product Engineer AstraPay / Astra Group)
 */
class AlumniSistemInformasiSeeder extends Seeder
{
    /**
     * Jalankan seeder alumni Sistem Informasi.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password123');

        // 1. Dapatkan Referensi Program Studi Sistem Informasi (Kode 72)
        $prodi = Prodi::where('kode_prodi', '72')->orWhere('nama_prodi', 'LIKE', '%Sistem Informasi%')->first();

        // 2. Dapatkan Referensi Wilayah
        $diy = Propinsi::where('kode_provinsi', '34')->orWhere('nama_provinsi', 'LIKE', '%Yogyakarta%')->first();
        $sleman = Kabupaten::where('kode_kabupaten', '34.04')->orWhere('nama_kabupaten', 'LIKE', '%Sleman%')->first();
        $kotaYogya = Kabupaten::where('kode_kabupaten', '34.71')->orWhere('nama_kabupaten', 'LIKE', '%Yogyakarta%')->first();

        $dki = Propinsi::where('kode_provinsi', '31')->orWhere('nama_provinsi', 'LIKE', '%Jakarta%')->first();
        $jaksel = Kabupaten::where('kode_kabupaten', '31.74')->orWhere('nama_kabupaten', 'LIKE', '%Jakarta Selatan%')->first();
        $jakbar = Kabupaten::where('kode_kabupaten', '31.73')->orWhere('nama_kabupaten', 'LIKE', '%Jakarta Barat%')->first();

        // 3. Daftarkan Entitas Perusahaan Nyata
        $compGlori = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'PT Glori Indah Farm'],
            [
                'jenis_perusahaan' => 'Agribisnis & Peternakan',
                'skala' => 'Nasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Sleman, DI Yogyakarta',
                'kode_provinsi' => $diy?->kode_provinsi,
                'kode_kabupaten' => $sleman?->kode_kabupaten,
            ]
        );

        $compFinaccel = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'FinAccel (Kredivo)'],
            [
                'jenis_perusahaan' => 'Financial Technology (FinTech)',
                'skala' => 'Internasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Jakarta Selatan, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jaksel?->kode_kabupaten,
                'homepage' => 'https://www.linkedin.com/company/10277787/',
            ]
        );

        $compCar = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'PT AJ Central Asia Raya (CAR Life Insurance)'],
            [
                'jenis_perusahaan' => 'Asuransi & Layanan Finansial',
                'skala' => 'Nasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Jakarta Barat, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jakbar?->kode_kabupaten,
                'homepage' => 'https://www.car.co.id',
            ]
        );

        $compKreasitech = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'Kreasitech (CV Adaptive Kreasi Teknologi)'],
            [
                'jenis_perusahaan' => 'Konsultan IT & Software House',
                'skala' => 'Nasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Yogyakarta, DI Yogyakarta',
                'kode_provinsi' => $diy?->kode_provinsi,
                'kode_kabupaten' => $kotaYogya?->kode_kabupaten,
                'homepage' => 'https://kreasitech.com',
            ]
        );

        $compBakpiaku = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'PT Cita Rasa Mendunia (Bakpiaku)'],
            [
                'jenis_perusahaan' => 'Food & Beverage / E-Commerce',
                'skala' => 'Nasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Sleman, DI Yogyakarta',
                'kode_provinsi' => $diy?->kode_provinsi,
                'kode_kabupaten' => $sleman?->kode_kabupaten,
                'homepage' => 'https://bakpiaku.com',
            ]
        );

        $compBca = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'PT Bank Central Asia Tbk (BCA)'],
            [
                'jenis_perusahaan' => 'Perbankan & Jasa Keuangan',
                'skala' => 'Internasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Menara BCA, Grand Indonesia, Jakarta Pusat',
                'kode_provinsi' => $dki?->kode_provinsi,
                'homepage' => 'https://www.bca.co.id',
            ]
        );

        $compSirclo = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'ICUBE by SIRCLO (PT Inovasi Informasi Indonesia)'],
            [
                'jenis_perusahaan' => 'Enterprise E-Commerce Solutions',
                'skala' => 'Internasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Jakarta Selatan, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jaksel?->kode_kabupaten,
                'homepage' => 'https://icubeonline.com',
            ]
        );

        $compAstraPay = Perusahaan::firstOrCreate(
            ['nama_perusahaan' => 'PT Astra Digital Arta (AstraPay)'],
            [
                'jenis_perusahaan' => 'Digital Financial Services (Astra Group)',
                'skala' => 'Nasional',
                'status_verifikasi' => 'Terverifikasi',
                'alamat' => 'Menara FIF, TB Simatupang, Jakarta Selatan',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jaksel?->kode_kabupaten,
                'homepage' => 'https://www.astrapay.com',
            ]
        );

        // 4. Daftar Lengkap 8 Alumni Sistem Informasi
        $alumniList = [
            [
                'nim' => '72050015',
                'nama' => 'Laurentia Lisa Setiawan',
                'email' => 'laurentia.lisa@gmail.com',
                'telepon' => '081227001515',
                'angkatan' => 2005,
                'tahun_lulus' => 2009,
                'tahun_akademik_lulus' => 'Genap 2008/2009',
                'tanggal_lulus' => '2009-08-15',
                'tanggal_lahir' => '1987-04-18',
                'jenis_kelamin' => 'Perempuan',
                'ipk' => 3.75,
                'judul_ta' => 'Perancangan Sistem Informasi Manajemen Bisnis dan Distribusi Agribisnis',
                'url_publikasi' => 'https://repository.ukdw.ac.id/view/creators/72050015.html',
                'perusahaan_id' => $compGlori->id,
                'kategori_pekerjaan' => 'Wiraswasta',
                'posisi_jabatan' => null,
                'posisi_wiraswasta' => 'Owner & Founder',
                'gaji' => 40000000,
                'skills' => 'Agribusiness, Business Management, Entrepreneurship, Software House Management, Robotics Education, SaaS Product Development',
                'experience' => "Owner & Founder di PT Glori Indah Farm (2009 - Sekarang)\nCEO & Founder di PT Cyber Olympus Indonesia (Cyber Olympus Software House)\nDirector di CROG International (Pusat Pembelajaran Robotik & Game)\nFounder Furnizing & Yes She Said I Do",
                'pendidikan_tingkat' => "Master's Degree (S2)",
                'perguruan_tinggi' => 'James Cook University Singapore',
                'pendidikan_prodi' => 'Master of Business Administration (MBA)',
                'linkedin_url' => 'https://www.linkedin.com/in/laurentia-lisa-setiawan/',
                'linkedin_username' => 'laurentia-lisa-setiawan',
                'alamat' => 'Sleman, DI Yogyakarta',
                'kode_provinsi' => $diy?->kode_provinsi,
                'kode_kabupaten' => $sleman?->kode_kabupaten,
            ],
            [
                'nim' => '23100570',
                'nama' => 'Yafet Andromeda',
                'email' => 'yafet.andromeda@gmail.com',
                'telepon' => '081328005700',
                'angkatan' => 2010,
                'tahun_lulus' => 2014,
                'tahun_akademik_lulus' => 'Genap 2013/2014',
                'tanggal_lulus' => '2014-07-20',
                'tanggal_lahir' => '1992-06-14',
                'jenis_kelamin' => 'Laki-laki',
                'ipk' => 3.82,
                'judul_ta' => 'Aplikasi Pencarian Kost Berbasis Mobile',
                'url_publikasi' => 'https://repository.ukdw.ac.id/view/creators/23100570=3AYafet_Andromeda=3A=3A.default.html',
                'perusahaan_id' => $compFinaccel->id,
                'kategori_pekerjaan' => 'Pekerja',
                'posisi_jabatan' => 'Lead Data Engineer',
                'posisi_wiraswasta' => null,
                'gaji' => 35000000,
                'skills' => 'Data Warehousing, Databases, Data Engineering, Backend Developer, Frontend Developer, Machine Learning Engineer, ETL, SQL',
                'experience' => "Lead Data Engineer di FinAccel (Oct 2020 - Present · Full-time) - Jakarta, Indonesia (Hybrid)\nLead Data Engineer di Bhinneka.Com (Nov 2014 - Oct 2020 · 6 yrs) - Jakarta, Indonesia (Remote)\nLead Data Engineer with a demonstrated history in E-Commerce and FinTech Industry. Experienced in Backend Developer, Frontend Developer, Data Engineer, and Machine Learning Engineer.",
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/yafet-andromeda/',
                'linkedin_username' => 'yafet-andromeda',
                'alamat' => 'Jakarta Selatan, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jaksel?->kode_kabupaten,
            ],
            [
                'nim' => '72180180',
                'nama' => 'Magdalena Evelyn Halim',
                'email' => 'magdalena.evelyn@gmail.com',
                'telepon' => '081299180180',
                'angkatan' => 2018,
                'tahun_lulus' => 2022,
                'tahun_akademik_lulus' => 'Genap 2021/2022',
                'tanggal_lulus' => '2022-07-15',
                'tanggal_lahir' => '2000-09-22',
                'jenis_kelamin' => 'Perempuan',
                'ipk' => 3.88,
                'judul_ta' => 'Pembangunan Aplikasi Perangkat Bergerak Document Distribution System (DDS) untuk Pengiriman Internal dan Eksternal',
                'url_publikasi' => 'https://repository.ukdw.ac.id/view/creators/=3AMagdalena_Evelyn_Halim=3A=3A.html',
                'perusahaan_id' => $compCar->id,
                'kategori_pekerjaan' => 'Pekerja',
                'posisi_jabatan' => 'IT Business Analyst',
                'posisi_wiraswasta' => null,
                'gaji' => 12000000,
                'skills' => 'Business Analysis, Mobile Application Development, System Design, Requirements Gathering, Research & Publication, Agile Methodology',
                'experience' => "IT Business Analyst di PT AJ Central Asia Raya (CAR Life Insurance) (2022 - Sekarang)\nPeneliti Hibah LPPM & Riset Unggulan Universitas UKDW (2022)\nPengembang Aplikasi Antisipasi Bencana Merapi (HKI Unit Centrino UKDW 2019)\nPenulis Artikel Ilmiah Jurnal JUTEI (2023)",
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/magdalenaevelyn/',
                'linkedin_username' => 'magdalenaevelyn',
                'alamat' => 'Jakarta Barat, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jakbar?->kode_kabupaten,
            ],
            [
                'nim' => '72120088',
                'nama' => 'Manila Kristin',
                'email' => 'manila.kristin@kreasitech.com',
                'telepon' => '081804120088',
                'angkatan' => 2012,
                'tahun_lulus' => 2016,
                'tahun_akademik_lulus' => 'Genap 2015/2016',
                'tanggal_lulus' => '2016-08-20',
                'tanggal_lahir' => '1994-05-10',
                'jenis_kelamin' => 'Perempuan',
                'ipk' => 3.70,
                'judul_ta' => 'Sistem Informasi Jasa Servis AC Berbasis Web (Back Office). Studi Kasus: CV. Kurniatama',
                'url_publikasi' => 'http://repository.ukdw.ac.id/view/subjects/QA76.default.html',
                'perusahaan_id' => $compKreasitech->id,
                'kategori_pekerjaan' => 'Wiraswasta',
                'posisi_jabatan' => null,
                'posisi_wiraswasta' => 'Chief Executive Officer (CEO) & Founder',
                'gaji' => 28000000,
                'skills' => 'IT Leadership, Web & Mobile Development, SaaS Product Management, IT Outsourcing, Digital Marketing, Graphic Design, Business Strategy',
                'experience' => "Chief Executive Officer (CEO) & Founder di Kreasitech (CV Adaptive Kreasi Teknologi) (2016 - Sekarang)\nGraphic Designer di PT Solusi Kreatif Kompis\nMotto: Stop Overthinking, Start Doing",
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/manila-kristin/',
                'linkedin_username' => 'manila-kristin',
                'alamat' => 'Kota Yogyakarta, DI Yogyakarta',
                'kode_provinsi' => $diy?->kode_provinsi,
                'kode_kabupaten' => $kotaYogya?->kode_kabupaten,
            ],
            [
                'nim' => '72200391',
                'nama' => 'Gilbert Christyano Day',
                'email' => 'gilbert.day@gmail.com',
                'telepon' => '081904200391',
                'angkatan' => 2020,
                'tahun_lulus' => 2024,
                'tahun_akademik_lulus' => 'Genap 2023/2024',
                'tanggal_lulus' => '2024-08-10',
                'tanggal_lahir' => '2002-01-19',
                'jenis_kelamin' => 'Laki-laki',
                'ipk' => 3.86,
                'judul_ta' => 'Implementasi Payment Gateway pada E-Commerce. Studi Kasus: PT. Cita Rasa Mendunia',
                'url_publikasi' => 'https://repository.ukdw.ac.id/9927/1/72200391_Bab%201_Bab%205_Daftarpustaka.pdf',
                'perusahaan_id' => $compBakpiaku->id,
                'kategori_pekerjaan' => 'Pekerja',
                'posisi_jabatan' => 'Fullstack Developer',
                'posisi_wiraswasta' => null,
                'gaji' => 9500000,
                'skills' => 'Flutter, Laravel, Next.js, Payment Gateway Midtrans, Database Migration, Fullstack Development, RESTful API',
                'experience' => "Fullstack Developer di PT Cita Rasa Mendunia (Bakpiaku) (2024 - Sekarang)\nPengembang Sistem Membership & Integrasi Payment Gateway Midtrans",
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/gilbert-day-a717a6247/',
                'linkedin_username' => 'gilbert-day-a717a6247',
                'alamat' => 'Sleman, DI Yogyakarta',
                'kode_provinsi' => $diy?->kode_provinsi,
                'kode_kabupaten' => $sleman?->kode_kabupaten,
            ],
            [
                'nim' => '72160043',
                'nama' => 'Agustinus Arsa Paskha Avaleriantha',
                'email' => 'agustinus.arsa@gmail.com',
                'telepon' => '081329160043',
                'angkatan' => 2016,
                'tahun_lulus' => 2021,
                'tahun_akademik_lulus' => 'Gasal 2020/2021',
                'tanggal_lulus' => '2021-02-20',
                'tanggal_lahir' => '1998-08-25',
                'jenis_kelamin' => 'Laki-laki',
                'ipk' => 3.96,
                'judul_ta' => 'Analisis Multidimensional Profil Mahasiswa Studi Kasus: Program Studi Sistem Informasi UKDW',
                'url_publikasi' => 'https://jutei.ukdw.ac.id/index.php/jurnal/article/view/202',
                'perusahaan_id' => $compBca->id,
                'kategori_pekerjaan' => 'Pekerja',
                'posisi_jabatan' => 'IT Specialist Network (Data Engineer)',
                'posisi_wiraswasta' => null,
                'gaji' => 22000000,
                'skills' => 'Data Engineering, Big Data Infrastructure, ETL, Network Data Analytics, Data Storage & Maintenance, Data Visualization, SQL, Python',
                'experience' => "IT Specialist Network (Data Engineer) di PT Bank Central Asia Tbk (BCA) (Mei 2021 - Sekarang)\nWisudawan Terbaik Program Studi Sistem Informasi UKDW (Februari 2021)\nPenulis Publikasi Ilmiah Jurnal JUTEI Vol 6 No 1 (2022)",
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/agustinus-arsa/',
                'linkedin_username' => 'agustinus-arsa',
                'alamat' => 'Jakarta Pusat, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
            ],
            [
                'nim' => '23070204',
                'nama' => 'Teddy Wirawan',
                'email' => 'teddy.wirawan@sirclo.com',
                'telepon' => '081226070204',
                'angkatan' => 2007,
                'tahun_lulus' => 2011,
                'tahun_akademik_lulus' => 'Genap 2010/2011',
                'tanggal_lulus' => '2011-07-28',
                'tanggal_lahir' => '1989-11-03',
                'jenis_kelamin' => 'Laki-laki',
                'ipk' => 3.78,
                'judul_ta' => 'Desain Dashboard untuk Visualisasi Pencapaian Kompetensi Studi',
                'url_publikasi' => 'https://repository.ukdw.ac.id/view/year/2011.type.html',
                'perusahaan_id' => $compSirclo->id,
                'kategori_pekerjaan' => 'Pekerja',
                'posisi_jabatan' => 'Engineering Manager',
                'posisi_wiraswasta' => null,
                'gaji' => 45000000,
                'skills' => 'Software Engineering, Omnichannel, Enterprise E-Commerce, Magento, Adobe Commerce, Engineering Management, Team Leadership, Microservices',
                'experience' => "Engineering Manager di ICUBE by SIRCLO (PT Inovasi Informasi Indonesia) (12+ tahun pengalaman)\nLead Architect Proyek Medbiz (Omnichannel B2B Marketplace kolaborasi Bio Farma)\nStaff IT di Yayasan Lembaga SABDA",
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/teddy-wirawan-b42026147/',
                'linkedin_username' => 'teddy-wirawan-b42026147',
                'alamat' => 'Jakarta Selatan, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jaksel?->kode_kabupaten,
            ],
            [
                'nim' => '72210055',
                'nama' => 'Patrik Kurniawan Saputra',
                'email' => 'patrik.kurniawan@astrapay.com',
                'telepon' => '081392210055',
                'angkatan' => 2021,
                'tahun_lulus' => 2025,
                'tahun_akademik_lulus' => 'Genap 2024/2025',
                'tanggal_lulus' => '2025-08-15',
                'tanggal_lahir' => '2003-02-14',
                'jenis_kelamin' => 'Laki-laki',
                'ipk' => 3.91,
                'judul_ta' => 'Rancang Bangun RESTful API dan Pengembangan Website Admin untuk Aplikasi Walking Tour Guide Studi Kasus: Sistem Informasi Pasar',
                'url_publikasi' => 'https://repository.ukdw.ac.id/view/type/thesis/2025.html',
                'perusahaan_id' => $compAstraPay->id,
                'kategori_pekerjaan' => 'Pekerja',
                'posisi_jabatan' => 'Product Engineer',
                'posisi_wiraswasta' => null,
                'gaji' => 14000000,
                'skills' => 'Back-End Engineering, RESTful API, Microservices, Web Services Authentication & Authorization, Laravel, Java, Cloud Computing, PostgreSQL',
                'experience' => "Product Engineer di PT Astra Digital Arta (AstraPay) (Januari 2026 - Sekarang)\nBack End Developer di Berijalan (PT Astra Sedaya Finance) (2025)\nPeneliti Riset Integrasi Dosen SiPasar LPPM UKDW\nGraduate Bangkit Academy 2024 & Pengurus HMSI UKDW 2023",
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
                'linkedin_url' => 'https://www.linkedin.com/in/patrik-kurniawan-saputra-05b7701a5/',
                'linkedin_username' => 'patrik-kurniawan-saputra-05b7701a5',
                'alamat' => 'Jakarta Selatan, DKI Jakarta',
                'kode_provinsi' => $dki?->kode_provinsi,
                'kode_kabupaten' => $jaksel?->kode_kabupaten,
            ],
        ];

        // 5. Eksekusi Pembentukan Akun User, Data Akademik, Yudisium, dan Biodata
        foreach ($alumniList as $data) {
            $nim = $data['nim'];
            $nama = $data['nama'];

            // A. User Akun Alumni
            $user = User::updateOrCreate(
                ['username' => $nim],
                [
                    'name' => $nama,
                    'email' => $data['email'],
                    'password' => $defaultPassword,
                    'role' => 'alumni',
                    'prodi_id' => $prodi?->id,
                    'must_change_password' => false,
                ]
            );

            // B. Data Akademik
            $totalSks = 144;
            $angkaKualitas = round($data['ipk'] * $totalSks, 2);

            DataAkademik::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama' => $nama,
                    'angkatan_masuk' => $data['angkatan'],
                    'status_mahasiswa' => 'Lulus',
                    'tahun_akademik_lulus' => $data['tahun_akademik_lulus'],
                    'tahun_lulus' => $data['tahun_lulus'],
                    'tanggal_lulus' => $data['tanggal_lulus'],
                    'ip_kumulatif' => $data['ipk'],
                    'total_sks' => $totalSks,
                    'total_angka_kualitas' => $angkaKualitas,
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'warga_negara' => 'WNI',
                ]
            );

            // C. Data Yudisium (Skripsi & Kelulusan)
            $yudisium = Yudisium::updateOrCreate(
                ['nim' => $nim],
                [
                    'judul_ta' => $data['judul_ta'],
                    'url_publikasi' => $data['url_publikasi'],
                    'jenis_publikasi' => 'Skripsi',
                    'status_publikasi' => 'Terbit',
                    'keterangan_hasil_yudisium' => $data['ipk'] >= 3.80 ? 'Lulus dengan Pujian (Cum Laude)' : 'Lulus Sangat Memuaskan',
                    'status_lulus' => 'Lulus',
                ]
            );

            // D. Data Biodata Profil Karier Utama
            Biodata::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $nim,
                    'yudisium_id' => $yudisium->id,
                    'prodi_id' => $prodi?->id,
                    'email_pribadi' => $data['email'],
                    'nomor_telepon' => $data['telepon'],
                    'alamat_saat_ini' => $data['alamat'],
                    'kode_kabupaten' => $data['kode_kabupaten'] ?? null,
                    'kode_provinsi' => $data['kode_provinsi'] ?? null,
                    'kategori_pekerjaan' => $data['kategori_pekerjaan'],
                    'posisi_jabatan' => $data['posisi_jabatan'],
                    'posisi_wiraswasta' => $data['posisi_wiraswasta'],
                    'perusahaan_id' => $data['perusahaan_id'],
                    'gaji' => $data['gaji'] ?? null,
                    'skills' => $data['skills'],
                    'experience' => $data['experience'],
                    'pendidikan_tingkat' => $data['pendidikan_tingkat'],
                    'perguruan_tinggi' => $data['perguruan_tinggi'],
                    'pendidikan_prodi' => $data['pendidikan_prodi'],
                    'linkedin_url' => $data['linkedin_url'],
                    'linkedin_username' => $data['linkedin_username'],
                ]
            );
        }
    }
}
