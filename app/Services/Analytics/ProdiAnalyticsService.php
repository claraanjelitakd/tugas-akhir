<?php

namespace App\Services\Analytics;

use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyediakan data analitik kuesioner yang dimiliki setiap program studi.
 *
 * Service ini membaca metadata pertanyaan dan pilihan langsung dari tabel
 * instrumen. Penambahan pertanyaan baru otomatis tersedia pada grafik yang
 * sesuai, pivot table, dan tab data responden tanpa daftar kode tambahan.
 */
class ProdiAnalyticsService
{
    /** Nilai sentinel untuk filter semua tahun kelulusan. */
    public const ALL_YEARS = 'ALL';

    /**
     * Mengambil program studi yang tersedia untuk filter analitik.
     *
     * @return Collection<int, array{id: int, kode_prodi: string|null, nama_prodi: string, has_questions: bool}>
     */
    public function getAvailableProdis(): Collection
    {
        $prodisWithQuestions = ProdiQuestion::query()
            ->distinct()
            ->pluck('prodi_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return Prodi::query()
            ->select('id', 'kode_prodi', 'nama_prodi')
            ->orderBy('nama_prodi')
            ->get()
            ->map(static function (Prodi $prodi) use ($prodisWithQuestions): array {
                return [
                    'id' => (int) $prodi->id,
                    'kode_prodi' => $prodi->kode_prodi,
                    'nama_prodi' => $prodi->nama_prodi,
                    'has_questions' => in_array((int) $prodi->id, $prodisWithQuestions, true),
                ];
            });
    }

    /**
     * Mengambil tahun lulusan dari populasi alumni, bukan hanya dari jawaban.
     *
     * @return array<int, string>
     */
    public function getAvailableYears(int $prodiId): array
    {
        $years = DB::table('biodata')
            ->leftJoin('data_akademik', 'data_akademik.nim', '=', 'biodata.nim')
            ->where('biodata.prodi_id', $prodiId)
            ->whereNotNull('data_akademik.tahun_lulus')
            ->where('data_akademik.tahun_lulus', '!=', '')
            ->distinct()
            ->orderByDesc('data_akademik.tahun_lulus')
            ->pluck('data_akademik.tahun_lulus')
            ->map(static fn ($year): string => (string) $year)
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique(array_merge([self::ALL_YEARS], $years)));
    }

    /**
     * Menyusun payload lengkap untuk halaman analitik program studi.
     *
     * @param  int|null  $prodiId  ID program studi yang dipilih.
     * @param  int|string|null  $sectionKey  ID bagian pertanyaan yang dipilih.
     * @param  string|null  $year  Filter tahun kelulusan atau ALL.
     * @return array<string, mixed>
     */
    public function buildProdiChart(?int $prodiId = null, $sectionKey = null, ?string $year = null): array
    {
        $availableProdis = $this->getAvailableProdis();
        $selectedProdi = $this->resolveProdi($prodiId, $availableProdis);

        if (! $selectedProdi) {
            return $this->emptyPayload($availableProdis);
        }

        $availableYears = $this->getAvailableYears((int) $selectedProdi->id);
        $selectedYear = $year && in_array($year, $availableYears, true)
            ? $year
            : $this->defaultYear($availableYears);
        $sections = ProdiQuestionSection::query()
            ->where('prodi_id', $selectedProdi->id)
            ->withCount('questions')
            ->orderBy('order')
            ->get();

        if ($sections->isEmpty()) {
            return $this->emptyPayload($availableProdis, $selectedProdi, $selectedYear, $availableYears);
        }

        $selectedSection = $this->resolveSection($sections, $sectionKey);
        $questions = ProdiQuestion::query()
            ->where('prodi_question_section_id', $selectedSection->id)
            ->with(['options' => static fn ($query) => $query->orderBy('order')])
            ->orderBy('order')
            ->get();
        $responses = $this->getResponses((int) $selectedProdi->id, $questions, $selectedYear);
        $totalRespondents = $responses->pluck('biodata_id')->unique()->count();
        $totalPopulation = $this->getPopulationCount((int) $selectedProdi->id, $selectedYear);

        return [
            'has_data' => true,
            'prodi' => $this->prodiPayload($selectedProdi),
            'filters' => [
                'available_prodis' => $availableProdis->values(),
                'selected_prodi_id' => (int) $selectedProdi->id,
                'available_years' => $availableYears,
                'selected_year' => $selectedYear,
                'sections' => $sections->map(static fn (ProdiQuestionSection $section): array => [
                    'id' => (int) $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'order' => (int) $section->order,
                    'question_count' => (int) $section->questions_count,
                ])->values(),
                'selected_section_id' => (int) $selectedSection->id,
            ],
            'chart' => $this->formatSectionChart(
                $selectedSection,
                $questions,
                $responses,
                $totalRespondents,
                $totalPopulation,
                $selectedProdi,
            ),
        ];
    }

