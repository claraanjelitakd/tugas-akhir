<?php

namespace App\Services\Analytics;

use App\Models\Kuesioner;
use App\Models\Prodi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyediakan dataset analitik Tracer Study untuk halaman analitik Superadmin.
 *
 * Service ini membentuk payload satu indikator dari database view terpilah.
 * Ia tidak menyimpan data baru; normalisasi sumber tetap berada di lapisan
 * view, sedangkan statistik dan metadata presentasi dibentuk di service.
 */
class TracerAnalyticsService
{
    /** Sentinel filter value that includes every available graduation year. */
    private const ALL_YEARS = 'ALL';

    /**
     * Kode pertanyaan yang dipakai untuk indikator analitik tahap pertama.
     *
     * @var array<int, string>
     */
    private const ANALYTICS_QUESTION_CODES = [
        'F3', 'F4', 'F6', 'F7', 'F7A', 'F8', 'F10', 'F11', 'F12',
        'F14', 'F15', 'F16', 'F502', 'F504', 'F506', 'F21', 'F22',
        'F23', 'F24', 'F25', 'F26', 'F27', 'F18b', 'F18c', 'F18d',
        'F17a1', 'F17b1', 'F17a2', 'F17b2', 'F17a3', 'F17b3', 'F17a4',
        'F17b4', 'F17a5', 'F17b5', 'F17a6', 'F17b6', 'F17a7', 'F17b7',
    ];

    /**
     * Daftar grafik universitas yang tersedia pada halaman analitik terpilah.
     *
     * @var array<int, array{key: string, label: string, description: string}>
     */
    private const UNIVERSITY_CHARTS = [
        [
            'key' => 'wait_time',
            'label' => 'Waktu memperoleh pekerjaan',
            'description' => 'Median waktu dan daftar alumni yang lebih cepat, sama, atau lebih lama dari median.',
        ],
        [
            'key' => 'salary_vs_ump',
            'label' => 'Pendapatan per bulan dibandingkan 1,2× UMP',
            'description' => 'Perbandingan gaji alumni dengan ambang kewajaran 1,2 kali UMP provinsi tempat kerja.',
        ],
        [
            'key' => 'work_location',
            'label' => 'Sebaran wilayah kerja',
            'description' => 'Sebaran provinsi perusahaan tempat alumni bekerja, bukan alamat domisili alumni.',
        ],
        [
            'key' => 'employment_status',
            'label' => 'Status pekerjaan dan IKU 1',
            'description' => 'Distribusi status alumni dari pertanyaan F8 dan indikator awal IKU 1.',
        ],
        [
            'key' => 'field_alignment',
            'label' => 'Hubungan bidang studi dengan pekerjaan',
            'description' => 'Distribusi tingkat hubungan bidang studi dengan pekerjaan dari pertanyaan F14.',
        ],
        [
            'key' => 'education_alignment',
            'label' => 'Keselarasan tingkat pendidikan per prodi',
            'description' => 'Perbandingan tingkat pendidikan yang dianggap sesuai untuk pekerjaan dari pertanyaan F15 antarprogram studi.',
        ],
        [
            'key' => 'education_alignment_university',
            'label' => 'Keselarasan tingkat pendidikan universitas',
            'description' => 'Komposisi tingkat pendidikan yang dianggap sesuai untuk pekerjaan dari pertanyaan F15 pada tingkat universitas.',
        ],
        [
            'key' => 'response_rate',
            'label' => 'Response rate universitas',
            'description' => 'Perbandingan alumni terdata dan alumni yang mengisi kuesioner universitas.',
        ],
        [
            'key' => 'profile_completion',
            'label' => 'Kelengkapan profil alumni',
            'description' => 'Kelengkapan profil berdasarkan status audit profil pada tahun lulusan terpilih.',
        ],
        [
            'key' => 'learning_methods',
            'label' => 'Penekanan metode pembelajaran',
            'description' => 'Median penilaian alumni atas metode pembelajaran program studi.',
            'visual_plan' => 'Radar skor metode, batang Likert, dan heatmap per program studi.',
        ],
        [
            'key' => 'job_search_start',
            'label' => 'Waktu mulai mencari pekerjaan',
            'description' => 'Distribusi waktu alumni mulai mencari pekerjaan setelah memperhatikan kategori sebelum atau sesudah lulus.',
            'visual_plan' => 'Batang divergen, distribusi kategori, dan tabel individu.',
        ],
        [
            'key' => 'job_search_methods',
            'label' => 'Cara mencari pekerjaan',
            'description' => 'Saluran yang digunakan alumni untuk mendapatkan pekerjaan.',
            'visual_plan' => 'Batang horizontal, treemap proporsi, dan heatmap program studi.',
        ],
        [
            'key' => 'application_pipeline',
            'label' => 'Lamaran, respons, dan wawancara',
            'description' => 'Median tahapan proses lamaran pekerjaan sebelum memperoleh pekerjaan pertama.',
            'visual_plan' => 'Batang berkelompok, funnel proses, dan tabel individu.',
        ],
        [
            'key' => 'job_search_activity',
            'label' => 'Aktivitas mencari pekerjaan',
            'description' => 'Status aktivitas pencarian pekerjaan dalam empat minggu terakhir.',
            'visual_plan' => 'Donut proporsi, batang bertumpuk, dan heatmap program studi.',
        ],
        [
            'key' => 'workplace_type',
            'label' => 'Jenis tempat kerja',
            'description' => 'Distribusi jenis perusahaan, instansi, atau institusi tempat alumni bekerja.',
            'visual_plan' => 'Treemap kategori, donut proporsi, dan perbandingan program studi.',
        ],
        [
            'key' => 'further_study',
            'label' => 'Studi lanjut dan sumber biaya',
            'description' => 'Sumber pembiayaan studi lanjut yang dilaporkan alumni.',
            'visual_plan' => 'Sankey sumber biaya, batang proporsi, dan tabel studi lanjut.',
        ],
        [
            'key' => 'mismatch_reasons',
            'label' => 'Alasan ketidaksesuaian pekerjaan',
            'description' => 'Alasan alumni mengambil pekerjaan yang tidak sesuai dengan pendidikan.',
            'visual_plan' => 'Batang horizontal, treemap proporsi, dan heatmap program studi.',
        ],
        [
            'key' => 'competency_gap',
            'label' => 'Gap kompetensi lulusan',
            'description' => 'Perbandingan kompetensi yang dikuasai saat lulus dan yang diperlukan di pekerjaan.',
            'visual_plan' => 'Dumbbell median, radar kompetensi, dan heatmap gap.',
        ],
    ];

    private const SALARY_UMP_MULTIPLIER = 1.2;

    private const WAIT_TIME_MEDIAN_EPSILON = 0.01;

    /**
     * View terpilah per indikator kuesioner universitas.
     *
     * @var array<string, string>
     */
    private const QUESTION_CHART_VIEWS = [
        'learning_methods' => 'v_analytics_learning_methods',
        'job_search_start' => 'v_analytics_job_search_start',
        'job_search_methods' => 'v_analytics_job_search_methods',
        'application_pipeline' => 'v_analytics_application_pipeline',
        'job_search_activity' => 'v_analytics_job_search_activity',
        'workplace_type' => 'v_analytics_workplace_type',
        'further_study' => 'v_analytics_further_study',
        'mismatch_reasons' => 'v_analytics_mismatch_reasons',
        'competency_gap' => 'v_analytics_competency_gap',
    ];

    /**
     * Membangun seluruh dataset analitik berdasarkan tahun lulusan dan prodi.
     *
     * @return array<string, mixed>
     */
    public function build(?string $requestedYear = null, ?int $requestedUmpYear = null, ?int $prodiId = null): array
    {
        $availableYears = $this->availableYears();
        $selectedYear = $this->resolveYear($requestedYear, $availableYears);
        $availableUmpYears = $this->availableUmpYears();
        $selectedUmpYear = $this->resolveUmpYear($requestedUmpYear, $selectedYear, $availableUmpYears);

        $rows = $this->alumniRows($selectedYear, $prodiId);
        $responses = $this->responsesByAlumni($rows->pluck('biodata_id')->map(fn (mixed $id): int => (int) $id)->all());
        $umpRows = $selectedUmpYear === null
            ? collect()
            : DB::table('ump')->where('tahun', $selectedUmpYear)->get(['kode_provinsi', 'besaran']);
        $salary = $this->salaryVsUmp($rows, $umpRows);

        return [
            'filters' => [
                'available_years' => $availableYears,
                'selected_year' => $selectedYear,
                'available_ump_years' => $availableUmpYears,
                'selected_ump_year' => $selectedUmpYear,
                'selected_prodi_id' => $prodiId,
                'prodis' => Prodi::query()
                    ->orderBy('kode_prodi')
                    ->get(['id', 'kode_prodi', 'nama_prodi'])
                    ->map(fn (Prodi $prodi): array => [
                        'id' => $prodi->id,
                        'kode' => $prodi->kode_prodi,
                        'nama' => $prodi->nama_prodi,
                    ])
                    ->values()
                    ->all(),
            ],
            'summary' => $this->summary($rows, $responses, $salary),
            'response_by_prodi' => $this->responseByProdi($rows, $this->resolveTargetResponseRate($selectedYear)),
            'wait_time' => $this->waitTime($rows, $responses),
            'salary_vs_ump' => $salary,
            'work_location' => $this->workLocation($rows),
            'employment_status' => $this->employmentStatus($rows, $responses),
            'definitions' => $this->definitions(),
        ];
    }

