<?php

namespace Database\Seeders;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\Kabupaten;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menata ulang data alumni demo agar halaman analitik memiliki distribusi yang dapat diuji.
 *
 * Seeder ini hanya menyentuh biodata dengan akun demo yang dibuat oleh
 * DashboardScenarioSeeder. Data alumni non-demo tidak pernah dipilih atau diubah.
 */
class AnalyticsDemoDistributionSeeder extends Seeder
{
    /**
     * Profil penekanan metode pembelajaran per kode prodi untuk data demo.
     *
     * Urutan skor mengikuti F21 sampai F27. Profil ini memberi setiap prodi
     * pola agregat yang berbeda, sedangkan variasi kecil per alumni tetap
     * menjaga distribusi respons tidak tampak sintetis.
     *
     * @var array<string, array<int, int>>
     */
    private const LEARNING_METHOD_PROFILES = [
        '71' => [5, 4, 4, 5, 4, 3, 4],
        '72' => [4, 3, 5, 4, 4, 3, 5],
        '11' => [3, 4, 3, 4, 4, 3, 4],
        '12' => [3, 3, 3, 4, 3, 3, 4],
        '21' => [4, 4, 4, 3, 5, 4, 3],
        '22' => [4, 5, 4, 4, 4, 3, 4],
        '41' => [3, 4, 4, 3, 3, 4, 4],
        '42' => [4, 3, 4, 4, 3, 4, 3],
        '61' => [5, 4, 5, 4, 5, 4, 4],
        '31' => [3, 3, 4, 3, 4, 3, 4],
        '81' => [4, 5, 4, 5, 3, 4, 4],
        '82' => [3, 4, 3, 4, 3, 4, 5],
    ];