    /**
     * Menentukan program studi default atau program studi dari filter.
     *
     * @param  Collection<int, array{id: int, kode_prodi: string|null, nama_prodi: string, has_questions: bool}>  $availableProdis
     */
    private function resolveProdi(?int $prodiId, Collection $availableProdis): ?Prodi
    {
        $selected = $prodiId
            ? $availableProdis->firstWhere('id', $prodiId)
            : ($availableProdis->firstWhere('kode_prodi', '72')
                ?? $availableProdis->firstWhere('has_questions', true)
                ?? $availableProdis->first());

        return $selected ? Prodi::find($selected['id']) : null;
    }

    /**
     * Menentukan bagian pertanyaan dari filter atau bagian default.
     *
     * @param  Collection<int, ProdiQuestionSection>  $sections
     */
    private function resolveSection(Collection $sections, int|string|null $sectionKey): ProdiQuestionSection
    {
        $section = $sectionKey ? $sections->firstWhere('id', (int) $sectionKey) : null;

        return $section ?? $sections->firstWhere('order', 2) ?? $sections->first();
    }

    /**
     * Mengambil jawaban hanya untuk pertanyaan pada bagian aktif.
     *
     * @param  Collection<int, ProdiQuestion>  $questions
     * @return Collection<int, object>
     */
    private function getResponses(int $prodiId, Collection $questions, string $year): Collection
    {
        if ($questions->isEmpty()) {
            return collect();
        }

        $query = DB::table('prodi_response')
            ->join('biodata', 'biodata.id', '=', 'prodi_response.biodata_id')
            ->leftJoin('data_akademik', 'data_akademik.nim', '=', 'biodata.nim')
            ->leftJoin('users', 'users.id', '=', 'biodata.user_id')
            ->where('biodata.prodi_id', $prodiId)
            ->whereIn('prodi_response.prodi_question_id', $questions->pluck('id'))
            ->select([
                'prodi_response.id as response_id',
                'prodi_response.biodata_id',
                'prodi_response.prodi_question_id',
                'prodi_response.answer_text',
                'prodi_response.answer_json',
                'prodi_response.created_at as answered_at',
                'biodata.nim',
                'users.name as nama_user',
                'data_akademik.nama as nama_akademik',
                'data_akademik.tahun_lulus',
            ]);

        if ($year !== self::ALL_YEARS) {
            $query->where('data_akademik.tahun_lulus', $year);
        }

        return $query->get();
    }

    /**
     * Menghitung populasi alumni pada program studi dan tahun yang dipilih.
     */
    private function getPopulationCount(int $prodiId, string $year): int
    {
        $query = DB::table('biodata')
            ->leftJoin('data_akademik', 'data_akademik.nim', '=', 'biodata.nim')
            ->where('biodata.prodi_id', $prodiId);

        if ($year !== self::ALL_YEARS) {
            $query->where('data_akademik.tahun_lulus', $year);
        }

        return (int) $query->count();
    }