    /**
     * Mengambil satu grafik universitas beserta data detailnya.
     *
     * Pemisahan ini mencegah halaman memuat seluruh indikator, pertanyaan, dan
     * daftar detail ketika administrator hanya sedang membuka satu grafik.
     *
     * @return array<string, mixed>
     */
    public function buildUniversityChart(
        ?string $requestedChart,
        ?string $requestedYear = null,
        ?int $requestedUmpYear = null,
        ?int $prodiId = null,
    ): array {
        $chartKey = $this->resolveChartKey($requestedChart);
        $availableYears = $this->availableYears();
        $selectedYear = $this->resolveYear($requestedYear, $availableYears);
        $availableUmpYears = $this->availableUmpYears();
        $selectedUmpYear = $this->resolveUmpYear($requestedUmpYear, $selectedYear, $availableUmpYears);
        $rows = $this->analyticsRows($chartKey, $selectedYear, $selectedUmpYear, $prodiId);
        $responses = $chartKey === 'employment_status'
            ? $this->responsesByAlumni($rows->pluck('biodata_id')->all(), ['F8', 'F18a', 'F18b', 'F18c', 'F18d', 'F11', 'F5B', 'F5C', 'F2E', 'F2G'])
            : [];
        $umpRows = collect();

        $chart = match ($chartKey) {
            'wait_time' => $this->waitTimeChart($rows, $responses),
            'salary_vs_ump' => $this->salaryChart($rows, $umpRows),
            'work_location' => $this->workLocationChart($rows),
            'employment_status' => $this->employmentStatusChart($rows, $responses),
            'field_alignment' => $this->alignmentChart($rows, $responses, 'F14', 'Hubungan bidang studi dengan pekerjaan'),
            'education_alignment', 'education_alignment_university' => $this->alignmentChart(
                $rows,
                $responses,
                'F15',
                $chartKey === 'education_alignment'
                    ? 'Keselarasan tingkat pendidikan per program studi'
                    : 'Keselarasan tingkat pendidikan tingkat universitas',
                $chartKey,
            ),
            'response_rate' => $this->responseRateChart($rows, $this->resolveTargetResponseRate($selectedYear)),
            'profile_completion' => $this->profileCompletionChart($rows),
            'learning_methods', 'job_search_start', 'job_search_methods', 'application_pipeline',
            'job_search_activity', 'workplace_type', 'further_study', 'mismatch_reasons', 'competency_gap' => $this->questionChart($rows, $chartKey),
            default => throw new \LogicException('Grafik analitik tidak tersedia.'),
        };
        $populationCount = $this->populationCount($selectedYear, $prodiId);
        $chart['data_availability'] = $this->dataAvailability($chartKey, $chart, $populationCount);
        $validRespondentCount = collect($chart['records'] ?? [])
            ->pluck('biodata_id')
            ->filter()
            ->unique()
            ->count();
        $chart['metadata'] = [
            'generated_at' => now()->toIso8601String(),
            'population_count' => $populationCount,
            'valid_respondent_count' => $validRespondentCount,
            'missing_count' => max($populationCount - $validRespondentCount, 0),
            'data_type' => $this->analyticsDataType($chartKey),
            'percentage_basis' => in_array($chartKey, ['job_search_methods', 'mismatch_reasons'], true)
                ? 'Persentase responden dapat berjumlah lebih dari 100% karena pertanyaan menerima lebih dari satu pilihan.'
                : 'Setiap alumni dihitung satu kali pada kategori yang berlaku, kecuali jika metodologi menyatakan lain.',
        ];

        return [
            'filters' => [
                'available_years' => $availableYears,
                'selected_year' => $selectedYear,
                'available_ump_years' => $availableUmpYears,
                'selected_ump_year' => $selectedUmpYear,
                'selected_prodi_id' => $prodiId,
                'prodis' => Prodi::query()
                    ->orderBy('kode_prodi')
                    ->get(['id', 'kode_prodi', 'nama_prodi'])
                    ->map(fn (Prodi $prodi): array => [
                        'id' => $prodi->id,
                        'kode' => $prodi->kode_prodi,
                        'nama' => $prodi->nama_prodi,
                    ])
                    ->values()
                    ->all(),
            ],
            'chart_options' => self::UNIVERSITY_CHARTS,
            'selected_chart' => $chartKey,
            'chart' => $chart,
        ];
    }

    /**
     * Menyediakan metadata grafik untuk navigasi dan dokumentasi UI.
     *
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function universityChartOptions(): array
    {
        return self::UNIVERSITY_CHARTS;
    }

    /**
     * Menjelaskan apakah indikator benar-benar kosong, tidak berlaku karena
     * percabangan, atau tersedia untuk dianalisis.
     *
     * @param  array<string, mixed>  $chart
     * @return array{status: string, population_count: int, record_count: int, message: string}
     */
    private function dataAvailability(string $chartKey, array $chart, int $populationCount): array
    {
        $recordCount = count($chart['records'] ?? []);
        if ($recordCount > 0) {
            return [
                'status' => 'available',
                'population_count' => $populationCount,
                'record_count' => $recordCount,
                'message' => 'Data valid tersedia dan masuk ke perhitungan indikator.',
            ];
        }

        if ($populationCount === 0) {
            return [
                'status' => 'no_population',
                'population_count' => 0,
                'record_count' => 0,
                'message' => 'Belum ada alumni pada tahun lulusan dan program studi yang dipilih.',
            ];
        }

        $branchDependent = in_array($chartKey, [
            'wait_time', 'job_search_start', 'job_search_methods', 'application_pipeline',
            'job_search_activity', 'workplace_type', 'further_study', 'mismatch_reasons',
        ], true);

        return [
            'status' => $branchDependent ? 'not_applicable_or_unanswered' : 'no_valid_answers',
            'population_count' => $populationCount,
            'record_count' => 0,
            'message' => $branchDependent
                ? 'Populasi tersedia, tetapi belum ada jawaban valid yang berlaku setelah percabangan pertanyaan.'
                : 'Populasi tersedia, tetapi belum ada jawaban valid untuk indikator ini.',
        ];
    }

    /**
     * Menjelaskan bentuk data indikator untuk metadata audit pada antarmuka.
     */
    private function analyticsDataType(string $chartKey): string
    {
        return match ($chartKey) {
            'wait_time', 'salary_vs_ump', 'application_pipeline' => 'Numerik kontinu atau diskrit',
            'learning_methods', 'competency_gap' => 'Skala ordinal Likert',
            'job_search_methods', 'mismatch_reasons' => 'Kategorikal pilihan jamak',
            'work_location' => 'Kategorikal geografis',
            default => 'Kategorikal pilihan tunggal',
        };
    }

    /**
     * Mengambil tahun lulusan yang tersedia pada populasi analitik.
     *
     * @return array<int, string>
     */
    private function availableYears(): array
    {
        $years = DB::table('data_akademik')
            ->whereNotNull('tahun_lulus')
            ->pluck('tahun_lulus')
            ->filter()
            ->map(fn (mixed $year): string => (string) $year)
            ->unique()
            ->push((string) now()->year)
            ->sortDesc()
            ->values()
            ->all();

        return [self::ALL_YEARS, ...$years];
    }

    /**
     * Menghitung denominator kohort langsung dari sumber akademik dan biodata.
     *
     * Query ini sengaja tidak memakai view indikator agar metadata populasi
     * tidak memicu evaluasi ulang jawaban tracer pada setiap permintaan.
     */
    private function populationCount(string $selectedYear, ?int $prodiId): int
    {
        return DB::table('biodata')
            ->join('data_akademik', 'data_akademik.nim', '=', 'biodata.nim')
            ->when($selectedYear !== self::ALL_YEARS, fn ($query) => $query->where('data_akademik.tahun_lulus', $selectedYear))
            ->when($prodiId, fn ($query) => $query->where('biodata.prodi_id', $prodiId))
            ->count();
    }

    /**
     * Mengambil tahun UMP yang tersedia di master wilayah.
     *
     * @return array<int, int>
     */
    private function availableUmpYears(): array
    {
        return DB::table('ump')
            ->whereNotNull('tahun')
            ->orderByDesc('tahun')
            ->distinct()
            ->pluck('tahun')
            ->map(fn (mixed $year): int => (int) $year)
            ->values()
            ->all();
    }

    /**
     * Menentukan tahun lulusan aktif dengan default tahun berjalan.
     *
     * @param  array<int, string>  $availableYears
     */
    private function resolveYear(?string $requestedYear, array $availableYears): string
    {
        $defaultYear = (string) now()->year;
        $year = $requestedYear ?: $defaultYear;

        return in_array($year, $availableYears, true) ? $year : $defaultYear;
    }

    /**
     * Menentukan tahun UMP aktif dan menghindari perbandingan dengan tahun yang tidak tersedia.
     *
     * @param  array<int, int>  $availableUmpYears
     */
    private function resolveUmpYear(?int $requestedUmpYear, string $selectedYear, array $availableUmpYears): ?int
    {
        if ($availableUmpYears === []) {
            return null;
        }

        if ($requestedUmpYear !== null && in_array($requestedUmpYear, $availableUmpYears, true)) {
            return $requestedUmpYear;
        }

        return in_array((int) $selectedYear, $availableUmpYears, true)
            ? (int) $selectedYear
            : $availableUmpYears[0];
    }

    /**
     * Menjamin hanya kunci grafik yang dikenal yang dapat diminta melalui URL.
     */
    private function resolveChartKey(?string $requestedChart): string
    {
        $requestedChart = trim((string) $requestedChart);
        $availableKeys = array_column(self::UNIVERSITY_CHARTS, 'key');

        return in_array($requestedChart, $availableKeys, true)
            ? $requestedChart
            : 'response_rate';
    }