    /**
     * Baris jawaban yang dikumpulkan sebelum bulk upsert untuk menghindari
     * query per pertanyaan pada ratusan alumni demo.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $pendingTracerRows = [];

    /**
     * Membuat master perusahaan demo lintas provinsi dan memperbarui karier demo.
     *
     * Distribusi sengaja memuat alumni bekerja, alumni dengan gaji di bawah 1,2
     * kali UMP, dan alumni belum bekerja supaya semua cabang grafik dapat diuji.
     */
    public function run(): void
    {
        $demoBiodata = Biodata::query()
            ->with(['dataAkademik', 'prodi'])
            ->whereHas('user', fn ($query) => $query->where('email', 'like', 'demo.%@alumni.ukdw.ac.id'))
            ->whereIn('id', DB::table('kuesioner_jawaban')->select('biodata_id')->where('kode_pertanyaan', 'F8'))
            ->orderBy('id')
            ->get();

        if ($demoBiodata->isEmpty()) {
            $this->command?->warn('AnalyticsDemoDistributionSeeder dilewati karena alumni demo belum tersedia.');

            return;
        }

        $locations = $this->locations();
        $companies = collect([
            ['name' => 'PT Demo Analitik Yogyakarta', 'province' => '34', 'city' => '34.71'],
            ['name' => 'PT Demo Analitik Jakarta', 'province' => '31', 'city' => '31.74'],
            ['name' => 'PT Demo Analitik Jawa Barat', 'province' => '32', 'city' => '32.73'],
            ['name' => 'PT Demo Analitik Jawa Tengah', 'province' => '33', 'city' => '33.74'],
            ['name' => 'PT Demo Analitik Jawa Timur', 'province' => '35', 'city' => '35.78'],
        ])->map(function (array $definition) use ($locations): ?Perusahaan {
            $province = $locations['provinces'][$definition['province']] ?? null;
            $city = $locations['cities'][$definition['city']] ?? null;

            if (! $province || ! $city) {
                return null;
            }

            return Perusahaan::updateOrCreate(
                ['nama_perusahaan' => $definition['name']],
                [
                    'kode_provinsi' => $province->kode_provinsi,
                    'kode_kabupaten' => $city->kode_kabupaten,
                    'alamat' => 'Data simulasi analitik',
                    'sektor' => 'Data simulasi',
                    'skala' => 'Nasional',
                    'jenis_perusahaan' => 'Perusahaan swasta',
                    'jenis_lokasi' => 'Dalam Negeri',
                    'kode_negara' => 'ID',
                    'status_verifikasi' => 'Terverifikasi',
                ],
            );
        })->filter()->values();

        if ($companies->isEmpty()) {
            $this->command?->warn('AnalyticsDemoDistributionSeeder dilewati karena master wilayah belum lengkap.');

            return;
        }

        // Pastikan opsi F16 memakai satu pilihan "Lainnya" tanpa varian isian lama.
        $this->normalizeMismatchOptionData();

        // UMP diambil dari database agar syarat perbandingan gaji mengikuti tahun lulusan.
        $umpByProvinceYear = DB::table('ump')
            ->whereIn('kode_provinsi', $companies->pluck('kode_provinsi')->unique()->values())
            ->whereIn('tahun', [2024, 2025, 2026])
            ->get(['kode_provinsi', 'tahun', 'besaran'])
            ->keyBy(fn (object $row): string => $row->kode_provinsi.'-'.$row->tahun);
        $latestUmpByProvince = DB::table('ump')
            ->whereIn('kode_provinsi', $companies->pluck('kode_provinsi')->unique()->values())
            ->orderByDesc('tahun')
            ->get(['kode_provinsi', 'besaran'])
            ->groupBy('kode_provinsi')
            ->map(static fn (Collection $rows): object => $rows->first());

        $prodiSections = KuesionerSection::where('target', 'prodi')
            ->whereIn('prodi_id', $demoBiodata->pluck('prodi_id')->filter()->unique())
            ->get()
            ->keyBy('prodi_id');

        $prodiQuestions = KuesionerPertanyaan::query()
            ->whereIn('pemilik_prodi_id', $demoBiodata->pluck('prodi_id')->filter()->unique())
            ->where('tipe', '!=', 'header')
            ->with('opsi')
            ->get()
            ->groupBy('pemilik_prodi_id');

        $univSection = KuesionerSection::where('target', 'univ')->first();
        $univSectionId = $univSection?->id ?: 1;

        $questions = KuesionerPertanyaan::query()
            ->whereIn('kode_pertanyaan', [
                'F3', 'F4', 'F6', 'F7', 'F7A', 'F8', 'F10', 'F11', 'F12',
                'F14', 'F15', 'F16', 'F18b', 'F18c', 'F18d', 'F502', 'F504',
                'F505', 'F506', 'F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27',
                'F17a1', 'F17b1', 'F17a2', 'F17b2', 'F17a3', 'F17b3', 'F17a4',
                'F17b4', 'F17a5', 'F17b5', 'F17a6', 'F17b6', 'F17a7', 'F17b7',
            ])
            ->with(['opsi', 'sections'])
            ->get()
            ->keyBy(fn (KuesionerPertanyaan $question): string => strtoupper($question->kode_pertanyaan));

        DB::transaction(function () use ($demoBiodata, $companies, $questions, $prodiQuestions, $prodiSections, $univSectionId, $umpByProvinceYear, $latestUmpByProvince): void {
            $this->pendingTracerRows = [];

            foreach ($demoBiodata->values() as $index => $biodata) {
                $status = $this->employmentStatus($index);
                $hasCareer = in_array($status, ['Bekerja (full time/part time)', 'Wiraswasta'], true);

                if (! $hasCareer) {
                    $biodata->update([
                        'perusahaan_id' => null,
                        'atasan_id' => null,
                        'kategori_pekerjaan' => $status,
                        'posisi_jabatan' => null,
                        'gaji' => null,
                    ]);
                } else {
                    $company = $companies[$index % $companies->count()];
                    $isBelowUmp = $index % 5 === 0;
                    $graduationYear = (int) ($biodata->dataAkademik?->tahun_lulus ?? 0);
                    $ump = $umpByProvinceYear->get($company->kode_provinsi.'-'.$graduationYear)?->besaran
                        ?? $latestUmpByProvince->get($company->kode_provinsi)?->besaran;
                    $minimumSalary = $ump !== null ? (int) ceil((float) $ump * 1.2) : 0;
                    $salary = $company->kode_provinsi === '34'
                        ? $minimumSalary + (($index % 4) * 250000)
                        : ($isBelowUmp ? 2000000 + (($index % 4) * 150000) : 7500000 + (($index % 7) * 750000));
                    $atasan = Atasan::firstOrCreate(
                        ['email' => 'analytics.demo.'.$biodata->nim.'@example.test'],
                        [
                            'perusahaan_id' => $company->id,
                            'nama' => 'Atasan Demo Analitik '.$biodata->nim,
                            'telepon' => '0812'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                        ],
                    );

                    $biodata->update([
                        'perusahaan_id' => $company->id,
                        'atasan_id' => $atasan->id,
                        'kategori_pekerjaan' => $status,
                        'posisi_jabatan' => $status === 'Wiraswasta' ? 'Pemilik Usaha' : 'Profesional '.$biodata->nim,
                        'gaji' => $salary,
                    ]);
                }

                $this->seedTracerAnalyticsAnswers($biodata, $index, $status, $questions, $univSectionId);
                $this->seedProdiAnalyticsAnswers($biodata, $index, $prodiQuestions->get($biodata->prodi_id, collect()), $prodiSections->get($biodata->prodi_id)?->id);
            }

            $demoBiodataIds = $demoBiodata->pluck('id')->all();
            $univQuestionIds = $questions->pluck('id')->all();

            KuesionerJawaban::whereIn('biodata_id', $demoBiodataIds)
                ->whereIn('pertanyaan_id', $univQuestionIds)
                ->delete();

            collect($this->pendingTracerRows)
                ->chunk(500)
                ->each(function (Collection $rows): void {
                    DB::table('kuesioner_jawaban')->insert($rows->all());
                });
        });

        $this->command?->info('Distribusi data demo analitik diperbarui tanpa mengubah alumni non-demo.');
    }