    /**
     * Memilih tahun berjalan bila tersedia dan tetap menyediakan ALL sebagai fallback.
     *
     * @param  array<int, string>  $availableYears
     */
    private function defaultYear(array $availableYears): string
    {
        $currentYear = (string) now()->year;

        return in_array($currentYear, $availableYears, true) ? $currentYear : self::ALL_YEARS;
    }

    /**
     * Memformat satu bagian menjadi visualisasi, pivot table, ringkasan, dan data detail.
     *
     * @param  Collection<int, ProdiQuestion>  $questions
     * @param  Collection<int, object>  $responses
     */
    private function formatSectionChart(
        ProdiQuestionSection $section,
        Collection $questions,
        Collection $responses,
        int $totalRespondents,
        int $totalPopulation,
        Prodi $prodi,
    ): array {
        $items = [];
        $records = [];

        foreach ($questions as $question) {
            $questionResponses = $responses->where('prodi_question_id', $question->id)->values();
            $item = match ($question->type) {
                'rating_5' => $this->formatLikertQuestion($question, $questionResponses),
                'text', 'textarea' => $this->formatTextQuestion($question, $questionResponses),
                default => $this->formatChoiceQuestion($question, $questionResponses),
            };
            $items[] = $item;
            $records = array_merge($records, $item['records']);
        }

        $types = $questions->pluck('type')->unique()->values();
        $chartType = $types->every(static fn (string $type): bool => $type === 'rating_5')
            ? 'likert'
            : ($types->every(static fn (string $type): bool => in_array($type, ['text', 'textarea'], true)) ? 'text' : 'choice');
        $responseRate = $totalPopulation > 0 ? round(($totalRespondents / $totalPopulation) * 100, 1) : 0;
        $validScores = collect($items)->flatMap(static fn (array $item): array => $item['scores'] ?? [])->values()->all();
        $questionStats = collect($items)->map(static fn (array $item): array => $item['stat'])->values();
        $highest = $questionStats->filter(static fn (array $stat): bool => ($stat['valid_count'] ?? 0) > 0)->sortByDesc('average')->first();
        $lowest = $questionStats->filter(static fn (array $stat): bool => ($stat['valid_count'] ?? 0) > 0)->sortBy('average')->first();

        $summary = [
            'total_respondents' => $totalRespondents,
            'population_count' => $totalPopulation,
            'response_rate' => $responseRate,
            'total_questions' => $questions->count(),
            'total_aspects' => $questions->count(),
            'valid_answers' => $questionStats->sum('valid_count'),
            'average_score' => $validScores !== [] ? round(array_sum($validScores) / count($validScores), 2) : 0,
            'median_score' => $this->median($validScores),
            'highest_aspect' => $highest['question_text'] ?? '-',
            'highest_score' => $highest['average'] ?? 0,
            'lowest_aspect' => $lowest['question_text'] ?? '-',
            'lowest_score' => $lowest['average'] ?? 0,
            'total_feedbacks' => $questionStats->sum('valid_count'),
            'total_options' => $questionStats->sum(static fn (array $stat): int => count($stat['categories'] ?? [])),
        ];

        return [
            'type' => $chartType,
            'title' => $section->title,
            'subtitle' => $section->description ?: $this->defaultSubtitle($chartType, $prodi),
            'caption' => $this->buildCaption($chartType, $section, $questions->count()),
            'section_id' => (int) $section->id,
            'methodology' => [
                'valid_answers' => 'Jawaban kosong dan nilai di luar skala atau opsi tidak masuk perhitungan.',
                'likert' => 'Rata-rata adalah jumlah skor valid dibagi jumlah jawaban valid; median adalah nilai tengah skor yang sudah diurutkan.',
                'choice' => 'Jumlah dan persentase dihitung per pilihan; jawaban ganda dapat menyumbang lebih dari satu pilihan.',
                'text' => 'Jawaban terbuka ditampilkan apa adanya pada tab Data dan tidak dipaksakan menjadi angka.',
            ],
            'summary' => $summary,
            'insights' => $this->buildInsights($section, $prodi, $chartType, $summary, $highest, $lowest),
            'series' => $this->legacySeries($items, $chartType),
            'visualizations' => $items,
            'pivot' => $questionStats->all(),
            'questions' => $questions->map(static fn (ProdiQuestion $question): array => [
                'id' => (int) $question->id,
                'code' => $question->code,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'is_required' => (bool) $question->is_required,
                'options' => $question->options->map(static fn ($option): array => [
                    'id' => (int) $option->id,
                    'code' => $option->code,
                    'label' => $option->option_text,
                ])->values(),
            ])->values(),
            'records' => $records,
        ];
    }