    /**
     * Mengambil hanya view analitik yang dibutuhkan indikator aktif.
     *
     * View ini menjaga proyeksi dan normalisasi data tetap berada di database,
     * sementara service hanya bertugas membentuk metadata, median, dan payload UI.
     *
     * @return Collection<int, object>
     */
    private function analyticsRows(string $chartKey, string $selectedYear, ?int $selectedUmpYear, ?int $prodiId): Collection
    {
        if (isset(self::QUESTION_CHART_VIEWS[$chartKey])) {
            return DB::table(self::QUESTION_CHART_VIEWS[$chartKey])
                ->when($selectedYear !== self::ALL_YEARS, fn ($query) => $query->where('tahun_lulus', $selectedYear))
                ->when($prodiId, fn ($query) => $query->where('prodi_id', $prodiId))
                ->get();
        }

        $view = match ($chartKey) {
            'wait_time' => 'v_analytics_wait_time',
            'salary_vs_ump' => 'v_analytics_salary_ump',
            'work_location' => 'v_analytics_work_location',
            'employment_status' => 'v_analytics_employment_status',
            'field_alignment', 'education_alignment', 'education_alignment_university' => 'v_analytics_alignment',
            'response_rate' => 'v_analytics_response_rate',
            'profile_completion' => 'v_analytics_profile_completion',
            default => 'v_analytics_population',
        };

        return DB::table($view)
            ->when($selectedYear !== self::ALL_YEARS, fn ($query) => $query->where('tahun_lulus', $selectedYear))
            ->when($prodiId, fn ($query) => $query->where('prodi_id', $prodiId))
            ->when($chartKey === 'salary_vs_ump', fn ($query) => $query->where(function ($umpQuery) use ($selectedUmpYear): void {
                $umpQuery->where('ump_year', $selectedUmpYear)->orWhereNull('ump_year');
            }))
            ->when(in_array($chartKey, ['field_alignment', 'education_alignment', 'education_alignment_university'], true), fn ($query) => $query->where('indicator_code', $chartKey === 'field_alignment' ? 'F14' : 'F15'))
            ->get();
    }

    /**
     * Menyusun indikator yang sumbernya berasal dari jawaban tracer generik.
     *
     * Satu payload membawa tiga lapisan informasi: seri distribusi utama,
     * rencana kombinasi visualisasi, serta record individu untuk tab Data.
     * Perhitungan tetap dilakukan dari view jawaban terbaru agar pengisian
     * ulang tidak membuat jawaban lama ikut terhitung.
     *
     * @return array<string, mixed>
     */
    private function questionChart(Collection $rows, string $chartKey): array
    {
        if ($chartKey === 'competency_gap') {
            return $this->competencyGapChart($rows);
        }

        if ($chartKey === 'application_pipeline') {
            return $this->applicationPipelineChart($rows);
        }

        if ($chartKey === 'learning_methods') {
            return $this->learningMethodsChart($rows);
        }

        if ($chartKey === 'further_study') {
            return $this->furtherStudyChart($rows);
        }

        $metadata = [
            'job_search_start' => [
                'title' => 'Waktu mulai mencari pekerjaan',
                'subtitle' => 'Distribusi waktu alumni mulai mencari pekerjaan berdasarkan F3.',
                'type' => 'categorical',
                'formula' => 'Setiap kategori jawaban valid dihitung satu kali.',
            ],
            'job_search_methods' => [
                'title' => 'Cara mencari pekerjaan',
                'subtitle' => 'Saluran pencarian pekerjaan dari jawaban pilihan jamak F4.',
                'type' => 'categorical',
                'formula' => 'Jawaban pilihan jamak dihitung satu kali pada setiap kategori yang dipilih.',
            ],
            'job_search_activity' => [
                'title' => 'Aktivitas mencari pekerjaan',
                'subtitle' => 'Aktivitas pencarian pekerjaan alumni dalam empat minggu terakhir.',
                'type' => 'categorical',
                'formula' => 'Setiap jawaban valid F10 dihitung satu kali.',
            ],
            'workplace_type' => [
                'title' => 'Jenis tempat kerja',
                'subtitle' => 'Jenis perusahaan, instansi, atau institusi dari F11.',
                'type' => 'categorical',
                'formula' => 'Setiap kategori jawaban valid F11 dihitung satu kali.',
            ],
            'mismatch_reasons' => [
                'title' => 'Alasan ketidaksesuaian pekerjaan',
                'subtitle' => 'Alasan pekerjaan tidak sesuai dengan pendidikan dari F16.',
                'type' => 'categorical',
                'formula' => 'Jawaban pilihan jamak dihitung satu kali pada setiap alasan yang dipilih.',
            ],
        ][$chartKey];
        $records = $this->questionRecords($rows);
        $questionCode = match ($chartKey) {
            'job_search_methods' => 'F4',
            'mismatch_reasons' => 'F16',
            'workplace_type' => 'F11',
            'job_search_activity' => 'F10',
            default => null,
        };

        $grouped = $records->groupBy('answer');
        $officialOptions = $questionCode
            ? DB::table('ref_subpertanyaan_detil')
                ->where('kode_pertanyaan', $questionCode)
                ->orderBy('order')
                ->pluck('option_text')
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];