    /**
     * Mengambil master provinsi dan kabupaten yang diperlukan perusahaan demo.
     *
     * @return array{provinces: array<string, Propinsi>, cities: array<string, Kabupaten>}
     */
    private function locations(): array
    {
        return [
            'provinces' => Propinsi::query()->whereIn('kode_provinsi', ['31', '32', '33', '34', '35'])->get()->keyBy('kode_provinsi')->all(),
            'cities' => Kabupaten::query()->whereIn('kode_kabupaten', ['31.74', '32.73', '33.74', '34.71', '35.78'])->get()->keyBy('kode_kabupaten')->all(),
        ];
    }

    /**
     * Menentukan distribusi status F8 agar IKU 1 tidak berisi satu kategori saja.
     */
    private function employmentStatus(int $index): string
    {
        return match ($index % 12) {
            0 => 'Tidak Kerja tetapi sedang mencari kerja',
            1 => 'Melanjutkan Pendidikan',
            2 => 'Belum memungkinkan bekerja',
            3 => 'Wiraswasta',
            default => 'Bekerja (full time/part time)',
        };
    }

    /**
     * Mengisi jawaban analitik demo sesuai percabangan instrumen universitas.
     *
     * @param  Collection<string, KuesionerPertanyaan>  $questions
     */
    private function seedTracerAnalyticsAnswers(Biodata $biodata, int $index, string $status, Collection $questions, int $univSectionId): void
    {
        $hasCareer = in_array($status, ['Bekerja (full time/part time)', 'Wiraswasta'], true);
        $isFurtherStudy = $status === 'Melanjutkan Pendidikan';
        $isSearching = $status === 'Tidak Kerja tetapi sedang mencari kerja';
        $workplaceTypes = ['Perusahaan swasta', 'Instansi pemerintah', 'BUMN/BUMD', 'Organisasi non-profit/LSM', 'Wiraswasta/perusahaan sendiri'];
        $alignments = ['Sangat erat', 'Erat', 'Cukup erat', 'Kurang erat', 'Tidak erat'];
        $educationLevels = $this->optionLabels($questions, 'F15');
        $fundingOptions = $this->optionLabels($questions, 'F12');
        $furtherStudyDegrees = ['S1', 'S2', 'S3', 'Profesi', 'Spesialis'];
        $learningScale = ['Tidak Sama Sekali', 'Kurang', 'Cukup Besar', 'Besar', 'Sangat Besar'];
        $competencyScale = ['Sangat Rendah', 'Rendah', 'Cukup', 'Tinggi', 'Sangat Tinggi'];

        $this->saveAnswer($biodata, $questions, 'F8', $status, $univSectionId);
        $this->saveAnswer($biodata, $questions, 'F505', $hasCareer ? (string) $biodata->gaji : null, $univSectionId);

        if ($hasCareer) {
            $startsBeforeGraduation = $index % 4 !== 0;
            $waitMonths = $startsBeforeGraduation ? $index % 7 : 7 + ($index % 12);
            $jobSearchStart = $index % 6 === 0
                ? 'Sesudah lulus '.(($index % 8) + 1).' bulan'
                : 'Sebelum lulus '.(($index % 6) + 1).' bulan';
            $methods = [
                'Mencari lewat internet/iklan online/milis',
                $index % 2 === 0 ? 'Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll)' : 'Melalui penempatan kerja atau magang',
            ];
            $applications = 4 + ($index % 18);
            $responses = max(1, (int) floor($applications * (0.35 + (($index % 3) * 0.1))));
            $interviews = max(1, min($responses, (int) floor($responses * 0.6)));
            $alignment = $alignments[$index % count($alignments)];

            $this->saveAnswer($biodata, $questions, 'F3', $jobSearchStart, $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F4', $methods, $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F6', (string) $applications, $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F7', (string) $responses, $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F7A', (string) $interviews, $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F11', $status === 'Wiraswasta' ? 'Wiraswasta/perusahaan sendiri' : $workplaceTypes[$index % 4], $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F14', $alignment, $univSectionId);
            if ($educationLevels !== []) {
                $this->saveAnswer($biodata, $questions, 'F15', $educationLevels[$index % count($educationLevels)], $univSectionId);
            }
            $this->saveAnswer($biodata, $questions, 'F504', $startsBeforeGraduation ? 'Ya' : 'Tidak', $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F502', $startsBeforeGraduation ? (string) $waitMonths : null, $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F506', $startsBeforeGraduation ? null : (string) $waitMonths, $univSectionId);
            $mismatchOptions = $this->optionLabels($questions, 'F16');
            if ($mismatchOptions !== []) {
                $selectedMismatch = [$mismatchOptions[$index % count($mismatchOptions)]];
                if (count($mismatchOptions) > 1 && $index % 5 === 0) {
                    $selectedMismatch[] = $mismatchOptions[($index + 1) % count($mismatchOptions)];
                }

                $this->saveAnswer($biodata, $questions, 'F16', array_values(array_unique($selectedMismatch)), $univSectionId);
            }
        } else {
            foreach (['F3', 'F4', 'F6', 'F7', 'F7A', 'F11', 'F14', 'F15', 'F16', 'F502', 'F506'] as $code) {
                $this->saveAnswer($biodata, $questions, $code, null, $univSectionId);
            }

            $this->saveAnswer($biodata, $questions, 'F504', 'Tidak', $univSectionId);
            $this->saveAnswer(
                $biodata,
                $questions,
                'F10',
                $isSearching ? 'Tidak, tetapi saya sedang menunggu hasil lamaran kerja' : 'Tidak',
                $univSectionId
            );
        }

        if ($isFurtherStudy) {
            $degree = $furtherStudyDegrees[$index % count($furtherStudyDegrees)];
            $degreePrefix = match ($degree) {
                'S1' => 'Sarjana',
                'S2' => 'Magister',
                'S3' => 'Doktor',
                'Profesi' => 'Profesi',
                default => 'Spesialis',
            };

            // Sinkronkan profil pendidikan lanjut agar sumber grafik tidak selalu S2.
            $biodata->update([
                'pendidikan_tingkat' => $degree,
                'perguruan_tinggi' => ['Universitas Gadjah Mada', 'Universitas Indonesia', 'Institut Teknologi Bandung', 'Universitas Airlangga', 'Universitas Padjadjaran'][$index % 5],
                'pendidikan_prodi' => $degreePrefix.' '.$biodata->prodi?->nama_prodi,
            ]);
        } else {
            // Bersihkan profil studi lama pada alumni yang status terbarunya bukan studi lanjut.
            $biodata->update([
                'pendidikan_tingkat' => null,
                'perguruan_tinggi' => null,
                'pendidikan_prodi' => null,
            ]);
        }

        $funding = $isFurtherStudy && $fundingOptions !== []
            ? $fundingOptions[$index % count($fundingOptions)]
            : null;
        $this->saveAnswer($biodata, $questions, 'F12', $funding, $univSectionId);
        $this->saveAnswer($biodata, $questions, 'F18b', $isFurtherStudy ? $biodata->perguruan_tinggi : null, $univSectionId);
        $this->saveAnswer($biodata, $questions, 'F18c', $isFurtherStudy ? $biodata->pendidikan_prodi : null, $univSectionId);
        $this->saveAnswer($biodata, $questions, 'F18d', $isFurtherStudy ? '2026-08-01' : null, $univSectionId);

        foreach (range(21, 27) as $offset => $number) {
            $score = $this->learningMethodScore($biodata, $offset, $index);
            $this->saveAnswer($biodata, $questions, 'F'.$number, $learningScale[$score - 1], $univSectionId);
        }

        foreach (range(1, 7) as $competency) {
            // Profil dasar dibuat berbeda per dimensi agar gap rata-rata tidak seragam.
            $graduationProfile = [3, 3, 4, 4, 2, 4, 3];
            $needProfile = [3, 4, 3, 5, 4, 3, 5];
            $graduationScore = min(5, max(1, $graduationProfile[$competency - 1] + (($index + $competency) % 3) - 1));
            $needScore = min(5, max(1, $needProfile[$competency - 1] + (($index * 2 + $competency) % 3) - 1));
            $this->saveAnswer($biodata, $questions, 'F17a'.$competency, $competencyScale[$graduationScore - 1], $univSectionId);
            $this->saveAnswer($biodata, $questions, 'F17b'.$competency, $competencyScale[$needScore - 1], $univSectionId);
        }
    }