    /**
     * Membuat statistik dan grafik standar untuk pertanyaan Likert.
     *
     * @param  Collection<int, object>  $responses
     * @return array<string, mixed>
     */
    private function formatLikertQuestion(ProdiQuestion $question, Collection $responses): array
    {
        $scores = [];
        $distribution = array_fill_keys([1, 2, 3, 4, 5], 0);
        $records = [];

        foreach ($responses as $response) {
            $raw = $this->rawAnswer($response);
            $score = $this->extractLikertScore($raw);
            $records[] = $this->recordPayload($response, $question, $score > 0 ? $this->likertLabel($score) : $raw, $score > 0 ? $score : null);
            if ($score < 1 || $score > 5) {
                continue;
            }
            $scores[] = $score;
            $distribution[$score]++;
        }

        $validCount = count($scores);
        $average = $validCount > 0 ? round(array_sum($scores) / $validCount, 2) : 0;
        $categories = collect($distribution)->map(static function (int $count, int $score) use ($validCount): array {
            return [
                'label' => $score.' - '.match ($score) {
                    1 => 'Sangat Tidak Setuju',
                    2 => 'Tidak Setuju',
                    3 => 'Netral',
                    4 => 'Setuju',
                    5 => 'Sangat Setuju',
                },
                'value' => $score,
                'count' => $count,
                'percentage' => $validCount > 0 ? round(($count / $validCount) * 100, 1) : 0,
            ];
        })->values()->all();
        $stat = $this->questionStat($question, $validCount, $average, $categories);
        $stat['median'] = $this->median($scores);
        $stat['distribution'] = $distribution;
        $stat['satisfaction_rate'] = $validCount > 0 ? round((($distribution[4] + $distribution[5]) / $validCount) * 100, 1) : 0;

        return [
            'key' => $question->code ?: 'question-'.$question->id,
            'question_id' => (int) $question->id,
            'type' => 'likert',
            'title' => $question->question_text,
            'subtitle' => 'Rata-rata dan median skor dari jawaban valid pada skala 1 sampai 5.',
            'caption' => 'Diagram batang horizontal dipakai agar teks pernyataan panjang tetap terbaca dan perbandingan skor antarbutir mudah dilakukan.',
            'categories' => [$question->question_text],
            'values' => [$average],
            'scores' => $scores,
            'stat' => $stat,
            'records' => $records,
        ];
    }

