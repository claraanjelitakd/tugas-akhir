<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use App\Models\Yudisium;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Skenario Dashboard & Kohort 532 Alumni Nyata UKDW.
 *
 * Mengisi 532 data alumni unik 100% dengan nama asli Indonesia, satu kohort
 * S1 angkatan 2021 yang lulus pada 2025, perusahaan riil dari LinkedIn/industri,
 * serta sebaran respon kuesioner realistis (tinggi, sedang, dan rendah)
 * di seluruh 12 program studi UKDW.
 */
class DashboardScenarioSeeder extends Seeder
{
    /**
     * Penampung jawaban kuesioner untuk bulk insert berkecepatan tinggi.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $pendingAnswers = [];

    /**
     * Jalankan proses seeding kohort 532 alumni riil.
     */
    public function run(): void
    {
        $this->pendingAnswers = [];
        $jsonPath = database_path('seeders/data/alumni_532_real_cohort.json');

        if (! file_exists($jsonPath)) {
            $this->command?->error("Berkas fixture kohort {$jsonPath} tidak ditemukan.");

            return;
        }

        $cohortData = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($cohortData) || empty($cohortData)) {
            $this->command?->error("Format data kohort {$jsonPath} tidak valid.");

            return;
        }

        $fixtureNims = collect($cohortData)
            ->pluck('nim')
            ->map(static fn (mixed $nim): string => (string) $nim)
            ->reject(static fn (string $nim): bool => $nim === '72220001')
            ->values();
        $fixtureBiodataIds = Biodata::query()
            ->whereIn('nim', $fixtureNims)
            ->pluck('id');

        if ($fixtureBiodataIds->isNotEmpty()) {
            KuesionerJawaban::query()
                ->where('responden_type', 'alumni')
                ->whereIn('biodata_id', $fixtureBiodataIds)
                ->delete();
        }

        $prodis = Prodi::query()->get()->keyBy('kode_prodi');
        $propinsiDiy = Propinsi::query()->where('kode_provinsi', '34')->first()
            ?? Propinsi::firstOrCreate(['nama_provinsi' => 'DI Yogyakarta', 'kode_provinsi' => '34']);
        $kabSleman = Kabupaten::query()->where('kode_kabupaten', '34.04')->first()
            ?? Kabupaten::firstOrCreate(['kode_provinsi' => $propinsiDiy->kode_provinsi, 'nama_kabupaten' => 'Sleman', 'kode_kabupaten' => '34.04']);
        $kabKotaYogya = Kabupaten::query()->where('kode_kabupaten', '34.71')->first()
            ?? Kabupaten::firstOrCreate(['kode_provinsi' => $propinsiDiy->kode_provinsi, 'nama_kabupaten' => 'Kota Yogyakarta', 'kode_kabupaten' => '34.71']);

        $defaultHashedPassword = Hash::make('password123');

        // Master pertanyaan kuesioner universitas
        $universityQuestions = KuesionerPertanyaan::query()
            ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
            ->join('kuesioner_section as s', 's.id', '=', 'sp.section_id')
            ->where('s.target', 'univ')
            ->where('kuesioner_pertanyaan.tipe', '!=', 'header')
            ->where('kuesioner_pertanyaan.is_active', true)
            ->with('opsi')
            ->select('kuesioner_pertanyaan.*', 'sp.section_id')
            ->orderBy('sp.section_id')
            ->orderBy('sp.order')
            ->get();

        // Cache kuesioner prodi dan pertanyaannya
        $prodiSections = KuesionerSection::where('target', 'prodi')->get()->keyBy('prodi_id');
        $prodiQuestionsBySection = KuesionerPertanyaan::query()
            ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
            ->whereIn('sp.section_id', $prodiSections->pluck('id'))
            ->where('kuesioner_pertanyaan.tipe', '!=', 'header')
            ->with('opsi')
            ->select('kuesioner_pertanyaan.*', 'sp.section_id')
            ->orderBy('sp.order')
            ->get()
            ->groupBy('section_id');

        // Cache perusahaan riil
        $companiesCache = Perusahaan::all()->keyBy('nama_perusahaan');

