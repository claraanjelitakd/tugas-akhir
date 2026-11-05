<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
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
 * Mengisi skenario demo dashboard beserta jawaban kuesioner yang bervariasi.
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
     * Menyiapkan data demo lintas tahun untuk dashboard tanpa menimpa data lama.
     *
     * Jumlah alumni per prodi merepresentasikan populasi lulusan, sedangkan jumlah
     * responden sengaja dibuat berbeda agar grafik response rate memiliki variasi.
     */
    public function run(): void
    {
        $this->pendingAnswers = [];
        $prodis = Prodi::query()->get()->keyBy('kode_prodi');
        $propinsi = Propinsi::query()->where('kode_provinsi', '34')->first();
        $kabupaten = Kabupaten::query()->where('kode_kabupaten', '34.04')->first();
        $defaultHashedPassword = Hash::make('password123');

        $universityQuestions = KuesionerPertanyaan::query()
            ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
            ->join('kuesioner_section as s', 's.id', '=', 'sp.section_id')
            ->where('s.target', 'univ')
            ->where('sp.wajib', true)
            ->where('kuesioner_pertanyaan.tipe', '!=', 'header')
            ->where('kuesioner_pertanyaan.is_active', true)
            ->with('opsi')
            ->select('kuesioner_pertanyaan.*', 'sp.section_id')
            ->orderBy('sp.section_id')
            ->orderBy('sp.order')
            ->get();

        if ($prodis->isEmpty() || ! $propinsi || ! $kabupaten) {
            $this->command?->warn('DashboardScenarioSeeder dilewati karena master prodi atau wilayah belum tersedia.');

            return;
        }

        $targetsByYear = [
            2024 => [
                'target' => ['71' => 60, '72' => 42, '11' => 64, '12' => 48, '21' => 30, '22' => 24, '31' => 18, '41' => 22, '42' => 26, '61' => 36, '81' => 45, '82' => 20],
                'respondents' => ['71' => 38, '72' => 29, '11' => 43, '12' => 28, '21' => 21, '22' => 14, '31' => 12, '41' => 16, '42' => 18, '61' => 25, '81' => 30, '82' => 11],
            ],
            2025 => [
                'target' => ['71' => 70, '72' => 48, '11' => 72, '12' => 55, '21' => 34, '22' => 27, '31' => 20, '41' => 25, '42' => 30, '61' => 38, '81' => 50, '82' => 22],
                'respondents' => ['71' => 46, '72' => 32, '11' => 50, '12' => 35, '21' => 22, '22' => 18, '31' => 15, '41' => 17, '42' => 23, '61' => 27, '81' => 37, '82' => 13],
            ],
            2026 => [
                'target' => ['71' => 80, '72' => 50, '11' => 81, '12' => 60, '21' => 36, '22' => 28, '31' => 20, '41' => 26, '42' => 32, '61' => 40, '81' => 55, '82' => 24],
                'respondents' => ['71' => 50, '72' => 35, '11' => 60, '12' => 39, '21' => 24, '22' => 17, '31' => 13, '41' => 19, '42' => 22, '61' => 30, '81' => 40, '82' => 14],
            ],
        ];

        $createdCount = 0;

        foreach ($targetsByYear as $year => $configuration) {
            foreach ($configuration['target'] as $prodiCode => $targetCount) {
                $prodi = $prodis->get($prodiCode);
                if (! $prodi) {
                    continue;
                }

                $respondentCount = $configuration['respondents'][$prodiCode] ?? 0;
                $prodiSection = KuesionerSection::where('target', 'prodi')
                    ->where('prodi_id', $prodi->id)
                    ->first();

                $prodiQuestions = $prodiSection
                    ? KuesionerPertanyaan::query()
                        ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
                        ->where('sp.section_id', $prodiSection->id)
                        ->where('kuesioner_pertanyaan.tipe', '!=', 'header')
                        ->with('opsi')
                        ->select('kuesioner_pertanyaan.*', 'sp.section_id')
                        ->orderBy('sp.order')
                        ->get()
                    : collect();

                for ($sequence = 1; $sequence <= $targetCount; $sequence++) {
                    $nim = $prodiCode.substr((string) ($year - 4), -2).str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                    if ($this->seedAlumni($nim, $sequence, $year, $respondentCount, $prodi, $propinsi, $kabupaten, $universityQuestions, $prodiQuestions, $defaultHashedPassword)) {
                        $createdCount++;
                    }
                }
            }
        }

        if (! empty($this->pendingAnswers)) {
            collect($this->pendingAnswers)
                ->chunk(500)
                ->each(function (Collection $rows): void {
                    DB::table('kuesioner_jawaban')->insert($rows->all());
                });
            $this->pendingAnswers = [];
        }

        $this->command?->info("Dashboard scenario selesai. {$createdCount} alumni baru dibuat tanpa mengubah data lama.");
    }

    /**
     * @param  Collection<int, KuesionerPertanyaan>  $universityQuestions
     * @param  Collection<int, KuesionerPertanyaan>  $prodiQuestions
     */
    private function seedAlumni(
        string $nim,
        int $sequence,
        int $graduationYear,
        int $respondentCount,
        Prodi $prodi,
        Propinsi $propinsi,
        Kabupaten $kabupaten,
        $universityQuestions,
        $prodiQuestions,
        string $defaultHashedPassword
    ): bool {
        if (DataAkademik::query()->where('nim', $nim)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($nim, $sequence, $graduationYear, $respondentCount, $prodi, $propinsi, $kabupaten, $universityQuestions, $prodiQuestions, $defaultHashedPassword): bool {
            $isRespondent = $sequence <= $respondentCount;
            $isProfileComplete = $isRespondent || $sequence % 11 !== 0;
            $firstNames = ['Aurelia', 'Bagas', 'Catherine', 'Dimas', 'Elisa', 'Felix', 'Gisella', 'Hendra', 'Intan', 'Jovan', 'Kezia', 'Lukas'];
            $lastNames = ['Pratama', 'Wijaya', 'Kusuma', 'Santoso', 'Nugraha', 'Lestari', 'Haryanto', 'Saputra', 'Wibowo', 'Handayani', 'Setiawan', 'Permata'];
            $name = $firstNames[($sequence - 1) % count($firstNames)].' '.$lastNames[($sequence + (int) $prodi->kode_prodi) % count($lastNames)].' '.$prodi->kode_prodi.'-'.$sequence;
            $email = 'demo.'.strtolower($nim).'@alumni.ukdw.ac.id';
            $entryYear = $graduationYear - 4;
            $birthDate = ($entryYear - 18).'-'.str_pad((string) (($sequence % 9) + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) (($sequence % 26) + 1), 2, '0', STR_PAD_LEFT);
            $salary = 5000000 + (($sequence * 375000) % 8500000);
            $hasCareer = $isRespondent && $sequence % 5 !== 0;
            $company = $hasCareer ? Perusahaan::query()->first() : null;
            $atasan = $hasCareer ? Atasan::firstOrCreate(
                ['email' => 'atasan.demo.'.strtolower($nim).'@example.test'],
                [
                    'perusahaan_id' => $company?->id,
                    'nama' => 'Atasan '.$prodi->nama_prodi.' '.$sequence,
                    'telepon' => '0812'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
                ],
            ) : null;

            $user = User::create([
                'username' => $nim,
                'name' => $name,
                'email' => $email,
                'password' => $defaultHashedPassword,
                'role' => 'alumni',
                'prodi_id' => $prodi->id,
                'must_change_password' => true,
            ]);

            $ipk = round(3.20 + (($sequence * 7) % 75) / 100, 2);
            $totalSks = $prodi->kode_prodi === '61' ? 160 : 144;
            $dataAkademik = DataAkademik::create([
                'nim' => $nim,
                'nama' => $name,
                'email_pribadi' => $email,
                'angkatan_masuk' => $entryYear,
                'status_mahasiswa' => 'Lulus',
                'tahun_akademik_lulus' => 'Genap '.($graduationYear - 1).'/'.$graduationYear,
                'tahun_lulus' => $graduationYear,
                'tanggal_lulus' => $graduationYear.'-08-15',
                'ip_kumulatif' => $ipk,
                'total_sks' => $totalSks,
                'total_angka_kualitas' => round($ipk * $totalSks, 2),
                'tanggal_lahir' => $birthDate,
                'jenis_kelamin' => $sequence % 2 === 0 ? 'Perempuan' : 'Laki-laki',
                'warga_negara' => 'WNI',
            ]);

            $biodata = Biodata::create([
                'user_id' => $user->id,
                'nim' => $nim,
                'prodi_id' => $prodi->id,
                'email_pribadi' => $email,
                'nomor_telepon' => '0812'.str_pad((string) (10000000 + $sequence), 8, '0', STR_PAD_LEFT),
                'alamat_saat_ini' => 'Jl. Kaliurang Km. '.(($sequence % 9) + 1).' No. '.($sequence + 10).', Sleman',
                'kelurahan' => 'Condongcatur',
                'kecamatan' => 'Depok',
                'kode_kabupaten' => $kabupaten->kode_kabupaten,
                'kode_provinsi' => $propinsi->kode_provinsi,
                'kode_pos' => '55281',
                'linkedin_url' => $sequence % 4 === 0 ? null : 'https://www.linkedin.com/in/demo-'.$nim,
                'linkedin_username' => $sequence % 4 === 0 ? null : 'demo-'.$nim,
                'skills' => $isRespondent ? 'Komunikasi, Analisis Data, '.$prodi->nama_prodi : null,
                'experience' => $isRespondent ? 'Pengalaman kerja pada bidang '.$prodi->nama_prodi : null,
                'perusahaan_id' => $company?->id,
                'atasan_id' => $atasan?->id,
                'kategori_pekerjaan' => $isRespondent ? ($hasCareer ? 'Bekerja (Full Time)' : 'Melanjutkan Pendidikan') : null,
                'posisi_jabatan' => $hasCareer ? ['Staff', 'Analyst', 'Developer', 'Coordinator'][$sequence % 4] : null,
                'bidang_pekerjaan' => $hasCareer ? ['Teknologi Informasi', 'Administrasi / Tata Usaha', 'Keuangan / Akuntansi', 'Lainnya'][$sequence % 4] : null,
                'pendidikan_tingkat' => $isRespondent && ! $hasCareer ? 'S2' : null,
                'perguruan_tinggi' => $isRespondent && ! $hasCareer ? 'Universitas Gadjah Mada' : null,
                'pendidikan_prodi' => $isRespondent && ! $hasCareer ? 'Magister '.$prodi->nama_prodi : null,
                'gaji' => $hasCareer ? $salary : null,
                'jenis_pekerjaan' => $hasCareer ? 'Profesional' : null,
            ]);

            $yudisium = Yudisium::create([
                'nim' => $nim,
                'judul_ta' => 'Analisis Data Alumni '.$prodi->nama_prodi.' Tahun '.$graduationYear,
                'judul_ta_inggris' => 'Alumni Data Analysis for '.$prodi->nama_prodi,
                'status_lulus' => 'Lulus',
                'keterangan_hasil_yudisium' => $dataAkademik->ip_kumulatif >= 3.75 ? 'Lulus dengan Pujian (Cumlaude)' : 'Sangat Memuaskan',
            ]);
            $biodata->update(['yudisium_id' => $yudisium->id]);

            if ($isRespondent) {
                $this->seedUniversityResponses($biodata, $graduationYear, $salary, $universityQuestions);
                $this->seedProdiResponses($biodata, $prodiQuestions);
            }

            return true;
        });
    }

    /**
     * @param  Collection<int, KuesionerPertanyaan>  $questions
     */
    private function seedUniversityResponses(Biodata $biodata, int $graduationYear, int $salary, $questions): void
    {
        foreach ($questions as $question) {
            $code = strtoupper((string) $question->kode_pertanyaan);
            if ($code === 'F506') {
                continue;
            }

            $answer = match ($code) {
                'F8' => 'Bekerja (full time/part time)',
                'F504' => 'Ya',
                'F502' => '3',
                'F505' => (string) $salary,
                'F14' => 'Sangat Erat',
                'F15' => 'Tingkat yang Sama',
                'F3' => 'Sebelum lulus ... bulan: 3',
                'F4' => 'Mencari lewat internet/iklan online/milis',
                'F6' => '4',
                'F7' => '3',
                'F7A' => '2',
                default => $this->defaultQuestionAnswer($question),
            };

            $opsi = $question->opsi?->first(fn ($o) => mb_strtolower(trim($o->label)) === mb_strtolower(trim((string) $answer)));

            $this->pendingAnswers[] = [
                'responden_type' => 'alumni',
                'biodata_id' => $biodata->id,
                'evaluasi_atasan_id' => null,
                'section_id' => $question->section_id,
                'pertanyaan_id' => $question->id,
                'kode_pertanyaan' => $question->kode_pertanyaan,
                'opsi_id' => $opsi?->id,
                'kode_opsi' => $opsi?->kode_opsi,
                'jawaban_teks' => $answer,
                'jawaban_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    /**
     * Menyimpan jawaban prodi demo dengan distribusi opsi yang tersebar.
     *
     * @param  Collection<int, KuesionerPertanyaan>  $questions
     */
    private function seedProdiResponses(Biodata $biodata, $questions): void
    {
        if ($questions->isEmpty()) {
            return;
        }

        $prodiSection = KuesionerSection::where('target', 'prodi')
            ->where('prodi_id', $biodata->prodi_id)
            ->first();
        $sectionId = $prodiSection?->id ?: 1;

        foreach ($questions as $question) {
            $options = $question->opsi?->values() ?? collect();
            $answerJson = null;
            $kodeOpsiResult = null;
            $answer = $options->first()?->label ?? 'Data simulasi '.$question->kode_pertanyaan;

            if (in_array($question->tipe, ['text', 'textarea'], true)) {
                $answer = 'Respon simulasi alumni untuk '.$question->teks_pertanyaan;
            } elseif ($options->isNotEmpty()) {
                $primaryOption = $options->get(($biodata->id + $question->id) % $options->count());
                $answer = $primaryOption?->label ?? $answer;
                $kodeOpsiResult = $primaryOption?->kode_opsi;

                if ($question->tipe === 'multiple_choice' || $question->tipe === 'checkbox') {
                    $selectedOptions = collect([$primaryOption]);
                    if ($options->count() > 1 && ($biodata->id + $question->id) % 3 === 0) {
                        $selectedOptions->push($options->get(($biodata->id + $question->id + 1) % $options->count()));
                    }
                    $selectedOptions = $selectedOptions->filter()->unique('id')->values();
                    $answer = $selectedOptions->pluck('label')->implode(', ');
                    $answerJson = json_encode($selectedOptions->pluck('label')->values()->all(), JSON_UNESCAPED_UNICODE);
                    $kodeOpsiResult = json_encode($selectedOptions->pluck('kode_opsi')->values()->all(), JSON_UNESCAPED_UNICODE);
                }
            }

            $opsi = $options->first(fn ($o) => $o->label === $answer);

            $this->pendingAnswers[] = [
                'responden_type' => 'alumni',
                'biodata_id' => $biodata->id,
                'evaluasi_atasan_id' => null,
                'section_id' => $sectionId,
                'pertanyaan_id' => $question->id,
                'kode_pertanyaan' => $question->kode_pertanyaan,
                'opsi_id' => $opsi?->id,
                'kode_opsi' => $kodeOpsiResult ?: $opsi?->kode_opsi,
                'jawaban_teks' => $answer,
                'jawaban_json' => $answerJson,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    private function defaultQuestionAnswer(KuesionerPertanyaan $question): string
    {
        $option = $question->opsi?->first();

        if ($option) {
            return (string) $option->label;
        }

        if (in_array($question->tipe, ['number', 'rating', 'scale'], true)) {
            return '4';
        }

        return 'Data simulasi alumni';
    }
}