    /**
     * Membuat statistik dan grafik standar untuk pilihan tunggal atau ganda.
     *
     * @param  Collection<int, object>  $responses
     * @return array<string, mixed>
     */
    private function formatChoiceQuestion(ProdiQuestion $question, Collection $responses): array
    {
        $optionLabels = $question->options->pluck('option_text', 'id')->all();
        $orderedLabels = array_values($optionLabels);
        $counts = array_fill_keys($orderedLabels, 0);
        $records = [];
        $validCount = 0;

        foreach ($responses as $response) {
            $labels = $this->answerLabels($response, $optionLabels);
            if ($labels === []) {
                continue;
            }
            $validCount++;
            foreach ($labels as $label) {
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
            $records[] = $this->recordPayload($response, $question, implode(', ', $labels));
        }

        $categories = collect($counts)->map(static fn (int $count, string $label): array => [
            'label' => $label,
            'value' => $count,
            'count' => $count,
            'percentage' => $validCount > 0 ? round(($count / $validCount) * 100, 1) : 0,
        ])->values()->all();
        $top = collect($categories)->sortByDesc('count')->first();
        $stat = $this->questionStat($question, $validCount, null, $categories);
        $stat['top_choice'] = $top['label'] ?? '-';
        $stat['top_count'] = $top['count'] ?? 0;

        return [
            'key' => $question->code ?: 'question-'.$question->id,
            'question_id' => (int) $question->id,
            'type' => 'choice',
            'title' => $question->question_text,
            'subtitle' => $question->type === 'multiple_choice' ? 'Satu alumni dapat tercatat pada lebih dari satu pilihan.' : 'Distribusi pilihan alumni yang mengisi pertanyaan ini.',
            'caption' => 'Diagram batang horizontal dipakai untuk membandingkan jumlah jawaban tanpa memotong label pilihan.',
            'categories' => array_column($categories, 'label'),
            'values' => array_column($categories, 'count'),
            'scores' => [],
            'stat' => $stat,
            'records' => $records,
        ];
    }

    /**
     * Membuat daftar jawaban terbuka untuk satu pertanyaan.
     *
     * @param  Collection<int, object>  $responses
     * @return array<string, mixed>
     */
    private function formatTextQuestion(ProdiQuestion $question, Collection $responses): array
    {
        $records = [];
        foreach ($responses as $response) {
            $raw = trim($this->rawAnswer($response));
            if ($raw !== '') {
                $records[] = $this->recordPayload($response, $question, $raw);
            }
        }

        return [
            'key' => $question->code ?: 'question-'.$question->id,
            'question_id' => (int) $question->id,
            'type' => 'text',
            'title' => $question->question_text,
            'subtitle' => 'Jawaban terbuka ditampilkan di tab Data agar konteks masukan alumni tetap utuh.',
            'caption' => 'Jawaban terbuka tidak dipaksakan menjadi angka; daftar respons menjadi bentuk visualisasi yang paling tepat.',
            'categories' => [],
            'values' => [],
            'scores' => [],
            'stat' => $this->questionStat($question, count($records), null, []),
            'records' => $records,
        ];
    }

    /**
     * Menghasilkan statistik pertanyaan dalam format pivot yang konsisten.
     *
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<string, mixed>
     */
    private function questionStat(ProdiQuestion $question, int $validCount, ?float $average, array $categories): array
    {
        return [
            'question_id' => (int) $question->id,
            'code' => $question->code ?: 'Q'.$question->id,
            'question_text' => $question->question_text,
            'type' => $question->type,
            'valid_count' => $validCount,
            'average' => $average,
            'median' => null,
            'categories' => $categories,
        ];
    }

    /**
     * Mengubah jawaban database menjadi label pilihan yang dapat dihitung.
     *
     * @param  array<int|string, string>  $optionLabels
     * @return array<int, string>
     */
    private function answerLabels(object $response, array $optionLabels): array
    {
        $orderedLabels = array_values($optionLabels);
        $labels = [];
        foreach ($this->answerValues($response) as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $order = filter_var($value, FILTER_VALIDATE_INT);
            if ($order !== false && $order >= 1 && isset($orderedLabels[$order - 1])) {
                $labels[] = $orderedLabels[$order - 1];

                continue;
            }
            $matched = collect($optionLabels)->first(static fn (string $label): bool => mb_strtolower(trim($label)) === mb_strtolower($value));
            $labels[] = $matched ?: $value;
        }

        return array_values(array_unique($labels));
    }

    /**
     * Membaca answer_json atau answer_text tanpa mengubah format sumber data.
     *
     * @return array<int, mixed>
     */
    private function answerValues(object $response): array
    {
        $json = $response->answer_json;
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return array_values($decoded);
            }
        }
        if (is_array($json)) {
            return array_values($json);
        }