        // Daftar nama atasan nyata per divisi/sektor
        $managerNames = [
            'IT' => ['Hartono Kusumo, S.E., MBA.', 'Maya Indrawati, S.T., M.Kom.', 'Rendra Pratama, S.Kom.', 'Budi Santoso, M.Cs.'],
            'Business' => ['Dra. Maria Sri Lestari, Ak., M.M.', 'Hendrawan Widjaja, S.E., CPA.', 'Christian Tanudjaya, M.B.A.'],
            'Medical' => ['Dr. dr. Hendrawan Sp.PD-KGEH', 'dr. Theresia Ratna Sp.A.', 'dr. Albertus Nugroho M.Kes.'],
            'Architecture' => ['Ir. Budi Prasetyo, IAI, AA.', 'Ar. Cynthia Maharani, M.Arch.', 'Ir. Gunawan Setiawan, M.T.'],
            'Biotek' => ['Dr. Ir. Wahyu Wibowo, M.Sc.', 'Dr. rer. nat. Florentina Lestari', 'Ir. Bambang Purnomo, M.Biotech.'],
            'Humanities' => ['Pdt. Prof. Dr. Robertus Setiawan, Th.D.', 'Drs. Ignatius Dananjaya, M.Hum.', 'Dra. Elisabeth Kinasih, M.Ed.'],
        ];

        $createdCount = 0;
        $idx = 0;