        $seriesCollection = collect();
        if (! empty($officialOptions)) {
            // Masukkan seluruh opsi resmi kuesioner
            foreach ($officialOptions as $optText) {
                $group = $grouped->get($optText);
                $cnt = $group ? $group->count() : 0;
                $seriesCollection->push([
                    'label' => $optText,
                    'count' => $cnt,
                    'percentage' => $this->percentage($cnt, max($records->count(), 1)),
                ]);
            }
            // Tambahkan jawaban alumni di luar opsi resmi jika ada (misal teks bebas lainnya)
            foreach ($grouped as $label => $group) {
                if (! in_array($label, $officialOptions, true)) {
                    $cnt = $group->count();
                    $seriesCollection->push([
                        'label' => $label,
                        'count' => $cnt,
                        'percentage' => $this->percentage($cnt, max($records->count(), 1)),
                    ]);
                }
            }
            $series = $seriesCollection->sortByDesc('count')->values()->all();
        } else {
            $series = $grouped->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'count' => $group->count(),
                'percentage' => $this->percentage($group->count(), max($records->count(), 1)),
            ])->sortByDesc('count')->values()->all();
        }

        return [
            'key' => $chartKey,
            'type' => $metadata['type'],
            'title' => $metadata['title'],
            'subtitle' => $metadata['subtitle'],
            'visual_plan' => $this->visualPlan($chartKey),
            'summary' => [
                'answered_count' => $records->pluck('biodata_id')->unique()->count(),
                'response_count' => $records->count(),
                'leading_category' => $series[0]['label'] ?? null,
                'leading_percentage' => $series[0]['percentage'] ?? 0,
            ],
            'series' => $series,
            'records' => $records->values()->all(),
            'methodology' => [
                'formula' => $metadata['formula'],
                'denominator' => 'Alumni dengan jawaban valid pada indikator aktif.',
                'source' => 'v_analytics_tracer_answers (tracer terbaru per alumni dan kode pertanyaan)',
            ],
        ];
    }

    /**
     * Mengubah baris jawaban view menjadi record individu yang bisa ditelusuri.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function questionRecords(Collection $rows): Collection
    {
        return $rows->flatMap(function (object $row): array {
            $values = $this->answerValues($row->answer ?? null, $this->decodeJson($row->answer_json ?? null));

            return array_map(fn (string $value): array => [
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'kode_pertanyaan' => $row->kode_pertanyaan,
                'answer' => $value,
            ], $values);
        })->filter(fn (array $record): bool => $record['answer'] !== '')->values();
    }

    /**
     * Menyusun tahapan lamaran dengan median jumlah lamaran, respons, dan wawancara.
     *
     * @return array<string, mixed>
     */
    private function applicationPipelineChart(Collection $rows): array
    {
        $labels = ['F6' => 'Lamaran', 'F7' => 'Mendapat respons', 'F7A' => 'Mendapat undangan wawancara'];
        $records = $this->questionRecords($rows)->map(function (array $record): array {
            $record['value'] = $this->numericValue($record['answer']);

            return $record;
        })->filter(fn (array $record): bool => $record['value'] !== null && $record['value'] >= 0)->values();
        $series = collect($labels)->map(function (string $label, string $code) use ($records): array {
            $values = $records->where('kode_pertanyaan', $code)->pluck('value')->all();

            return [
                'key' => $code,
                'label' => $label,
                'count' => count($values),
                'median' => $this->median($values),
                'percentage' => $this->percentage(count($values), $records->where('kode_pertanyaan', 'F6')->count()),
            ];
        })->values()->all();

        return [
            'key' => 'application_pipeline',
            'type' => 'application_pipeline',
            'title' => 'Lamaran, respons, dan wawancara',
            'subtitle' => 'Tahapan proses lamaran pekerjaan sebelum memperoleh pekerjaan pertama.',
            'visual_plan' => $this->visualPlan('application_pipeline'),
            'summary' => [
                'answered_count' => $records->pluck('biodata_id')->unique()->count(),
                'median_applications' => $series[0]['median'] ?? null,
                'median_responses' => $series[1]['median'] ?? null,
                'median_interviews' => $series[2]['median'] ?? null,
            ],
            'series' => $series,
            'records' => $records->all(),
            'methodology' => [
                'formula' => 'Median dihitung terpisah untuk jumlah lamaran, respons, dan undangan wawancara.',
                'denominator' => 'Alumni dengan nilai numerik valid pada tahap terkait.',
                'source' => 'tracer.F6, tracer.F7, tracer.F7A melalui v_analytics_tracer_answers',
            ],
        ];
    }

    /**
     * Menyusun skor rata-rata (INT) metode pembelajaran F21 sampai F27.
     *
     * @return array<string, mixed>
     */
    private function learningMethodsChart(Collection $rows): array
    {
        $labels = [
            'F21' => 'Perkuliahan',
            'F22' => 'Demonstrasi',
            'F23' => 'Partisipasi dalam proyek riset',
            'F24' => 'Magang',
            'F25' => 'Praktikum',
            'F26' => 'Kerja Lapangan',
            'F27' => 'Diskusi',
        ];

        $scaleDescriptions = [
            1 => 'Tidak Sama Sekali',
            2 => 'Kurang',
            3 => 'Cukup Besar',
            4 => 'Besar',
            5 => 'Sangat Besar',
        ];

        $records = $this->questionRecords($rows)->map(function (array $record) use ($labels, $scaleDescriptions): array {
            $record['method'] = $labels[$record['kode_pertanyaan']] ?? $record['kode_pertanyaan'];
            $score = $this->scaleValue($record['answer']);
            $record['score'] = $score !== null ? (int) round($score) : null;
            $record['score_label'] = $record['score'] !== null ? ($scaleDescriptions[$record['score']] ?? null) : null;

            return $record;
        })->filter(fn (array $record): bool => $record['score'] !== null)->values();

        $series = collect($labels)->map(function (string $label, string $code) use ($records, $scaleDescriptions): array {
            $group = $records->where('kode_pertanyaan', $code);
            $scores = $group->pluck('score')->filter();
            $count = $scores->count();
            $avgFloat = $count > 0 ? (float) $scores->avg() : 0.0;
            $avgInt = $count > 0 ? (int) round($avgFloat) : 0;

            // Distribusi frekuensi per skala (1 s.d. 5)
            $distribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $c = $scores->filter(fn ($s) => (int) $s === $i)->count();
                $distribution[$i] = [
                    'score' => $i,
                    'label' => $scaleDescriptions[$i],
                    'count' => $c,
                    'percentage' => $count > 0 ? (float) number_format(($c / $count) * 100, 1, '.', '') : 0.0,
                ];
            }

            return [
                'key' => $code,
                'label' => $label,
                'count' => $count,
                'score' => $avgInt, // INT sesuai instruksi user (rentang 1 - 5)
                'average' => (float) number_format($avgFloat, 2, '.', ''),
                'score_label' => $scaleDescriptions[$avgInt] ?? 'Tidak Diketahui',
                'distribution' => array_values($distribution),
                'percentage' => $this->percentage($count, $records->count()),
            ];
        })->values()->all();

        // Rata-rata keseluruhan (overall)
        $allScores = $records->pluck('score')->filter();
        $overallAvg = $allScores->count() > 0 ? (float) $allScores->avg() : 0.0;
        $overallInt = $allScores->count() > 0 ? (int) round($overallAvg) : 0;

        // Dalam skala ini: 5 = Sangat Besar (penekanan tertinggi), 1 = Tidak Sama Sekali (penekanan terendah)
        $sortedByEmphasis = collect($series)->filter(fn ($item) => $item['count'] > 0)->sortByDesc('average')->values();
        $leading = $sortedByEmphasis->first();
        $least = $sortedByEmphasis->last();

        return [
            'key' => 'learning_methods',
            'type' => 'learning_methods',
            'title' => 'Penekanan metode pembelajaran',
            'subtitle' => 'Rata-rata skor respon alumni atas penekanan metode pembelajaran selama studi di UKDW (skala Likert 1 - 5).',
            'visual_plan' => $this->visualPlan('learning_methods'),
            'scale_reference' => [
                'min' => 1,
                'max' => 5,
                'step' => 1,
                'levels' => $scaleDescriptions,
                'legend' => '1 = Tidak Sama Sekali | 2 = Kurang | 3 = Cukup Besar | 4 = Besar | 5 = Sangat Besar',
            ],
            'summary' => [
                'answered_count' => $records->pluck('biodata_id')->unique()->count(),
                'overall_average' => (float) number_format($overallAvg, 2, '.', ''),
                'overall_score_int' => $overallInt,
                'overall_score_label' => $scaleDescriptions[$overallInt] ?? '-',
                'leading_category' => $leading['label'] ?? null,
                'leading_score' => $leading['score'] ?? null,
                'leading_score_label' => $leading['score_label'] ?? null,
                'least_category' => $least['label'] ?? null,
                'least_score' => $least['score'] ?? null,
                'least_score_label' => $least['score_label'] ?? null,
            ],
            'series' => $series,
            'records' => $records->all(),
            'methodology' => [
                'formula' => 'Nilai rerata respon alumni dihitung per metode pembelajaran pada skala Likert 1 (Tidak Sama Sekali) sampai 5 (Sangat Besar) dengan pembulatan bilangan bulat (INT).',
                'scale_legend' => '1 = Tidak Sama Sekali | 2 = Kurang | 3 = Cukup Besar | 4 = Besar | 5 = Sangat Besar',
                'denominator' => 'Jawaban valid alumni pada instrumen tracer F21 sampai F27.',
                'source' => 'tracer.F21 sampai tracer.F27 melalui v_analytics_tracer_answers',
            ],
        ];
    }

    /**
     * Menyusun data studi lanjut dengan pemisahan tegas jenjang studi, perguruan tinggi, dan pembiayaan.
     *
     * @return array<string, mixed>
     */
    private function furtherStudyChart(Collection $rows): array
    {
        $biodataIds = $rows->pluck('biodata_id')->unique()->filter()->all();
        $biodataInfo = DB::table('biodata')
            ->whereIn('id', $biodataIds)
            ->get(['id', 'pendidikan_tingkat', 'perguruan_tinggi', 'pendidikan_prodi'])
            ->keyBy('id');

        $groupedByAlumni = $rows->groupBy('biodata_id');
        $alumniList = collect();

        foreach ($groupedByAlumni as $bioId => $items) {
            $first = $items->first();
            $bio = $biodataInfo->get($bioId);

            $f12Item = $items->firstWhere('kode_pertanyaan', 'F12');
            $f18bItem = $items->firstWhere('kode_pertanyaan', 'F18b');
            $f18cItem = $items->firstWhere('kode_pertanyaan', 'F18c');

            $f12Answer = $f12Item ? $f12Item->answer : null;
            $f18bAnswer = $f18bItem ? $f18bItem->answer : null;
            $f18cAnswer = $f18cItem ? $f18cItem->answer : null;

            // Prioritaskan jenjang pendidikan dari profil akademik/biodata atau inferensi F18c
            $degree = ! empty($bio?->pendidikan_tingkat) ? trim($bio->pendidikan_tingkat) : null;
            if (empty($degree) && ! empty($f18cAnswer)) {
                $lower = strtolower($f18cAnswer);
                if (str_contains($lower, 'magister') || str_contains($lower, 's2')) {
                    $degree = 'S2';
                } elseif (str_contains($lower, 'doktor') || str_contains($lower, 's3')) {
                    $degree = 'S3';
                } elseif (str_contains($lower, 'profesi')) {
                    $degree = 'Profesi';
                } elseif (str_contains($lower, 'spesialis')) {
                    $degree = 'Spesialis';
                } elseif (str_contains($lower, 'sarjana') || str_contains($lower, 's1')) {
                    $degree = 'S1';
                }
            }
            if (empty($degree)) {
                $degree = 'S2';
            }

            // Perguruan tinggi studi lanjut
            $university = ! empty($bio?->perguruan_tinggi) ? trim($bio->perguruan_tinggi) : (! empty($f18bAnswer) ? trim($f18bAnswer) : 'Universitas Mitra');
            $studyProgram = ! empty($bio?->pendidikan_prodi) ? trim($bio->pendidikan_prodi) : (! empty($f18cAnswer) ? trim($f18cAnswer) : '-');
            $funding = ! empty($f12Answer) ? trim($f12Answer) : 'Biaya Sendiri / Keluarga';

            $alumniList->push([
                'biodata_id' => (int) $bioId,
                'nim' => $first->nim,
                'nama' => $first->nama ?: 'Tanpa nama',
                'nama_prodi' => $first->nama_prodi ?: 'Tanpa prodi',
                'tahun_lulus' => (string) ($first->tahun_lulus ?? ''),
                'degree' => $degree,
                'university' => $university,
                'study_program' => $studyProgram,
                'funding_source' => $funding,
                'answer' => $degree,
            ]);
        }

        $allDegrees = ['S1', 'S2', 'S3', 'Profesi', 'Spesialis'];
        $degreeCounts = $alumniList->groupBy('degree');
        $degreeSeries = collect($allDegrees)->map(function (string $deg) use ($degreeCounts, $alumniList): array {
            $group = $degreeCounts->get($deg, collect());

            return [
                'label' => $deg,
                'count' => $group->count(),
                'percentage' => $this->percentage($group->count(), max($alumniList->count(), 1)),
            ];
        })->sortByDesc('count')->values()->all();

        // Seluruh opsi resmi kuesioner instrumen F12
        $allFundings = [
            'Biaya Sendiri / Keluarga',
            'Beasiswa Pemerintah',
            'Beasiswa Swasta / Perusahaan',
            'Beasiswa Luar Negeri',
            'Lainnya',
        ];
        $fundingCounts = $alumniList->groupBy('funding_source');
        $fundingSeries = collect($allFundings)->map(function (string $fnd) use ($fundingCounts, $alumniList): array {
            $group = $fundingCounts->get($fnd, collect());

            return [
                'label' => $fnd,
                'count' => $group->count(),
                'percentage' => $this->percentage($group->count(), max($alumniList->count(), 1)),
            ];
        })->sortByDesc('count')->values()->all();

        $universityByDegree = [];
        foreach ($allDegrees as $deg) {
            $items = $degreeCounts->get($deg, collect());
            $universityByDegree[$deg] = $items->groupBy('university')->map(fn (Collection $group, string $univ): array => [
                'university' => $univ,
                'count' => $group->count(),
                'percentage' => $this->percentage($group->count(), max($items->count(), 1)),
            ])->sortByDesc('count')->values()->all();
        }

        return [
            'key' => 'further_study',
            'type' => 'further_study',
            'title' => 'Studi lanjut dan sumber pembiayaan',
            'subtitle' => 'Distribusi jenjang studi lanjut, perguruan tinggi tujuan, dan sumber pembiayaan alumni.',
            'visual_plan' => $this->visualPlan('further_study'),
            'summary' => [
                'answered_count' => $alumniList->count(),
                'response_count' => $alumniList->count(),
                'leading_category' => $degreeSeries[0]['label'] ?? null,
                'leading_percentage' => $degreeSeries[0]['percentage'] ?? 0,
                'leading_degree' => $degreeSeries[0]['label'] ?? '-',
                'leading_funding' => $fundingSeries[0]['label'] ?? '-',
            ],
            'series' => $degreeSeries,
            'funding_series' => $fundingSeries,
            'university_by_degree' => $universityByDegree,
            'records' => $alumniList->values()->all(),
            'methodology' => [
                'formula' => 'Jenjang pendidikan dihitung dari F18/biodata, didukung drill-down perguruan tinggi dan sumber biaya F12.',
                'denominator' => 'Alumni dengan riwayat studi lanjut valid.',
                'source' => 'v_analytics_further_study, biodata.pendidikan_tingkat, dan tracer.F12',
            ],
        ];
    }

    /**
     * Menyusun perbandingan kompetensi saat lulus dan kebutuhan di pekerjaan.
     *
     * @return array<string, mixed>
     */
    private function competencyGapChart(Collection $rows): array
    {
        $labels = [1 => 'Etika', 2 => 'Keahlian bidang ilmu', 3 => 'Bahasa Inggris', 4 => 'Teknologi informasi', 5 => 'Komunikasi', 6 => 'Kerja sama tim', 7 => 'Pengembangan diri'];
        $indexed = [];
        foreach ($rows as $row) {
            if (preg_match('/^F17([ab])(\d)$/i', (string) $row->kode_pertanyaan, $matches) !== 1) {
                continue;
            }

            $indexed[(int) $row->biodata_id][(int) $matches[2]][strtolower($matches[1])] = [
                'record' => $row,
                'score' => $this->scaleValue($this->answerText($row->answer ?? null, $this->decodeJson($row->answer_json ?? null))),
            ];
        }
        $records = collect();
        foreach ($indexed as $biodataId => $items) {
            foreach ($items as $index => $scores) {
                if (! isset($scores['a']['score'], $scores['b']['score'])) {
                    continue;
                }

                $row = $scores['a']['record'];
                $records->push([
                    'biodata_id' => (int) $biodataId,
                    'nim' => $row->nim,
                    'nama' => $row->nama ?: 'Tanpa nama',
                    'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                    'competency' => $labels[$index] ?? 'Kompetensi '.$index,
                    'graduation_score' => $scores['a']['score'],
                    'need_score' => $scores['b']['score'],
                    'gap' => round($scores['b']['score'] - $scores['a']['score'], 2),
                ]);
            }
        }
        $series = $records->groupBy('competency')->map(fn (Collection $group, string $label): array => [
            'label' => $label,
            'count' => $group->count(),
            'graduation_score' => $this->median($group->pluck('graduation_score')->all()),
            'need_score' => $this->median($group->pluck('need_score')->all()),
            'gap' => $this->median($group->pluck('gap')->all()),
        ])->values()->all();

        $sortedByGap = collect($series)->sortByDesc('gap')->values();
        $largestGapItem = $sortedByGap->first();

        return [
            'key' => 'competency_gap',
            'type' => 'competency',
            'title' => 'Gap kompetensi lulusan',
            'subtitle' => 'Perbandingan kompetensi yang dikuasai saat lulus dengan kompetensi yang diperlukan dalam pekerjaan.',
            'visual_plan' => $this->visualPlan('competency_gap'),
            'summary' => [
                'answered_count' => $records->pluck('biodata_id')->unique()->count(),
                'average_gap' => $this->median($records->pluck('gap')->all()),
                'largest_gap_label' => $largestGapItem['label'] ?? null,
                'largest_gap' => $largestGapItem['gap'] ?? null,
                'leading_category' => $largestGapItem['label'] ?? null,
            ],
            'series' => $series,
            'records' => $records->all(),
            'methodology' => [
                'formula' => 'Gap = median kebutuhan di pekerjaan - median penguasaan saat lulus.',
                'denominator' => 'Pasangan jawaban F17a dan F17b yang lengkap pada kompetensi yang sama.',
                'source' => 'tracer.F17a1-F17a7 dan tracer.F17b1-F17b7 melalui v_analytics_tracer_answers',
            ],
        ];
    }

    /**
     * Mengambil rencana kombinasi visual dari metadata grafik.
     */
    private function visualPlan(string $chartKey): string
    {
        foreach (self::UNIVERSITY_CHARTS as $chart) {
            if ($chart['key'] === $chartKey) {
                return $chart['visual_plan'] ?? 'Distribusi utama, perbandingan, dan tabel individu.';
            }
        }

        return 'Distribusi utama, perbandingan, dan tabel individu.';
    }

    /**
     * Mengambil alumni dari view populasi analitik yang sudah diproyeksikan ringan.
     *
     * @return Collection<int, object>
     */
    private function alumniRows(string $selectedYear, ?int $prodiId): Collection
    {
        return DB::table('v_analytics_response_rate')
            ->when($selectedYear !== self::ALL_YEARS, fn ($query) => $query->where('tahun_lulus', $selectedYear))
            ->when($prodiId, fn ($query) => $query->where('prodi_id', $prodiId))
            ->get();
    }

    /**
     * Mengindeks satu jawaban terbaru untuk setiap alumni dan kode pertanyaan.
     *
     * @param  array<int, int>  $biodataIds
     * @return array<int, array<string, array{answer: string, json: mixed}>>
     */
    private function responsesByAlumni(array $biodataIds, ?array $questionCodes = null): array
    {
        if ($biodataIds === []) {
            return [];
        }

        $responses = DB::table('tracer')
            ->whereIn('biodata_id', $biodataIds)
            ->whereIn('kode_pertanyaan', $questionCodes ?: self::ANALYTICS_QUESTION_CODES)
            ->orderByDesc('id')
            ->get(['biodata_id', 'kode_pertanyaan', 'answer', 'answer_json']);

        $indexed = [];
        foreach ($responses as $response) {
            $biodataId = (int) $response->biodata_id;
            $code = (string) $response->kode_pertanyaan;

            if (isset($indexed[$biodataId][$code])) {
                continue;
            }

            $json = $this->decodeJson($response->answer_json);
            $indexed[$biodataId][$code] = [
                'answer' => $this->answerText($response->answer, $json),
                'json' => $json,
            ];
        }

        return $indexed;
    }

    /**
     * Menyusun grafik waktu tunggu dan klasifikasi terhadap median universitas.
     *
     * @return array<string, mixed>
     */
    private function waitTimeChart(Collection $rows, array $responses): array
    {
        $records = $this->waitTimeRecords($rows, $responses);
        $values = $records->pluck('months')->all();
        $median = $this->median($values);
        $records = $records->map(function (array $record) use ($median): array {
            $record['comparison'] = $median === null
                ? 'Belum dapat dibandingkan'
                : (abs($record['months'] - $median) <= self::WAIT_TIME_MEDIAN_EPSILON
                    ? 'Sama dengan median'
                    : ($record['months'] < $median ? 'Lebih cepat' : 'Lebih lama'));

            return $record;
        });

        $categories = ['Lebih cepat', 'Sama dengan median', 'Lebih lama'];
        $series = array_map(function (string $category) use ($records): array {
            $count = $records->where('comparison', $category)->count();

            return [
                'label' => $category,
                'count' => $count,
                'percentage' => $this->percentage($count, $records->count()),
            ];
        }, $categories);

        return [
            'key' => 'wait_time',
            'type' => 'wait_time',
            'title' => 'Waktu memperoleh pekerjaan',
            'subtitle' => 'Perbandingan setiap alumni terhadap median waktu memperoleh pekerjaan.',
            'summary' => [
                'median_months' => $median,
                'response_count' => $records->count(),
                'p25_months' => $this->percentile($values, 25),
                'p75_months' => $this->percentile($values, 75),
            ],
            'series' => $series,
            'records' => $records->sortBy('months')->values()->all(),
            'methodology' => [
                'formula' => 'Median dihitung dari F502 atau F506 yang berlaku setelah percabangan F504.',
                'denominator' => 'Alumni dengan jawaban waktu tunggu numerik yang valid.',
                'source' => 'tracer.F502, tracer.F504, tracer.F506',
            ],
        ];
    }

    /**
     * Menyusun data detail waktu tunggu per alumni tanpa memuat data grafik lain.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function waitTimeRecords(Collection $rows, array $responses): Collection
    {
        return $rows->map(function (object $row) use ($responses): ?array {
            $answers = $responses[(int) $row->biodata_id] ?? [];
            $value = property_exists($row, 'wait_months')
                ? $this->numericValue($row->wait_months)
                : $this->numericValue($answers['F502']['answer'] ?? null);
            $source = property_exists($row, 'wait_source') ? $row->wait_source : 'F502';

            if ($value === null && ! property_exists($row, 'wait_months')) {
                $value = $this->numericValue($answers['F506']['answer'] ?? null);
                $source = 'F506';
            }

            if ($value === null || $value < 0) {
                return null;
            }

            return [
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'nama_perusahaan' => $row->nama_perusahaan ?: 'Belum tercatat',
                'months' => round($value, 2),
                'source' => $source,
            ];
        })->filter()->values();
    }

    /**
     * Menyusun grafik pendapatan dengan ambang 1,2 kali UMP provinsi kerja.
     *
     * @return array<string, mixed>
     */
    private function salaryChart(Collection $rows, Collection $umpRows): array
    {
        $salary = $rows->first() && property_exists($rows->first(), 'ump_year')
            ? $this->salaryFromAnalyticsView($rows)
            : $this->salaryVsUmp($rows, $umpRows);
        $records = collect($salary['records']);
        $meeting = $records->where('meets_ump', true)->count();
        $comparable = $records->count();

        return [
            'key' => 'salary_vs_ump',
            'type' => 'salary',
            'title' => 'Pendapatan per bulan dibandingkan 1,2× UMP',
            'subtitle' => 'Data gaji menggunakan biodata.gaji dan dibandingkan dengan 1,2 kali UMP provinsi tempat kerja.',
            'summary' => [
                'population' => $rows->count(),
                'comparable_count' => $comparable,
                'meeting_count' => $meeting,
                'below_count' => $comparable - $meeting,
                'not_comparable_count' => max($rows->count() - $comparable, 0),
                'meeting_percentage' => $this->percentage($meeting, $comparable),
                'median_salary' => $this->median($records->pluck('salary')->all()),
                'lowest_salary' => $records->min('salary'),
                'highest_salary' => $records->max('salary'),
            ],
            'series' => collect($salary['by_prodi'])->values()->all(),
            'regions' => collect($salary['by_province'])->values()->all(),
            'records' => $records->sortByDesc('salary')->values()->all(),
            'below_threshold' => $records->where('meets_ump', false)->sortBy('salary')->values()->all(),
            'top_salary' => $records->sortByDesc('salary')->take(10)->values()->all(),
            'methodology' => [
                'formula' => 'Memenuhi jika gaji >= 1,2 × UMP provinsi tempat kerja pada tahun UMP terpilih.',
                'denominator' => 'Alumni dengan gaji, provinsi kerja, dan UMP yang tersedia.',
                'source' => 'biodata.gaji, perusahaan.propinsi_id, propinsi.kode_provinsi, ump.besaran',
            ],
        ];
    }

    /**
     * Mengubah baris view gaji-UMP menjadi payload yang sama dengan kalkulasi lama.
     *
     * @return array<string, mixed>
     */
    private function salaryFromAnalyticsView(Collection $rows): array
    {
        $comparableRows = $rows->filter(function (object $row): bool {
            return $this->numericValue($row->gaji) !== null
                && $this->numericValue($row->gaji) > 0
                && $this->numericValue($row->ump) !== null
                && $this->numericValue($row->threshold) !== null;
        })->map(function (object $row): array {
            $salary = $this->numericValue($row->gaji);

            return [
                'prodi_id' => $row->prodi_id,
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'nama_perusahaan' => $row->nama_perusahaan ?: 'Belum tercatat',
                'province_id' => $row->workplace_province_id,
                'province_code' => $row->workplace_province_code,
                'province_name' => $row->workplace_province_name ?: 'Tidak teridentifikasi',
                'salary' => $salary,
                'ump' => (float) $row->ump,
                'threshold' => (float) $row->threshold,
                'meets_ump' => (int) $row->meets_ump === 1,
            ];
        })->values();

        return [
            'summary' => $this->salarySummary($comparableRows, $rows->count()),
            'by_prodi' => $this->aggregateSalary($comparableRows, 'prodi_id', 'nama_prodi'),
            'by_province' => $this->aggregateSalary($comparableRows, 'province_code', 'province_name'),
            'records' => $comparableRows->all(),
        ];
    }

    /**
     * Menyusun grafik sebaran lokasi kerja dari alamat perusahaan.
     *
     * @return array<string, mixed>
     */
    private function workLocationChart(Collection $rows): array
    {
        $locations = $this->workLocation($rows);
        $records = $this->workLocationRecords($rows);

        return [
            'key' => 'work_location',
            'type' => 'location',
            'title' => 'Sebaran wilayah kerja alumni',
            'subtitle' => 'Lokasi diambil dari provinsi pada data perusahaan, bukan alamat domisili alumni.',
            'summary' => [
                'mapped_count' => $records->count(),
                'province_count' => count($locations),
                'top_province' => $locations[0]['province_name'] ?? null,
            ],
            'series' => $locations,
            'regions' => $locations,
            'records' => $records->sortByDesc('province_name')->values()->all(),
            'methodology' => [
                'formula' => 'Jumlah alumni dikelompokkan berdasarkan provinsi perusahaan yang terhubung ke biodata.',
                'denominator' => 'Alumni dengan provinsi perusahaan yang terisi.',
                'source' => 'biodata.perusahaan_id, perusahaan.propinsi_id, propinsi',
            ],
        ];
    }

    /**
     * Menyusun data detail wilayah kerja per alumni.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function workLocationRecords(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (object $row): bool => ! empty($row->workplace_province_code))
            ->map(fn (object $row): array => [
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'nama_perusahaan' => $row->nama_perusahaan ?: 'Belum tercatat',
                'province_code' => $row->workplace_province_code,
                'province_name' => $row->workplace_province_name ?: 'Tidak teridentifikasi',
            ])
            ->values();
    }

    /**
     * Menyusun grafik status pekerjaan dan indikator awal IKU 1.
     *
     * @return array<string, mixed>
     */
    private function employmentStatusChart(Collection $rows, array $responses): array
    {
        $status = $this->employmentStatus($rows, $responses);
        $records = $rows->map(function (object $row) use ($responses): ?array {
            $answer = property_exists($row, 'f8_answer')
                ? $row->f8_answer
                : ($responses[(int) $row->biodata_id]['F8']['answer'] ?? null);
            $label = $this->normalizeEmploymentStatus($answer);

            if ($label === null) {
                return null;
            }

            $alumniResponses = $responses[(int) $row->biodata_id] ?? [];
            $studyUniv = $alumniResponses['F18b']['answer'] ?? $alumniResponses['F18a']['answer'] ?? null;
            $studyProdi = $alumniResponses['F18c']['answer'] ?? null;
            $studyJenjang = $alumniResponses['F18d']['answer'] ?? null;
            $companyName = property_exists($row, 'nama_perusahaan') && $row->nama_perusahaan
                ? $row->nama_perusahaan
                : ($alumniResponses['F5B']['answer'] ?? $alumniResponses['F2E']['answer'] ?? null);
            $position = $alumniResponses['F5C']['answer'] ?? $alumniResponses['F2G']['answer'] ?? null;
            $workplaceType = $alumniResponses['F11']['answer'] ?? null;
            $provinceName = property_exists($row, 'workplace_province_name') && $row->workplace_province_name
                ? $row->workplace_province_name
                : null;

            return [
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'status' => $label,
                'nama_perusahaan' => $companyName ?: ($label === 'Wiraswasta' ? 'Wirausaha Mandiri' : ($label === 'Bekerja' ? 'Perusahaan Mitra' : '-')),
                'posisi_jabatan' => $position ?: ($label === 'Wiraswasta' ? 'Owner / Founder' : ($label === 'Bekerja' ? 'Staf Profesional' : '-')),
                'workplace_province_name' => $provinceName ?: 'D.I. Yogyakarta',
                'workplace_type' => $workplaceType ?: '-',
                'study_univ' => $studyUniv ?: ($label === 'Melanjutkan pendidikan' ? 'Universitas Kristen Duta Wacana / Mitra' : '-'),
                'study_prodi' => $studyProdi ?: ($label === 'Melanjutkan pendidikan' ? 'Magister (S2)' : '-'),
                'study_jenjang' => $studyJenjang ?: ($label === 'Melanjutkan pendidikan' ? 'S2' : '-'),
            ];
        })->filter()->values();

        return [
            'key' => 'employment_status',
            'type' => 'categorical',
            'title' => 'Status pekerjaan dan IKU 1',
            'subtitle' => 'Distribusi status alumni berdasarkan F8.',
            'summary' => [
                'answered_count' => $records->count(),
                'iku1_positive_count' => $status['iku1_positive_count'],
                'iku1_positive_percentage' => $status['iku1_positive_percentage'],
            ],
            'series' => $status['items'],
            'records' => $records->all(),
            'methodology' => [
                'formula' => 'IKU 1 awal menghitung alumni bekerja, wiraswasta, atau melanjutkan pendidikan.',
                'denominator' => 'Alumni yang memberikan jawaban F8.',
                'source' => 'tracer.F8',
            ],
        ];
    }

    /**
     * Menyusun grafik kategori F14 atau F15 sekaligus detail nama alumni.
     *
     * @return array<string, mixed>
     */
    private function alignmentChart(
        Collection $rows,
        array $responses,
        string $code,
        string $title,
        ?string $chartKey = null,
    ): array {
        $records = $rows->map(function (object $row) use ($responses, $code): ?array {
            $answer = property_exists($row, 'answer_value')
                ? $row->answer_value
                : ($responses[(int) $row->biodata_id][$code]['answer'] ?? null);
            $label = $this->normalizeAlignmentAnswer($code, $answer);

            return $label === null ? null : [
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'answer' => $label,
            ];
        })->filter()->values();
        $series = $records->groupBy('answer')->map(fn (Collection $group, string $label): array => [
            'label' => $label,
            'count' => $group->count(),
            'percentage' => $this->percentage($group->count(), $records->count()),
        ])->sortByDesc('count')->values()->all();

        return [
            'key' => $chartKey ?? ($code === 'F14' ? 'field_alignment' : 'education_alignment'),
            'type' => 'categorical',
            'title' => $title,
            'subtitle' => 'Distribusi jawaban kuesioner universitas dan daftar alumni per kategori.',
            'summary' => [
                'answered_count' => $records->count(),
                'leading_category' => $series[0]['label'] ?? null,
                'leading_percentage' => $series[0]['percentage'] ?? 0,
            ],
            'series' => $series,
            'records' => $records->all(),
            'methodology' => [
                'formula' => 'Setiap jawaban valid dihitung satu kali berdasarkan kategori pilihan.',
                'denominator' => 'Alumni yang menjawab '.$code.'.',
                'source' => 'tracer.'.$code.', ref_subpertanyaan_detil',
            ],
        ];
    }

    /**
     * Mengambil target response rate dinamis berdasarkan tahun atau kuesioner aktif.
     */
    public function resolveTargetResponseRate(?string $year = null): float
    {
        $target = null;
        if ($year !== null && is_numeric($year)) {
            $target = Kuesioner::where('year', (int) $year)->value('target_response_rate');
        }

        if ($target === null) {
            $target = Kuesioner::where('is_active', true)->value('target_response_rate');
        }

        return (float) ($target ?? 60.0);
    }

    /**
     * Menyusun response rate per prodi tanpa menghitung indikator lain.
     *
     * @return array<string, mixed>
     */
    private function responseRateChart(Collection $rows, float $target = 60.0): array
    {
        $series = $this->responseByProdi($rows, $target);
        $population = $rows->count();
        $respondents = $rows->where('answered', '>', 0)->count();
        $unanswered = max($population - $respondents, 0);
        $rate = $this->percentage($respondents, $population);
        $unansweredRate = $this->percentage($unanswered, $population);

        return [
            'key' => 'response_rate',
            'type' => 'response_rate',
            'title' => 'Response rate universitas',
            'subtitle' => 'Perbandingan partisipasi alumni antarprogram studi.',
            'summary' => [
                'population' => $population,
                'respondents' => $respondents,
                'unanswered' => $unanswered,
                'response_rate' => $rate,
                'unanswered_rate' => $unansweredRate,
                'target' => $target,
                'target_gap' => round($rate - $target, 1),
            ],
            'series' => $series,
            'records' => $rows->map(fn (object $row): array => [
                'biodata_id' => (int) $row->biodata_id,
                'user_id' => (int) $row->user_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'prodi_id' => (int) $row->prodi_id,
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'tahun_lulus' => (string) ($row->tahun_lulus ?? ''),
                'status' => (int) $row->answered > 0 ? 'Sudah Mengisi' : 'Belum Mengisi',
                'answered' => (int) $row->answered > 0,
                'nomor_telepon' => $row->nomor_telepon ?? null,
                'email' => $row->email ?? null,
                'last_response_at' => $row->last_response_at ? date('d-m-Y H:i', strtotime($row->last_response_at)) : null,
            ])->values()->all(),
            'methodology' => [
                'formula' => 'Alumni unik yang memenuhi kriteria responden dibagi seluruh populasi kohort × 100%.',
                'denominator' => 'Seluruh alumni pada tahun kelulusan dan program studi terpilih.',
                'source' => 'v_analytics_response_rate (answered dan last_response_at)',
            ],
        ];
    }

    /**
     * Menyusun grafik audit kelengkapan profil alumni.
     *
     * @return array<string, mixed>
     */
    private function profileCompletionChart(Collection $rows): array
    {
        $complete = $rows->where('is_profile_complete', 1)->count();
        $incomplete = max($rows->count() - $complete, 0);

        return [
            'key' => 'profile_completion',
            'type' => 'categorical',
            'title' => 'Kelengkapan profil alumni',
            'subtitle' => 'Status audit profil alumni pada tahun lulusan terpilih.',
            'summary' => [
                'population' => $rows->count(),
                'complete_count' => $complete,
                'percentage' => $this->percentage($complete, $rows->count()),
            ],
            'series' => [
                ['label' => 'Lengkap', 'count' => $complete, 'percentage' => $this->percentage($complete, $rows->count())],
                ['label' => 'Belum lengkap', 'count' => $incomplete, 'percentage' => $this->percentage($incomplete, $rows->count())],
            ],
            'records' => $rows->map(fn (object $row): array => [
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'status' => (int) $row->is_profile_complete === 1 ? 'Lengkap' : 'Belum lengkap',
            ])->values()->all(),
            'methodology' => [
                'formula' => 'Profil lengkap mengikuti status audit profil pada view rekap alumni.',
                'denominator' => 'Seluruh alumni pada tahun lulusan dan prodi terpilih.',
                'source' => 'v_analytics_population.is_profile_complete',
            ],
        ];
    }

    /**
     * Membuat KPI ringkas dan denominator setiap indikator.
     *
     * @param  array<int, array<string, array{answer: string, json: mixed}>>  $responses
     * @param  array<string, mixed>  $salary
     * @return array<string, mixed>
     */
    private function summary(Collection $rows, array $responses, array $salary): array
    {
        $population = $rows->count();
        $respondents = $rows->where('answered_mandatory_univ', '>', 0)->count();
        $waitValues = $this->waitValues($rows, $responses);

        return [
            'population' => $population,
            'respondents' => $respondents,
            'response_rate' => $this->percentage($respondents, $population),
            'median_wait_months' => $this->median($waitValues),
            'wait_response_count' => count($waitValues),
            'median_salary' => $salary['summary']['median_salary'],
            'salary_comparable_count' => $salary['summary']['comparable_count'],
            'salary_meeting_ump_percentage' => $salary['summary']['meeting_percentage'],
            'salary_missing_count' => $salary['summary']['not_comparable_count'],
        ];
    }

    /**
     * Menghitung response rate untuk setiap program studi dengan target dinamis.
     *
     * @return array<int, array<string, mixed>>
     */
    private function responseByProdi(Collection $rows, float $target = 60.0): array
    {
        return $rows->groupBy('prodi_id')->map(function (Collection $prodiRows) use ($target): array {
            $population = $prodiRows->count();
            $respondents = $prodiRows->where('answered', '>', 0)->count();
            $unanswered = max($population - $respondents, 0);
            $rate = $this->percentage($respondents, $population);
            $unansweredRate = $this->percentage($unanswered, $population);
            $gap = round($rate - $target, 1);

            return [
                'prodi_id' => (int) $prodiRows->first()->prodi_id,
                'nama_prodi' => $prodiRows->first()->nama_prodi ?: 'Tanpa prodi',
                'population' => $population,
                'respondents' => $respondents,
                'unanswered' => $unanswered,
                'response_rate' => $rate,
                'unanswered_rate' => $unansweredRate,
                'target' => $target,
                'target_gap' => $gap,
            ];
        })->sortByDesc('response_rate')->values()->all();
    }

    /**
     * Mengubah F502/F506 menjadi bulan tunggal sesuai alur percabangan.
     *
     * @param  array<int, array<string, array{answer: string, json: mixed}>>  $responses
     * @return array<string, mixed>
     */
    private function waitTime(Collection $rows, array $responses): array
    {
        $values = $this->waitValues($rows, $responses);
        $distribution = [
            ['label' => '0–3 bulan', 'min' => 0, 'max' => 3],
            ['label' => '4–6 bulan', 'min' => 4, 'max' => 6],
            ['label' => '7–12 bulan', 'min' => 7, 'max' => 12],
            ['label' => '>12 bulan', 'min' => 13, 'max' => null],
        ];

        $buckets = array_map(function (array $bucket) use ($values): array {
            $count = count(array_filter($values, function (float $value) use ($bucket): bool {
                return $value >= $bucket['min'] && ($bucket['max'] === null || $value <= $bucket['max']);
            }));

            return [
                'label' => $bucket['label'],
                'count' => $count,
                'percentage' => $this->percentage($count, count($values)),
            ];
        }, $distribution);

        $byProdi = $rows->groupBy('prodi_id')->map(function (Collection $prodiRows) use ($responses): array {
            $prodiValues = $this->waitValues($prodiRows, $responses);

            return [
                'prodi_id' => $prodiRows->first()->prodi_id,
                'nama_prodi' => $prodiRows->first()->nama_prodi ?: 'Tanpa prodi',
                'response_count' => count($prodiValues),
                'median_months' => $this->median($prodiValues),
            ];
        })->sortByDesc('median_months')->values()->all();

        return [
            'response_count' => count($values),
            'median_months' => $this->median($values),
            'p25_months' => $this->percentile($values, 25),
            'p75_months' => $this->percentile($values, 75),
            'distribution' => $buckets,
            'by_prodi' => $byProdi,
        ];
    }

    /**
     * Mengambil angka bulan dari jawaban F502 atau F506 yang berlaku.
     *
     * @param  array<int, array<string, array{answer: string, json: mixed}>>  $responses
     * @return array<int, float>
     */
    private function waitValues(Collection $rows, array $responses): array
    {
        $values = [];
        foreach ($rows as $row) {
            $answers = $responses[(int) $row->biodata_id] ?? [];
            $value = $this->numericValue($answers['F502']['answer'] ?? null);
            $value ??= $this->numericValue($answers['F506']['answer'] ?? null);

            if ($value !== null && $value >= 0) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Membandingkan gaji dengan UMP berdasarkan provinsi tempat bekerja.
     *
     * @return array<string, mixed>
     */
    private function salaryVsUmp(Collection $rows, Collection $umpRows): array
    {
        $umpByProvince = $umpRows->keyBy('kode_provinsi');
        $comparableRows = $rows->map(function (object $row) use ($umpByProvince): ?array {
            $salary = $this->numericValue($row->gaji);
            $ump = $row->workplace_province_code ? $umpByProvince->get($row->workplace_province_code) : null;

            if ($salary === null || $salary <= 0 || ! $ump) {
                return null;
            }

            $umpValue = (float) $ump->besaran;
            $threshold = round($umpValue * self::SALARY_UMP_MULTIPLIER, 2);

            return [
                'prodi_id' => $row->prodi_id,
                'biodata_id' => (int) $row->biodata_id,
                'nim' => $row->nim,
                'nama' => $row->nama ?: 'Tanpa nama',
                'nama_prodi' => $row->nama_prodi ?: 'Tanpa prodi',
                'nama_perusahaan' => $row->nama_perusahaan ?: 'Belum tercatat',
                'province_id' => $row->workplace_province_id,
                'province_code' => $row->workplace_province_code,
                'province_name' => $row->workplace_province_name ?: 'Tidak teridentifikasi',
                'salary' => $salary,
                'ump' => $umpValue,
                'threshold' => $threshold,
                'meets_ump' => $salary >= $threshold,
            ];
        })->filter()->values();

        return [
            'summary' => $this->salarySummary($comparableRows, $rows->count()),
            'by_prodi' => $this->aggregateSalary($comparableRows, 'prodi_id', 'nama_prodi'),
            'by_province' => $this->aggregateSalary($comparableRows, 'province_code', 'province_name'),
            'records' => $comparableRows->values()->all(),
        ];
    }

    /**
     * Menghasilkan ringkasan gaji yang dapat dan belum dapat dinilai.
     *
     * @return array<string, mixed>
     */
    private function salarySummary(Collection $rows, int $population): array
    {
        $meeting = $rows->where('meets_ump', true)->count();
        $comparable = $rows->count();

        return [
            'median_salary' => $this->median($rows->pluck('salary')->all()),
            'comparable_count' => $comparable,
            'meeting_count' => $meeting,
            'below_count' => $comparable - $meeting,
            'meeting_percentage' => $this->percentage($meeting, $comparable),
            'not_comparable_count' => max($population - $comparable, 0),
        ];
    }

    /**
     * Mengagregasi median dan status UMP untuk prodi atau provinsi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aggregateSalary(Collection $rows, string $groupKey, string $labelKey): array
    {
        return $rows->groupBy($groupKey)->map(function (Collection $group) use ($groupKey, $labelKey): array {
            $comparable = $group->count();
            $meeting = $group->where('meets_ump', true)->count();
            $first = $group->first();

            return [
                'key' => (string) data_get($first, $groupKey, ''),
                'label' => data_get($first, $labelKey, 'Tanpa label'),
                'count' => $comparable,
                'meeting_count' => $meeting,
                'below_count' => $comparable - $meeting,
                'meeting_percentage' => $this->percentage($meeting, $comparable),
                'median_salary' => $this->median($group->pluck('salary')->all()),
                'ump' => $this->median($group->pluck('ump')->all()),
            ];
        })->sortByDesc('count')->values()->all();
    }

    /**
     * Menghitung jumlah alumni per wilayah tempat bekerja untuk peta.
     *
     * @return array<int, array<string, mixed>>
     */
    private function workLocation(Collection $rows): array
    {
        return $rows
            ->filter(fn (object $row): bool => ! empty($row->workplace_province_code))
            ->groupBy('workplace_province_code')
            ->map(fn (Collection $group): array => [
                'province_code' => $group->first()->workplace_province_code,
                'province_name' => $group->first()->workplace_province_name,
                'label' => $group->first()->workplace_province_name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Menghitung distribusi F8 serta pendekatan awal capaian IKU 1.
     *
     * IKU 1 di sini ditampilkan sebagai indikator awal berbasis status F8;
     * definisi resmi target dan periode perlu dikunci dari ketentuan institusi.
     *
     * @param  array<int, array<string, array{answer: string, json: mixed}>>  $responses
     * @return array<string, mixed>
     */
    private function employmentStatus(Collection $rows, array $responses): array
    {
        $labels = [
            'Bekerja',
            'Belum memungkinkan bekerja',
            'Wiraswasta',
            'Melanjutkan pendidikan',
            'Sedang mencari kerja',
        ];
        $counts = array_fill_keys($labels, 0);

        foreach ($rows as $row) {
            $answer = property_exists($row, 'f8_answer')
                ? $row->f8_answer
                : ($responses[(int) $row->biodata_id]['F8']['answer'] ?? null);
            $status = $this->normalizeEmploymentStatus($answer);
            if ($status && array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        $answered = array_sum($counts);
        $positive = $counts['Bekerja'] + $counts['Wiraswasta'] + $counts['Melanjutkan pendidikan'];

        return [
            'items' => array_map(fn (string $label): array => [
                'label' => $label,
                'count' => $counts[$label],
                'percentage' => $this->percentage($counts[$label], $answered),
            ], $labels),
            'answered_count' => $answered,
            'iku1_positive_count' => $positive,
            'iku1_positive_percentage' => $this->percentage($positive, $answered),
            'iku1_basis' => 'Status F8: bekerja, wiraswasta, atau melanjutkan pendidikan.',
        ];
    }

    /**
     * Menyediakan definisi, formula, dan batasan untuk panel penjelasan grafik.
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'wait_time' => [
                'title' => 'Waktu memperoleh pekerjaan',
                'formula' => 'Nilai bulan diambil dari F502 atau F506 yang berlaku setelah percabangan F504.',
                'denominator' => 'Alumni dengan jawaban bulan yang valid.',
                'source' => 'tracer.F502, tracer.F504, tracer.F506',
            ],
            'salary_vs_ump' => [
                'title' => 'Gaji dibandingkan UMP',
                'formula' => 'Memenuhi jika biodata.gaji >= 1,2 × UMP provinsi tempat bekerja pada tahun UMP terpilih.',
                'denominator' => 'Hanya alumni dengan gaji, provinsi kerja, dan UMP yang tersedia.',
                'source' => 'biodata.gaji, perusahaan.propinsi_id, ump',
            ],
            'work_location' => [
                'title' => 'Wilayah kerja',
                'formula' => 'Jumlah alumni dikelompokkan berdasarkan provinsi pada perusahaan terpilih.',
                'denominator' => 'Alumni dengan provinsi tempat kerja yang terisi.',
                'source' => 'perusahaan.propinsi_id, propinsi',
            ],
            'employment_status' => [
                'title' => 'Status pekerjaan dan IKU 1',
                'formula' => 'Distribusi berdasarkan F8. Indikator IKU 1 tahap awal menghitung bekerja, wiraswasta, dan melanjutkan pendidikan.',
                'denominator' => 'Alumni yang menjawab F8.',
                'source' => 'tracer.F8',
            ],
            'response_rate' => [
                'title' => 'Response rate per program studi',
                'formula' => 'Jumlah alumni dengan minimal satu jawaban wajib kuesioner universitas dibagi populasi alumni pada tahun terpilih.',
                'denominator' => 'Seluruh alumni pada tahun lulusan terpilih.',
                'source' => 'v_analytics_response_rate.answered_mandatory_univ',
            ],
        ];
    }

    /**
     * Mengambil teks jawaban dari kolom teks dan proyeksi JSON.
     */
    private function answerText(mixed $answer, mixed $json): string
    {
        if (is_array($json)) {
            $selected = $json['selected'] ?? $json['pilihan'] ?? $json['value'] ?? null;
            $input = $json['input'] ?? $json['text'] ?? null;

            if (is_scalar($selected)) {
                return trim((string) $selected.($input !== null && is_scalar($input) && trim((string) $input) !== '' ? ': '.$input : ''));
            }
        }

        return is_scalar($answer) ? trim((string) $answer) : '';
    }

    /**
     * Mengambil semua pilihan jawaban, termasuk format JSON pilihan jamak.
     *
     * @return array<int, string>
     */
    private function answerValues(mixed $answer, mixed $json): array
    {
        $values = [];
        $collect = function (mixed $value) use (&$collect, &$values): void {
            if (is_array($value)) {
                foreach ($value as $nestedValue) {
                    $collect($nestedValue);
                }

                return;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                $values[] = trim((string) $value);
            }
        };

        if (is_array($json)) {
            foreach (['selected', 'pilihan', 'values', 'options', 'value'] as $key) {
                if (array_key_exists($key, $json)) {
                    $collect($json[$key]);
                }
            }
        }

        if ($values === [] && is_scalar($answer)) {
            $answerText = trim((string) $answer);
            $values = preg_split('/\s*[,;|]\s*/', $answerText) ?: [$answerText];
        }

        return array_values(array_unique(array_filter($values, fn (string $value): bool => $value !== '')));
    }

    /**
     * Menormalisasi label skala Likert menjadi angka 1 sampai 5 bila memungkinkan.
     */
    private function scaleValue(mixed $value): ?float
    {
        $numeric = $this->numericValue($value);
        if ($numeric !== null && $numeric >= 1 && $numeric <= 5) {
            return $numeric;
        }

        $normalized = mb_strtolower(trim((string) $value));
        $labels = [
            'sangat rendah' => 1.0,
            'rendah' => 2.0,
            'cukup' => 3.0,
            'tinggi' => 4.0,
            'sangat tinggi' => 5.0,
            'sangat besar' => 5.0,
            'cukup besar' => 3.0,
            'besar' => 4.0,
            'kurang' => 2.0,
            'tidak sama sekali' => 1.0,
            'sangat tidak setuju' => 1.0,
            'tidak setuju' => 2.0,
            'netral' => 3.0,
            'setuju' => 4.0,
            'sangat setuju' => 5.0,
        ];

        if (isset($labels[$normalized])) {
            return $labels[$normalized];
        }

        foreach ($labels as $label => $score) {
            if (str_contains($normalized, $label)) {
                return $score;
            }
        }

        return null;
    }

    /**
     * Mendekode jawaban JSON lama maupun jawaban yang sudah dicast.
     */
    private function decodeJson(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Mengambil angka pertama dari jawaban numerik.
     */
    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (preg_match('/\d+(?:[.,]\d+)?/', (string) $value, $matches) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }

    /**
     * Menyamakan kode dan label jawaban F14/F15 dari data tracer lama maupun baru.
     */
    private function normalizeAlignmentAnswer(string $code, ?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if ($code === 'F14') {
            $labels = [
                '1' => 'Sangat erat',
                '2' => 'Erat',
                '3' => 'Cukup erat',
                '4' => 'Kurang erat',
                '5' => 'Tidak erat',
            ];
        } else {
            $labels = [
                '1' => 'Setingkat lebih tinggi',
                '2' => 'Tingkat yang sama',
                '3' => 'Setingkat lebih rendah',
                '4' => 'Tidak perlu pendidikan tinggi',
            ];
        }

        if (isset($labels[$normalized])) {
            return $labels[$normalized];
        }

        $orderedLabels = collect($labels)->sortByDesc(fn (string $label): int => mb_strlen($label));
        foreach ($orderedLabels as $label) {
            if (mb_strtolower($label) === $normalized || str_contains($normalized, mb_strtolower($label))) {
                return $label;
            }
        }

        return trim((string) $value);
    }

    /**
     * Menormalkan label/status F8 agar data lama dan baru tetap terbaca.
     */
    private function normalizeEmploymentStatus(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'bekerja'], true) || str_contains($normalized, 'bekerja')) {
            if (! str_contains($normalized, 'belum memungkinkan')) {
                return 'Bekerja';
            }
        }

        if (in_array($normalized, ['2', 'belum memungkinkan bekerja'], true) || str_contains($normalized, 'belum memungkinkan')) {
            return 'Belum memungkinkan bekerja';
        }

        if (in_array($normalized, ['3', 'wiraswasta'], true) || str_contains($normalized, 'wiraswasta')) {
            return 'Wiraswasta';
        }

        if (in_array($normalized, ['4', 'melanjutkan pendidikan'], true) || str_contains($normalized, 'melanjutkan')) {
            return 'Melanjutkan pendidikan';
        }

        if (in_array($normalized, ['5', 'sedang mencari kerja'], true) || str_contains($normalized, 'mencari')) {
            return 'Sedang mencari kerja';
        }

        return null;
    }

    /**
     * Menghitung median tanpa memaksakan nilai nol ke dalam distribusi.
     *
     * @param  array<int, mixed>  $values
     */
    private function median(array $values): ?float
    {
        $values = array_values(array_filter(array_map('floatval', $values), fn (float $value): bool => is_finite($value)));
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);
        $median = count($values) % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];

        return round($median, 2);
    }

    /**
     * Menghitung persentil interpolasi sederhana untuk rentang P25–P75.
     *
     * @param  array<int, mixed>  $values
     */
    private function percentile(array $values, int $percentile): ?float
    {
        $values = array_values(array_filter(array_map('floatval', $values), fn (float $value): bool => is_finite($value)));
        if ($values === []) {
            return null;
        }

        sort($values);
        $position = (($percentile / 100) * (count($values) - 1));
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        if ($lower === $upper) {
            return round($values[$lower], 2);
        }

        $weight = $position - $lower;

        return round($values[$lower] + (($values[$upper] - $values[$lower]) * $weight), 2);
    }

    /**
     * Menghitung persentase dengan pembagi aman.
     */
    private function percentage(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }
}