        $raw = trim((string) $response->answer_text);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values($decoded);
        }

        return preg_split('/\s*(?:,|;|\|)\s*/', $raw) ?: [$raw];
    }

    /**
     * Mengambil teks jawaban untuk record dan jawaban terbuka.
     */
    private function rawAnswer(object $response): string
    {
        if (trim((string) $response->answer_text) !== '') {
            return trim((string) $response->answer_text);
        }

        return implode(', ', array_map(static fn ($value): string => (string) $value, $this->answerValues($response)));
    }

    /**
     * Membuat baris data responden yang dipakai grafik dan tabel.
     *
     * @return array<string, mixed>
     */
    private function recordPayload(object $response, ProdiQuestion $question, string $answer, ?int $score = null): array
    {
        return [
            'response_id' => (int) $response->response_id,
            'biodata_id' => (int) $response->biodata_id,
            'nim' => $response->nim ?: '-',
            'nama' => $response->nama_akademik ?: ($response->nama_user ?: 'Alumni'),
            'tahun_lulus' => $response->tahun_lulus ?: '-',
            'aspek' => $question->question_text,
            'kode' => $question->code ?: 'Q'.$question->id,
            'type' => $question->type,
            'skor' => $score,
            'jawaban_label' => $answer,
            'tanggal_isi' => $response->answered_at ? substr((string) $response->answered_at, 0, 10) : '-',
        ];
    }

    /**
     * Membuat narasi analisis tanpa menyimpulkan kategori di luar data.
     *
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $highest
     * @param  array<string, mixed>|null  $lowest
     * @return array{headline: string, points: array<int, string>}
     */
    private function buildInsights(
        ProdiQuestionSection $section,
        Prodi $prodi,
        string $chartType,
        array $summary,
        ?array $highest,
        ?array $lowest,
    ): array {
        $headline = match ($chartType) {
            'likert' => "Evaluasi {$section->title} pada Program Studi {$prodi->nama_prodi} memiliki rata-rata {$summary['average_score']} dari skala 5,00.",
            'text' => "Terdapat {$summary['valid_answers']} jawaban terbuka yang dapat ditinjau pada bagian {$section->title}.",
            default => "Distribusi jawaban untuk {$section->title} ditampilkan per pertanyaan agar setiap pilihan dapat dibandingkan secara langsung.",
        };
        $points = ["{$summary['total_respondents']} alumni memberikan jawaban dari populasi {$summary['population_count']} alumni pada filter aktif."];
        if ($chartType === 'likert' && $highest && $lowest) {
            $points[] = "Rata-rata tertinggi terdapat pada \"{$highest['question_text']}\" sebesar {$highest['average']} dengan {$highest['valid_count']} jawaban valid.";
            if ($highest['question_id'] !== $lowest['question_id']) {
                $points[] = "Rata-rata terendah terdapat pada \"{$lowest['question_text']}\" sebesar {$lowest['average']} dan perlu dibaca bersama distribusi skornya.";
            }
        }

        return ['headline' => $headline, 'points' => $points];
    }

    /**
     * Menyediakan seri lama agar konsumen frontend lama tetap kompatibel.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function legacySeries(array $items, string $chartType): array
    {
        $first = $items[0] ?? [];
        if ($chartType === 'likert') {
            return [
                'categories' => array_map(static fn (array $item): string => $item['title'], $items),
                'full_categories' => array_map(static fn (array $item): string => $item['title'], $items),
                'averages' => array_map(static fn (array $item): float => (float) ($item['stat']['average'] ?? 0), $items),
                'aspect_details' => array_map(static fn (array $item): array => $item['stat'], $items),
            ];
        }

        return [
            'categories' => $first['categories'] ?? [],
            'counts' => $first['values'] ?? [],
            'percentages' => array_map(static fn (array $category): float => (float) ($category['percentage'] ?? 0), $first['stat']['categories'] ?? []),
            'items' => $items,
        ];
    }

    /**
     * Menentukan caption standar per tipe visualisasi.
     */
    private function buildCaption(string $chartType, ProdiQuestionSection $section, int $questionCount): string
    {
        return match ($chartType) {
            'likert' => "Diagram batang horizontal memperlihatkan rata-rata skor {$questionCount} pernyataan pada bagian {$section->title}; tab Pivot menampilkan distribusi 1-5 dan median.",
            'choice' => "Diagram batang horizontal memperlihatkan jumlah pilihan pada setiap pertanyaan di bagian {$section->title}; urutan pilihan mengikuti instrumen.",
            default => "Daftar jawaban terbuka pada bagian {$section->title} ditampilkan tanpa mengubah isi masukan alumni.",
        };
    }

    /**
     * Menentukan subtitle fallback saat bagian tidak memiliki deskripsi.
     */
    private function defaultSubtitle(string $chartType, Prodi $prodi): string
    {
        return match ($chartType) {
            'likert' => "Evaluasi {$prodi->nama_prodi} menggunakan skala Likert 1 sampai 5.",
            'choice' => "Distribusi pilihan alumni {$prodi->nama_prodi}.",
            default => "Masukan tertulis alumni {$prodi->nama_prodi}.",
        };
    }

    /**
     * Mengubah skor menjadi label yang konsisten dengan instrumen.
     */
    private function likertLabel(int $score): string
    {
        return $score.' - '.match ($score) {
            1 => 'Sangat Tidak Setuju',
            2 => 'Tidak Setuju',
            3 => 'Netral',
            4 => 'Setuju',
            5 => 'Sangat Setuju',
        };
    }

    /**
     * Mengekstrak angka skala 1-5 dari jawaban teks.
     */
    private function extractLikertScore(string $text): int
    {
        if (preg_match('/^([1-5])(?:\s|=|$)/', trim($text), $matches)) {
            return (int) $matches[1];
        }

        return is_numeric($text) && (int) $text >= 1 && (int) $text <= 5 ? (int) $text : 0;
    }

    /**
     * Menghitung median dari kumpulan skor numerik.
     *
     * @param  array<int, int|float>  $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0;
        }
        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? round(($values[$middle - 1] + $values[$middle]) / 2, 2)
            : (float) $values[$middle];
    }

    /**
     * Membuat payload prodi yang aman dikirim ke Inertia.
     *
     * @return array{id: int, nama_prodi: string, kode_prodi: string|null}
     */
    private function prodiPayload(Prodi $prodi): array
    {
        return [
            'id' => (int) $prodi->id,
            'nama_prodi' => $prodi->nama_prodi,
            'kode_prodi' => $prodi->kode_prodi,
        ];
    }

    /**
     * Membuat payload kosong saat belum ada program studi atau instrumen.
     *
     * @param  Collection<int, array<string, mixed>>  $availableProdis
     * @param  array<int, string>  $availableYears
     * @return array<string, mixed>
     */
    private function emptyPayload(
        Collection $availableProdis,
        ?Prodi $prodi = null,
        string $selectedYear = self::ALL_YEARS,
        array $availableYears = [self::ALL_YEARS],
    ): array {
        return [
            'has_data' => false,
            'message' => 'Instrumen pertanyaan program studi belum tersedia.',
            'prodi' => $prodi ? $this->prodiPayload($prodi) : [],
            'filters' => [
                'available_prodis' => $availableProdis->values(),
                'selected_prodi_id' => $prodi?->id,
                'available_years' => $availableYears,
                'selected_year' => $selectedYear,
                'sections' => [],
                'selected_section_id' => null,
            ],
            'chart' => [
                'type' => 'empty',
                'title' => 'Analitik Program Studi',
                'subtitle' => 'Belum ada instrumen yang dapat divisualisasikan.',
                'summary' => [],
                'insights' => ['headline' => '', 'points' => []],
                'series' => [],
                'visualizations' => [],
                'pivot' => [],
                'questions' => [],
                'records' => [],
            ],
        ];
    }
}