        foreach ($cohortData as $item) {
            $idx++;
            $prodiCode = (string) $item['prodi_kode'];
            $prodi = $prodis->get($prodiCode);
            if (! $prodi) {
                continue;
            }

            $nim = (string) $item['nim'];
            if ($nim === '72220001') {
                continue;
            }
            $nama = (string) $item['nama'];
            $gender = (string) ($item['gender'] ?? 'Laki-laki');
            $email = (string) $item['email'];
            $angkatan = 2021;
            $tahunLulus = 2025;
            $ipk = (float) ($item['ipk'] ?? 3.45);
            $isRespondent = (bool) ($item['is_respondent'] ?? false);
            $statusKarier = $item['status_karier'] ?? null;
            $companyName = $item['perusahaan'] ?? null;
            $methodsScores = $item['metode_pembelajaran'] ?? [4, 4, 4, 4, 4, 3, 4];
            $hasCareer = in_array($statusKarier, ['Bekerja (Full Time)', 'Wiraswasta'], true);

            // 1. Tautkan atau buat entitas Perusahaan riil
            $company = null;
            $atasan = null;

            if ($hasCareer && $companyName) {
                $company = $companiesCache->get($companyName);
                if (! $company) {
                    $company = Perusahaan::firstOrCreate(
                        ['nama_perusahaan' => $companyName],
                        [
                            'kode_provinsi' => $propinsiDiy->kode_provinsi,
                            'kode_kabupaten' => $kabSleman->kode_kabupaten,
                            'alamat' => 'Yogyakarta, Indonesia',
                            'sektor' => 'Industri & Layanan Profesional',
                            'skala' => 'Nasional',
                            'jenis_perusahaan' => str_contains($companyName, 'RS ') ? 'Organisasi non-profit/Lembaga Swadaya Masyarakat' : (str_contains($companyName, 'PT Bank') ? 'BUMN/BUMD' : 'Perusahaan swasta'),
                            'jenis_lokasi' => 'Dalam Negeri',
                            'kode_negara' => 'ID',
                            'status_verifikasi' => 'Terverifikasi',
                        ]
                    );
                    $companiesCache->put($companyName, $company);
                }

                // Tentukan nama atasan riil
                $sectorKey = match ($prodiCode) {
                    '71', '72' => 'IT',
                    '11', '12' => 'Business',
                    '61' => 'Medical',
                    '21', '22' => 'Architecture',
                    '41', '42' => 'Biotek',
                    default => 'Humanities',
                };
                $managerList = $managerNames[$sectorKey];
                $managerName = $managerList[$idx % count($managerList)];

                $atasan = Atasan::firstOrCreate(
                    [
                        'perusahaan_id' => $company->id,
                        'nama' => $managerName,
                    ],
                    [
                        'email' => strtolower(str_replace([' ', '.', ','], '', explode(' ', $managerName)[0])).$idx.'@perusahaan.co.id',
                        'telepon' => '0812'.str_pad((string) (20000000 + $idx), 8, '0', STR_PAD_LEFT),
                    ]
                );
            }

            // 2. Akun User
            $user = User::updateOrCreate(
                ['username' => $nim],
                [
                    'name' => $nama,
                    'email' => $email,
                    'password' => $defaultHashedPassword,
                    'role' => 'alumni',
                    'prodi_id' => $prodi->id,
                    'must_change_password' => false,
                ]
            );

            // 3. Data Akademik
            $totalSks = $prodiCode === '61' ? 160 : 144;
            $birthDate = ($angkatan - 18).'-'.str_pad((string) (($idx % 12) + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) (($idx % 28) + 1), 2, '0', STR_PAD_LEFT);

            $dataAkademik = DataAkademik::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama' => $nama,
                    'email_pribadi' => $email,
                    'angkatan_masuk' => $angkatan,
                    'status_mahasiswa' => 'Lulus',
                    'tahun_akademik_lulus' => 'Genap '.($tahunLulus - 1).'/'.$tahunLulus,
                    'tahun_lulus' => $tahunLulus,
                    'tanggal_lulus' => $tahunLulus.'-08-15',
                    'ip_kumulatif' => $ipk,
                    'total_sks' => $totalSks,
                    'total_angka_kualitas' => round($ipk * $totalSks, 2),
                    'tanggal_lahir' => $birthDate,
                    'jenis_kelamin' => $gender,
                    'warga_negara' => 'WNI',
                ]
            );

            // 4. Data Yudisium
            $yudisium = Yudisium::updateOrCreate(
                ['nim' => $nim],
                [
                    'judul_ta' => 'Kajian Strategis dan Penerapan Komprehensif Bidang '.$prodi->nama_prodi,
                    'judul_ta_inggris' => 'Strategic Study and Application in '.$prodi->nama_prodi,
                    'status_lulus' => 'Lulus',
                    'keterangan_hasil_yudisium' => $ipk >= 3.75 ? 'Lulus dengan Pujian (Cumlaude)' : 'Sangat Memuaskan',
                ]
            );

            // 5. Gaji Realistis (Rp 5.500.000 s/d Rp 14.500.000)
            $salary = $statusKarier === 'Bekerja (Full Time)' || $statusKarier === 'Wiraswasta'
                ? 5500000 + (($idx * 350000) % 9000000)
                : null;

            // 6. Biodata Karier
            $biodata = Biodata::updateOrCreate(
                ['nim' => $nim],
                [
                    'user_id' => $user->id,
                    'prodi_id' => $prodi->id,
                    'yudisium_id' => $yudisium->id,
                    'email_pribadi' => $email,
                    'nomor_telepon' => '0812'.str_pad((string) (10000000 + $idx), 8, '0', STR_PAD_LEFT),
                    'alamat_saat_ini' => 'Jl. Kaliurang Km. '.(($idx % 9) + 1).' No. '.($idx + 5).', Sleman',
                    'kelurahan' => 'Condongcatur',
                    'kecamatan' => 'Depok',
                    'kode_kabupaten' => $kabSleman->kode_kabupaten,
                    'kode_provinsi' => $propinsiDiy->kode_provinsi,
                    'kode_pos' => '55281',
                    'linkedin_url' => $statusKarier ? 'https://www.linkedin.com/in/'.strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z ]/', '', $nama))) : null,
                    'linkedin_username' => $statusKarier ? strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z ]/', '', $nama))) : null,
                    'skills' => $statusKarier ? 'Keahlian Bidang '.$prodi->nama_prodi.', Manajemen Tim, Analisis Data' : null,
                    'experience' => $hasCareer ? 'Pengalaman profesional pada '.$companyName : null,
                    'perusahaan_id' => $hasCareer ? $company?->id : null,
                    'atasan_id' => $hasCareer ? $atasan?->id : null,
                    'kategori_pekerjaan' => $statusKarier,
                    'posisi_jabatan' => $hasCareer ? $this->resolveJobTitle($prodiCode, $statusKarier, $idx) : null,
                    'bidang_pekerjaan' => $hasCareer ? $this->resolveJobField($prodiCode) : null,
                    'posisi_wiraswasta' => $statusKarier === 'Wiraswasta' ? $this->resolveEntrepreneurRole($idx) : null,
                    'pendidikan_tingkat' => $statusKarier === 'Melanjutkan Pendidikan' ? 'S2' : null,
                    'perguruan_tinggi' => $statusKarier === 'Melanjutkan Pendidikan' ? 'Universitas Gadjah Mada' : null,
                    'pendidikan_prodi' => $statusKarier === 'Melanjutkan Pendidikan' ? 'Magister '.$prodi->nama_prodi : null,
                    'gaji' => $salary,
                    'jenis_pekerjaan' => $hasCareer ? 'Profesional' : null,
                ]
            );

            // 7. Jika Alumni Responden: Rekam Jawaban Kuesioner Komprehensif
            if ($isRespondent) {
                $this->seedUniversityResponses(
                    $biodata,
                    $idx,
                    $statusKarier,
                    $salary,
                    $methodsScores,
                    $universityQuestions
                );

                $prodiSection = $prodiSections->get($prodi->id);
                if ($prodiSection) {
                    $prodiQuestions = $prodiQuestionsBySection->get($prodiSection->id, collect());
                    $this->seedProdiResponses($biodata, $prodiSection->id, $idx, $prodiQuestions);
                }
            }

            $createdCount++;
        }

        // 8. Bulk insert seluruh jawaban kuesioner
        if (! empty($this->pendingAnswers)) {
            collect($this->pendingAnswers)
                ->chunk(500)
                ->each(function (Collection $rows): void {
                    DB::table('kuesioner_jawaban')->insert($rows->all());
                });
            $this->pendingAnswers = [];
        }

        $this->call(EvaluasiAtasanSimulationSeeder::class);

        $this->command?->info("Pembersihan & Seeding kohort 532 alumni riil selesai! {$createdCount} alumni terdata.");
    }

    /**
     * Menyusun seluruh respon kuesioner universitas untuk 22 indikator analitik.
     *
     * @param  array<int, int>  $methodsScores
     * @param  Collection<int, KuesionerPertanyaan>  $questions
     */
    private function seedUniversityResponses(
        Biodata $biodata,
        int $index,
        ?string $statusKarier,
        ?int $salary,
        array $methodsScores,
        Collection $questions
    ): void {
        $scaleDescriptions = [
            1 => 'Tidak Sama Sekali',
            2 => 'Kurang',
            3 => 'Cukup Besar',
            4 => 'Besar',
            5 => 'Sangat Besar',
        ];

        $hasCareer = in_array($statusKarier, ['Bekerja (Full Time)', 'Wiraswasta'], true);
        $isFurtherStudy = $statusKarier === 'Melanjutkan Pendidikan';

        // Pilihan saluran F4 menggunakan kode resmi & label
        $f4Channels = [
            ['kode' => 'F404', 'label' => 'Mencari lewat internet/iklan online/milis'],
            ['kode' => 'F413', 'label' => 'Melalui penempatan kerja atau magang'],
            ['kode' => 'F411', 'label' => 'Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll)'],
            ['kode' => 'F408', 'label' => 'Memeroleh informasi dari pusat/kantor pengembangan karir fakultas/universitas'],
            ['kode' => 'F405', 'label' => 'Dihubungi oleh perusahaan'],
            ['kode' => 'F403', 'label' => 'Pergi ke bursa/pameran kerja'],
            ['kode' => 'F410', 'label' => 'Membangun jejaring (network) sejak masih kuliah'],
            ['kode' => 'F412', 'label' => 'Membangun bisnis sendiri'],
            ['kode' => 'F402', 'label' => 'Melamar ke perusahaan tanpa mengetahui lowongan yang ada'],
            ['kode' => 'F414', 'label' => 'Bekerja di tempat yang sama dengan tempat kerja semasa kuliah'],
            ['kode' => 'F401', 'label' => 'Melalui iklan di koran/majalah, brosur'],
            ['kode' => 'F407', 'label' => 'Menghubungi agen tenaga kerja komersial/swasta'],
            ['kode' => 'F406', 'label' => 'Menghubungi Kemenakertrans'],
            ['kode' => 'F409', 'label' => 'Menghubungi kantor kemahasiswaan/hubungan alumni'],
            ['kode' => 'F415', 'label' => 'Lainnya'],
        ];

        // Masa tunggu kerja F504 & F502/F506
        $startsBeforeGraduation = $index % 3 !== 0;
        $waitMonths = $startsBeforeGraduation ? ($index % 5) + 1 : ($index % 8) + 1;

        // Alignment kesesuaian studi F14
        $alignments = ['Sangat Erat', 'Erat', 'Cukup Erat', 'Kurang Erat', 'Tidak Sama Sekali'];
        $alignment = $alignments[$index % count($alignments)];

        foreach ($questions as $question) {
            $code = strtoupper((string) $question->kode_pertanyaan);
            $answer = null;
            $kodeOpsi = null;
            $answerJson = null;

            // Indikator 02: Metode Pembelajaran F21 s/d F27
            if (preg_match('/^F2([1-7])$/', $code, $matches)) {
                $methodIdx = ((int) $matches[1]) - 1;
                $score = $methodsScores[$methodIdx] ?? 4;
                $answer = $scaleDescriptions[$score] ?? 'Besar';
                $kodeOpsi = (string) $score;
            } elseif ($code === 'F8') {
                $answer = $statusKarier === 'Wiraswasta'
                    ? 'Wiraswasta'
                    : ($hasCareer ? 'Bekerja (full time/part time)' : ($isFurtherStudy ? 'Melanjutkan Pendidikan' : 'Tidak Kerja tetapi sedang mencari kerja'));
            } elseif ($code === 'F504') {
                $answer = $hasCareer ? ($startsBeforeGraduation ? 'Ya' : 'Tidak') : 'Tidak';
            } elseif ($code === 'F502') {
                $answer = $hasCareer && $startsBeforeGraduation ? (string) $waitMonths : null;
            } elseif ($code === 'F506') {
                $answer = $hasCareer && ! $startsBeforeGraduation ? (string) $waitMonths : null;
            } elseif ($code === 'F505') {
                $answer = $salary ? (string) $salary : null;
            } elseif ($code === 'F12') {
                $answer = $isFurtherStudy ? 'Beasiswa Pemerintah' : null;
            } elseif ($code === 'F18B') {
                $answer = $isFurtherStudy ? (string) ($biodata->perguruan_tinggi ?: 'Universitas Gadjah Mada') : null;
            } elseif ($code === 'F18C') {
                $answer = $isFurtherStudy ? (string) ($biodata->pendidikan_prodi ?: 'Program Magister Pascasarjana') : null;
            } elseif ($code === 'F18D') {
                $answer = $isFurtherStudy ? '2025-09-01' : null;
            } elseif ($code === 'F3') {
                $answer = $hasCareer
                    ? ($startsBeforeGraduation ? "Sebelum lulus ... bulan: {$waitMonths}" : "Setelah lulus ... bulan: {$waitMonths}")
                    : 'Saya tidak mencari kerja';
            } elseif ($code === 'F4') {
                $otherJobDetails = [
                    'Lainnya: Rekomendasi dosen pembimbing skripsi dan kemitraan riset kampus',
                    'Lainnya: Dihubungi headhunter profesional melalui jejaring LinkedIn Talent',
                    'Lainnya: Portofolio GitHub pribadi dan kontrak freelance luar negeri',
                    'Lainnya: Program magang bersertifikat MSIB Kemendikbudristek',
                    'Lainnya: Diterima lewat program Management Trainee (MT) industri perbankan',
                    'Lainnya: Juara kompetisi hackathon nasional langsung direkrut venture capital',
                    'Lainnya: Mengikuti program bootcamp intensif software engineering kemitraan industri',
                    'Lainnya: Diajak rekan senior mendirikan agensi digital mandiri',
                    'Lainnya: Ditawari posisi purnawaktu langsung dari tempat magang kuliah',
                    'Lainnya: Komunitas pengembang open-source dan konferensi teknologi',
                    'Lainnya: Rekomendasi jejaring alumni UKDW di industri manufaktur',
                    'Lainnya: Lolos seleksi CPNS kementerian pusat bidang teknologi informasi',
                ];

                // Pilih 1 s/d 2 saluran pencarian kerja
                $chosenChannel1 = $f4Channels[$index % count($f4Channels)];
                $chosenChannel2 = $f4Channels[($index + 3) % count($f4Channels)];

                $label1 = $chosenChannel1['label'];
                if ($chosenChannel1['kode'] === 'F415') {
                    $label1 = $otherJobDetails[$index % count($otherJobDetails)];
                }
                $label2 = $chosenChannel2['label'];
                if ($chosenChannel2['kode'] === 'F415') {
                    $label2 = $otherJobDetails[($index + 1) % count($otherJobDetails)];
                }

                if ($index % 2 === 0 && $chosenChannel1['kode'] !== $chosenChannel2['kode']) {
                    $answer = $label1.', '.$label2;
                    $answerJson = json_encode([$label1, $label2], JSON_UNESCAPED_UNICODE);
                    $kodeOpsi = json_encode([$chosenChannel1['kode'], $chosenChannel2['kode']], JSON_UNESCAPED_UNICODE);
                } else {
                    $answer = $label1;
                    $answerJson = json_encode([$label1], JSON_UNESCAPED_UNICODE);
                    $kodeOpsi = $chosenChannel1['kode'];
                }
            } elseif ($code === 'F6') {
                $answer = (string) (3 + ($index % 12));
            } elseif ($code === 'F7') {
                $answer = (string) max(1, (int) floor((3 + ($index % 12)) * 0.45));
            } elseif ($code === 'F7A') {
                $answer = (string) max(1, (int) floor((3 + ($index % 12)) * 0.25));
            } elseif ($code === 'F10') {
                if ($hasCareer) {
                    $answer = 'Tidak';
                } else {
                    $f10Pool = [
                        'Tidak, tapi saya sedang menunggu hasil lamaran kerja',
                        'Tidak, tapi saya sedang menunggu hasil lamaran kerja',
                        'Ya, saya akan mulai bekerja dalam 2 minggu ke depan',
                        'Ya, tapi saya belum pasti akan bekerja dalam 2 minggu ke depan',
                        'Lainnya',
                    ];
                    $answer = $f10Pool[$index % count($f10Pool)];
                }
            } elseif ($code === 'F11') {
                $answer = $statusKarier === 'Wiraswasta'
                    ? 'Wiraswasta/perusahaan sendiri'
                    : ['Perusahaan swasta', 'BUMN/BUMD', 'Instansi pemerintah', 'Organisasi non-profit/LSM'][$index % 4];
            } elseif ($code === 'F14') {
                $answer = $alignment;
            } elseif ($code === 'F15') {
                $answer = $index % 4 === 0 ? 'Tingkat yang Lebih Tinggi' : 'Tingkat yang Sama';
            } elseif ($code === 'F16' && in_array($alignment, ['Kurang Erat', 'Tidak Sama Sekali'], true)) {
                $otherMismatch = [
                    'Lainnya: Tertarik mendalami bisnis teknologi digital dan pengembangan e-commerce',
                    'Lainnya: Menemukan minat baru di bidang UI/UX design dan manajemen produk teknologi',
                    'Lainnya: Melanjutkan usaha rintisan keluarga berbasis industri manufaktur kreatif',
                    'Lainnya: Tawaran kompensasi gaji dan benefit karir jauh di atas rata-rata industri',
                    'Lainnya: Budaya kerja fleksibel remote working dengan klien internasional',
                    'Lainnya: Peluang promosi karir manajerial terbuka lebih cepat di perusahaan rintisan',
                ];
                $reasonsList = [
                    'Saya belum mendapatkan pekerjaan yang lebih sesuai',
                    'Gaji dan benefit di pekerjaan ini lebih menjanjikan',
                    'Pekerjaan ini lebih prospektif untuk masa depan',
                    $otherMismatch[$index % count($otherMismatch)],
                ];
                $answer = $reasonsList[$index % count($reasonsList)];
                if (str_starts_with($answer, 'Lainnya:')) {
                    $kodeOpsi = 'F1613';
                }
            } elseif (preg_match('/^F17([AB])([1-7])$/', $code, $cMatches)) {
                // Gap Kompetensi (F17a1-a7 dan F17b1-b7)
                $type = $cMatches[1];
                $cIdx = (int) $cMatches[2];
                $compScore = $type === 'A'
                    ? min(5, max(1, 3 + (($index + $cIdx) % 3) - 1))
                    : min(5, max(1, 4 + (($index * 2 + $cIdx) % 2)));
                $answer = $scaleDescriptions[$compScore] ?? 'Tinggi';
                $kodeOpsi = (string) $compScore;
            } else {
                $option = $question->opsi?->first();
                // Pertanyaan yang belum memiliki aturan fixture dibiarkan kosong;
                // jangan membuat nilai sintetis yang dapat terbaca sebagai jawaban nyata.
                $answer = $option ? (string) $option->label : null;
                $kodeOpsi = $option?->kode_opsi;
            }

            if ($answer !== null) {
                $matchedOpsi = $question->opsi?->first(fn ($o) => mb_strtolower(trim((string) $o->label)) === mb_strtolower(trim((string) $answer)));

                $this->pendingAnswers[] = [
                    'responden_type' => 'alumni',
                    'biodata_id' => $biodata->id,
                    'evaluasi_atasan_id' => null,
                    'section_id' => $question->section_id,
                    'pertanyaan_id' => $question->id,
                    'kode_pertanyaan' => $question->kode_pertanyaan,
                    'opsi_id' => $matchedOpsi?->id,
                    'kode_opsi' => $kodeOpsi ?: $matchedOpsi?->kode_opsi,
                    'jawaban_teks' => $answer,
                    'jawaban_json' => $answerJson,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
    }

    /**
     * Menyusun respon kuesioner tingkat program studi.
     *
     * @param  Collection<int, KuesionerPertanyaan>  $questions
     */
    private function seedProdiResponses(
        Biodata $biodata,
        int $sectionId,
        int $index,
        Collection $questions
    ): void {
        $aspirations = [
            'Semoga Program Studi semakin unggul dan menghasilkan lulusan yang adaptif dengan perkembangan teknologi industri modern.',
            'Harapan saya prodi dapat memperluas jejaring kemitraan magang internasional dan riset kolaboratif tingkat global.',
            'Semoga akreditasi unggul dapat terus dipertahankan dan fasilitas laboratorium komputasi awan semakin lengkap.',
            'Harapan saya komunitas alumni semakin solid dan aktif berbagi peluang karir serta mentorship bagi mahasiswa aktif.',
            'Semoga kurikulum pembelajaran terus mengintegrasikan sertifikasi kompetensi profesional yang diakui industri global.',
            'Harapan saya prodi menjadi rujukan utama riset transformasi digital dan pengembangan sistem informasi berkelanjutan di Indonesia.',
            'Semoga lulusan prodi semakin diperhitungkan dan menduduki posisi strategis di perusahaan teknologi multinasional.',
            'Harapan saya prodi tetap menjaga nilai integritas, etika kristiani, dan kepedulian sosial yang menjadi ciri khas UKDW.',
        ];

        $feedbacks = [
            'Kurikulum perlu lebih banyak memasukkan studi kasus industri nyata dan praktik arsitektur cloud computing terkini.',
            'Perbanyak program magang bersertifikat dan workshop persiapan karir serta simulasi wawancara kerja teknis.',
            'Tingkatkan spesifikasi perangkat laboratorium komputer untuk mendukung pemodelan machine learning dan data engineering.',
            'Berikan pembekalan soft skills komunikasi profesional dan kemampuan bahasa Inggris teknis sejak semester awal.',
            'Dosen sangat inspiratif, mohon fasilitasi sertifikasi internasional resmi (AWS, Google Cloud, Cisco) diintegrasikan ke mata kuliah.',
            'Tingkatkan kolaborasi proyek lintas prodi untuk melatih kepemimpinan tim dan pemecahan masalah interdisipliner.',
            'Perkuat bursa karir kampus dan jejaring alumni untuk mempercepat penyerapan kerja lulusan baru di industri modern.',
            'Perbanyak sesi kuliah dosen tamu dari praktisi industri teknologi dan startup ternama.',
            'Perbarui modul praktikum dengan framework pemrograman modern yang paling banyak digunakan di industri saat ini.',
            'Dukungan akses jurnal internasional dan lisensi software enterprise perlu diperluas bagi mahasiswa tingkat akhir.',
        ];

        foreach ($questions as $question) {
            $options = $question->opsi?->values() ?? collect();
            if ($options->isEmpty()) {
                // Tangani pertanyaan teks terbuka kualitatif (harapan dan saran alumni)
                if (in_array($question->tipe, ['text', 'textarea'], true)) {
                    $qCode = strtoupper((string) $question->kode_pertanyaan);
                    $qText = strtolower((string) $question->teks_pertanyaan);

                    if (str_contains($qCode, '01') || str_contains($qText, 'harapan')) {
                        $answer = $aspirations[($biodata->id + $index) % count($aspirations)];
                    } else {
                        $answer = $feedbacks[($biodata->id + $index) % count($feedbacks)];
                    }

                    $this->pendingAnswers[] = [
                        'responden_type' => 'alumni',
                        'biodata_id' => $biodata->id,
                        'evaluasi_atasan_id' => null,
                        'section_id' => $sectionId,
                        'pertanyaan_id' => $question->id,
                        'kode_pertanyaan' => $question->kode_pertanyaan,
                        'opsi_id' => null,
                        'kode_opsi' => null,
                        'jawaban_teks' => $answer,
                        'jawaban_json' => json_encode([$answer], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                continue;
            }

            $primaryOption = $options->get(($biodata->id + $question->id) % $options->count());
            $answer = $primaryOption?->label ?? 'Data respon prodi';
            $kodeOpsi = $primaryOption?->kode_opsi;
            $answerJson = null;

            if ($question->tipe === 'multiple_choice' || $question->tipe === 'checkbox') {
                $answerJson = json_encode([$answer], JSON_UNESCAPED_UNICODE);
            }

            $this->pendingAnswers[] = [
                'responden_type' => 'alumni',
                'biodata_id' => $biodata->id,
                'evaluasi_atasan_id' => null,
                'section_id' => $sectionId,
                'pertanyaan_id' => $question->id,
                'kode_pertanyaan' => $question->kode_pertanyaan,
                'opsi_id' => $primaryOption?->id,
                'kode_opsi' => $kodeOpsi,
                'jawaban_teks' => $answer,
                'jawaban_json' => $answerJson,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    private function resolveJobTitle(string $prodiCode, string $career, int $idx): string
    {
        if ($career === 'Wiraswasta') {
            return $this->resolveEntrepreneurRole($idx);
        }

        return match ($prodiCode) {
            '71' => ['Software Engineer', 'Fullstack Developer', 'DevOps Specialist', 'Data Engineer'][$idx % 4],
            '72' => ['IT Business Analyst', 'Product Manager', 'Data Analyst', 'ERP Consultant'][$idx % 4],
            '11' => ['Marketing Specialist', 'Operational Manager', 'Financial Planner', 'HR Business Partner'][$idx % 4],
            '12' => ['Senior Auditor', 'Tax Specialist', 'Management Accountant', 'Financial Analyst'][$idx % 4],
            '21' => ['Junior Architect', 'BIM Coordinator', 'Urban Designer', 'Project Architect'][$idx % 4],
            '22' => ['Product Designer', 'Industrial Designer', 'UX/UI Designer', 'Design Researcher'][$idx % 4],
            '41' => ['Microbiologist', 'Laboratory Analyst', 'Quality Control Specialist', 'Conservation Officer'][$idx % 4],
            '42' => ['Food Technologist', 'QA/QC Food Safety Specialist', 'R&D Food Formulator', 'Production Supervisor'][$idx % 4],
            '61' => ['Dokter Internship', 'Dokter Residen', 'Dokter Umum', 'Medical Officer'][$idx % 4],
            '31' => ['Pendeta Jemaat', 'Pelayan Pastoral', 'Konselor Rohani', 'Tenaga Pendidik Teologi'][$idx % 4],
            '81' => ['English Educator', 'Curriculum Developer', 'Instructional Designer', 'Language Specialist'][$idx % 4],
            default => ['Social Researcher', 'Cultural Specialist', 'Community Organizer', 'Humanities Consultant'][$idx % 4],
        };
    }

    /**
     * Menyediakan peran wirausaha terstruktur untuk fixture status Wiraswasta.
     *
     * @param  int  $idx  Indeks deterministik alumni pada fixture.
     * @return string Peran usaha yang mencakup founder, freelancer, dan usaha umum.
     */
    private function resolveEntrepreneurRole(int $idx): string
    {
        return match ($idx % 4) {
            0 => 'Founder / Owner',
            1 => 'Owner / Managing Director',
            2 => 'Freelancer / Konsultan Mandiri',
            default => 'Pengelola Usaha',
        };
    }

    private function resolveJobField(string $prodiCode): string
    {
        return match ($prodiCode) {
            '71', '72' => 'Teknologi Informasi & Rekayasa Perangkat Lunak',
            '11', '12' => 'Keuangan, Perbankan, Bisnis & Akuntansi',
            '21', '22' => 'Arsitektur, Desain Produk & Industri Kreatif',
            '41', '42' => 'Bioteknologi, Pangan, Pertanian & Farmasi',
            '61' => 'Pelayanan Kesehatan & Medis',
            '31' => 'Pelayanan Gerejawi, Agama & Pembinaan Mental',
            '81' => 'Pendidikan, Pelatihan & Pengajaran Bahasa',
            default => 'Sosial, Kebudayaan, Riset & Pengembangan Masyarakat',
        };
    }
}