    /**
     * Menghasilkan skor demo metode belajar yang khas untuk tiap program studi.
     *
     * @param  int  $methodOffset  Posisi F21 sampai F27 pada profil prodi.
     * @param  int  $respondentIndex  Urutan alumni demo untuk variasi terbatas.
     */
    private function learningMethodScore(Biodata $biodata, int $methodOffset, int $respondentIndex): int
    {
        $profile = self::LEARNING_METHOD_PROFILES[$biodata->prodi?->kode_prodi ?? ''] ?? [3, 3, 3, 3, 3, 3, 3];
        $variation = match ($respondentIndex % 11) {
            0 => 1,
            7 => -1,
            default => 0,
        };

        return min(5, max(1, $profile[$methodOffset] + $variation));
    }

    /**
     * Menyusun respons kuesioner prodi demo yang bervariasi untuk setiap butir.
     *
     * @param  Collection<int, KuesionerPertanyaan>  $questions
     */
    private function seedProdiAnalyticsAnswers(Biodata $biodata, int $respondentIndex, Collection $questions, ?int $prodiSectionId = null): void
    {
        $otherDetails = ['Menjadi mentor proyek mahasiswa', 'Berbagi pengalaman karier', 'Membuka jejaring mitra industri', 'Mendampingi pengembangan kurikulum'];

        foreach ($questions as $question) {
            $options = $question->opsi->values();
            $answerText = null;
            $answerJson = null;
            $primaryOption = null;

            if (in_array($question->tipe, ['rating_5', 'likert_5'], true)) {
                $score = min(5, max(1, 3 + (($respondentIndex + 2) % 3) - 1));
                $answerText = (string) $score;
            } elseif (in_array($question->tipe, ['text', 'textarea'], true)) {
                $answerText = 'Respons demo '.(($respondentIndex % 4) + 1).' untuk '.$question->teks_pertanyaan;
            } elseif ($options->isNotEmpty()) {
                // Putar satu per satu agar semua opsi aktif terisi, termasuk Lainnya.
                $primaryOption = $options->get(($respondentIndex + 1) % $options->count());
                $selectedOptions = collect([$primaryOption])->filter();
                $otherOption = $options->first(fn ($option): bool => $this->isOtherOption((string) $option->label));
                $customDetail = null;

                $isMulti = in_array($question->tipe, ['multiple_choice', 'checkbox'], true);

                if ($otherOption && ($respondentIndex + 1) % 7 === 0) {
                    $selectedOptions = $isMulti
                        ? $selectedOptions->push($otherOption)->unique('id')->values()
                        : collect([$otherOption]);
                    $customDetail = $otherDetails[($respondentIndex + 1) % count($otherDetails)];
                } elseif ($isMulti && $options->count() > 1 && ($respondentIndex + 1) % 3 === 0) {
                    $selectedOptions->push($options->get(($respondentIndex + 2) % $options->count()));
                    $selectedOptions = $selectedOptions->filter()->unique('id')->values();
                }

                $selectedLabels = $selectedOptions
                    ->map(fn ($option): string => $this->isOtherOption((string) $option->label) ? 'Lainnya' : (string) $option->label)
                    ->all();
                $answerText = implode(', ', $selectedLabels);
                $answerJson = $isMulti
                    ? ['selected' => $selectedLabels, 'custom' => $customDetail]
                    : ['selected' => $selectedLabels[0] ?? '', 'custom' => $customDetail];

                if ($customDetail !== null && ! $isMulti) {
                    $answerText .= ' ('.$customDetail.')';
                }
            }

            if ($answerText !== null) {
                $sectionId = $prodiSectionId ?? ($question->sections->first()?->id ?? 1);

                KuesionerJawaban::updateOrCreate(
                    [
                        'responden_type' => 'alumni',
                        'biodata_id' => $biodata->id,
                        'pertanyaan_id' => $question->id,
                    ],
                    [
                        'evaluasi_atasan_id' => null,
                        'section_id' => $sectionId,
                        'kode_pertanyaan' => $question->kode_pertanyaan,
                        'opsi_id' => $primaryOption?->id,
                        'kode_opsi' => $primaryOption?->kode_opsi,
                        'jawaban_teks' => $answerText,
                        'jawaban_json' => $answerJson,
                    ]
                );
            }
        }
    }

    /**
     * Menentukan opsi isian bebas lama maupun baru agar fixture memakai satu format Lainnya.
     */
    private function isOtherOption(string $label): bool
    {
        $normalized = mb_strtolower(trim($label));

        return $normalized === 'other'
            || str_contains($normalized, 'lainnya')
            || str_contains($normalized, 'tuliskan');
    }

    /**
     * Mengambil label opsi aktif dari metadata pertanyaan, bukan menyalin label ke seeder.
     *
     * @param  Collection<string, KuesionerPertanyaan>  $questions
     * @return array<int, string>
     */
    private function optionLabels(Collection $questions, string $code): array
    {
        $question = $questions->get(strtoupper($code));
        if (! $question) {
            return [];
        }

        return $question->opsi
            ->sortBy('order')
            ->pluck('label')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->filter(static fn (string $label): bool => $label !== '' && mb_strtolower($label) !== 'lainnya isian')
            ->values()
            ->all();
    }

    /**
     * Menghapus opsi legacy dan menormalkan label jawaban agar metadata dan data respons konsisten.
     */
    private function normalizeMismatchOptionData(): void
    {
        DB::table('kuesioner_opsi')
            ->where('label', 'Lainnya isian')
            ->delete();

        DB::table('kuesioner_jawaban')
            ->where('kode_pertanyaan', 'F16')
            ->where('jawaban_teks', 'like', '%Lainnya isian%')
            ->update([
                'jawaban_teks' => DB::raw("REPLACE(jawaban_teks, 'Lainnya isian', 'Lainnya')"),
                'jawaban_json' => DB::raw("REPLACE(jawaban_json, 'Lainnya isian', 'Lainnya')"),
                'updated_at' => now(),
            ]);

        DB::table('kuesioner_jawaban')
            ->where('kode_pertanyaan', 'F15')
            ->where('jawaban_teks', 'like', '%Tingkat yang Sama%')
            ->update([
                'jawaban_teks' => DB::raw("REPLACE(jawaban_teks, 'Tingkat yang Sama', 'Tingkat yang sama')"),
                'jawaban_json' => DB::raw("REPLACE(jawaban_json, 'Tingkat yang Sama', 'Tingkat yang sama')"),
                'updated_at' => now(),
            ]);
    }

    /**
     * Menyimpan satu jawaban demo dengan kode dan question_id yang konsisten.
     *
     * @param  Collection<string, KuesionerPertanyaan>  $questions
     * @param  string|array<int, string>|null  $answer
     */
    private function saveAnswer(Biodata $biodata, Collection $questions, string $code, string|array|null $answer, int $univSectionId): void
    {
        $question = $questions->get(strtoupper($code));
        if (! $question) {
            return;
        }

        $answerText = is_array($answer) ? implode('; ', $answer) : $answer;
        $answerJson = is_array($answer) ? ['values' => array_values($answer)] : ($answer !== null ? ['value' => $answer] : null);

        $matchedOpsi = null;
        $kodeOpsi = null;

        if ($question->opsi && $question->opsi->isNotEmpty()) {
            if (is_array($answer)) {
                $matchedList = $question->opsi->filter(function ($opt) use ($answer) {
                    return in_array($opt->label, $answer, true) || in_array($opt->kode_opsi, $answer, true);
                });
                if ($matchedList->isNotEmpty()) {
                    $kodeOpsi = json_encode($matchedList->pluck('kode_opsi')->values()->all());
                    $matchedOpsi = $matchedList->first();
                }
            } elseif ($answer !== null) {
                $matchedOpsi = $question->opsi->first(function ($opt) use ($answer) {
                    return $opt->label === $answer || $opt->kode_opsi === $answer;
                });
                $kodeOpsi = $matchedOpsi?->kode_opsi;
            }
        }

        $sectionId = $question->sections->first()?->id ?? $univSectionId;

        $this->pendingTracerRows[] = [
            'responden_type' => 'alumni',
            'biodata_id' => $biodata->id,
            'evaluasi_atasan_id' => null,
            'section_id' => $sectionId,
            'pertanyaan_id' => $question->id,
            'kode_pertanyaan' => $question->kode_pertanyaan,
            'opsi_id' => $matchedOpsi?->id,
            'kode_opsi' => $kodeOpsi,
            'jawaban_teks' => $answerText,
            'jawaban_json' => $answerJson === null ? null : json_encode($answerJson, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
