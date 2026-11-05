<?php

namespace App\Services\Analytics;

use App\Models\KuesionerSection;
use App\Models\Prodi;
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
        $prodisWithQuestions = KuesionerSection::query()
            ->where('target', 'prodi')
            ->whereNotNull('prodi_id')
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
        $sections = KuesionerSection::query()
            ->where('target', 'prodi')
            ->where('prodi_id', $selectedProdi->id)
            ->withCount('pertanyaan as questions_count')
            ->orderBy('order')
            ->get()
            ->map(function ($sec) {
                $sec->title = $sec->judul;
                $sec->description = $sec->deskripsi;

                return $sec;
            });

        if ($sections->isEmpty()) {
            return $this->emptyPayload($availableProdis, $selectedProdi, $selectedYear, $availableYears);
        }

        // ALUR 1: filter section memilih satu bagian kuesioner berdasarkan ID.
        // `order` bagian ini nanti dipakai frontend untuk memilih file
        // categories/01_... sampai categories/09_....
        $selectedSection = $this->resolveSection($sections, $sectionKey);

        // ALUR 2: pertanyaan dan opsi pada section aktif diambil dari metadata
        // database. Karena itu frontend tidak perlu menulis nama pertanyaan.
        $questions = $selectedSection->pertanyaan()
            ->with('opsi')
            ->orderBy('kuesioner_section_pertanyaan.order')
            ->get()
            ->map(function ($q) {
                $q->question_text = $q->teks_pertanyaan;
                $q->code = $q->kode_pertanyaan;
                $q->type = $q->tipe;
                $q->is_required = (bool) ($q->pivot->wajib ?? true);
                $q->options = $q->opsi->map(function ($o) {
                    $o->option_text = $o->label;
                    $o->code = $o->kode_opsi;

                    return $o;
                });

                return $q;
            });
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
                'sections' => $sections->map(static fn ($section): array => [
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
     * @param  Collection<int, object>  $sections
     */
    private function resolveSection(Collection $sections, int|string|null $sectionKey): object
    {
        $section = $sectionKey ? $sections->firstWhere('id', (int) $sectionKey) : null;

        return $section ?? $sections->firstWhere('order', 1) ?? $sections->first();
    }

    /**
     * Mengambil jawaban hanya untuk pertanyaan pada bagian aktif.
     *
     * @param  Collection<int, object>  $questions
     * @return Collection<int, object>
     */
    private function getResponses(int $prodiId, Collection $questions, string $year): Collection
    {
        if ($questions->isEmpty()) {
            return collect();
        }

        $query = DB::table('kuesioner_jawaban')
            ->join('biodata', 'biodata.id', '=', 'kuesioner_jawaban.biodata_id')
            ->leftJoin('data_akademik', 'data_akademik.nim', '=', 'biodata.nim')
            ->leftJoin('users', 'users.id', '=', 'biodata.user_id')
            ->where('kuesioner_jawaban.responden_type', 'alumni')
            ->where('biodata.prodi_id', $prodiId)
            ->whereIn('kuesioner_jawaban.pertanyaan_id', $questions->pluck('id'))
            ->select([
                'kuesioner_jawaban.id as response_id',
                'kuesioner_jawaban.biodata_id',
                'kuesioner_jawaban.pertanyaan_id as prodi_question_id',
                'kuesioner_jawaban.jawaban_teks as answer_text',
                'kuesioner_jawaban.jawaban_json as answer_json',
                'kuesioner_jawaban.created_at as answered_at',
                'biodata.nim',
                'biodata.nomor_telepon',
                'biodata.linkedin_url',
                'biodata.email_pribadi',
                'users.name as nama_user',
                'users.email as email_user',
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
     * @param  Collection<int, object>  $questions
     * @param  Collection<int, object>  $responses
     */
    private function formatSectionChart(
        object $section,
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

            // ALUR 3: `type` pertanyaan menentukan cara menghitung datanya.
            // rating_5 menjadi skor Likert, text menjadi kartu jawaban terbuka,
            // sedangkan tipe lain menjadi distribusi pilihan batang horizontal.
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

        // ALUR 4: service mengirim `type`, `visualizations`, dan metadata
        // pertanyaan. Frontend tidak menerima gambar jadi; frontend membangun
        // ECharts dari arrays ini agar grafik tetap interaktif.
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
            'average_score' => $validScores !== [] ? (int) round(array_sum($validScores) / count($validScores)) : 0,
            'median_score' => (int) round($this->median($validScores)),
            'highest_aspect' => $highest['question_text'] ?? '-',
            'highest_score' => $highest['average'] ?? 0,
            'lowest_aspect' => $lowest['question_text'] ?? '-',
            'lowest_score' => $lowest['average'] ?? 0,
            'total_feedbacks' => $questionStats->sum('valid_count'),
            'total_options' => $questionStats->sum(static fn (array $stat): int => count($stat['categories'] ?? [])),
        ];

        $methodology = [
            'valid_answers' => 'Jawaban kosong dan nilai di luar skala atau opsi tidak masuk perhitungan.',
            'formula' => $chartType === 'likert'
                ? 'Skor tampilan = ROUND(Σ skor jawaban valid / n jawaban valid) | Median tampilan = ROUND(nilai tengah skor valid)'
                : 'Frekuensi pilihan p = n_p | Proporsi pilihan p (%) = (n_p / N jawaban valid per pertanyaan) × 100%',
            'denominator' => $chartType === 'likert'
                ? 'n jawaban valid = jumlah alumni yang memberi skor 1–5 pada setiap butir. Skor yang ditampilkan dibulatkan ke bilangan bulat pada skala 1–5.'
                : 'N jawaban valid = jumlah alumni yang memberi jawaban valid pada pertanyaan aktif. Pada pilihan ganda, satu alumni dapat menambah frekuensi lebih dari satu pilihan.',
            'source' => 'Tabel: prodi_response, prodi_question, prodi_question_option, prodi_question_section, biodata, data_akademik, users, prodi.',
            'tables' => [
                ['table' => 'kuesioner_jawaban', 'columns' => 'biodata_id, pertanyaan_id, jawaban_teks, jawaban_json, created_at', 'role' => 'Jawaban kuesioner prodi per alumni'],
                ['table' => 'kuesioner_pertanyaan', 'columns' => 'id, pemilik_prodi_id, kode_pertanyaan, teks_pertanyaan, tipe_pertanyaan, urutan', 'role' => 'Definisi butir dan tipe perhitungan'],
                ['table' => 'kuesioner_opsi', 'columns' => 'pertanyaan_id, label_opsi, urutan', 'role' => 'Opsi pembentuk distribusi pilihan'],
                ['table' => 'biodata', 'columns' => 'id, nim, prodi_id, nomor_telepon, email_pribadi', 'role' => 'Relasi responden, prodi, dan kontak alumni'],
                ['table' => 'data_akademik', 'columns' => 'nim, nama, tahun_lulus', 'role' => 'Identitas akademik dan filter kohort'],
            ],
            'views' => [],
            'charts' => collect($items)->map(static function (array $item) use ($chartType): array {
                return [
                    'key' => $item['key'],
                    'title' => $item['title'],
                    'formula' => $chartType === 'likert'
                        ? 'Skor butir = ROUND(Σ skor valid / n jawaban valid pada butir).'
                        : 'Frekuensi opsi = jumlah alumni yang memilih opsi pada butir.',
                    'source' => 'kuesioner_jawaban bergabung dengan kuesioner_pertanyaan dan kuesioner_opsi',
                ];
            })->values()->all(),
            'likert' => 'Rata-rata dan median dibulatkan ke bilangan bulat agar skor tetap konsisten pada skala 1-5.',
            'choice' => 'Jumlah dan persentase dihitung per pilihan; jawaban ganda dapat menyumbang lebih dari satu pilihan.',
            'text' => 'Jawaban terbuka ditampilkan apa adanya pada tab Data dan tidak dipaksakan menjadi angka.',
        ];

        return [
            'type' => $chartType,
            'title' => $section->title,
            'subtitle' => $section->description ?: $this->defaultSubtitle($chartType, $prodi),
            'caption' => $this->buildCaption($chartType, $section, $questions->count()),
            'section_id' => (int) $section->id,
            'methodology' => $methodology,
            'metadata' => [
                'percentage_basis' => $chartType === 'likert' ? 'Skala Likert 1–5 per butir.' : 'Jumlah jawaban valid pada pertanyaan aktif.',
                'data_type' => $chartType === 'likert' ? 'Skor Likert dibulatkan (integer)' : ($chartType === 'text' ? 'Jawaban teks' : 'Frekuensi dan proporsi pilihan'),
                'missing_count' => max($totalPopulation - $totalRespondents, 0),
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => $summary,
            'insights' => $this->buildInsights($section, $prodi, $chartType, $summary, $highest, $lowest, $items),
            'series' => $this->legacySeries($items, $chartType),
            'visualizations' => $items,
            'pivot' => $questionStats->all(),
            'questions' => $questions->map(static fn ($question): array => [
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
    private function formatLikertQuestion(object $question, Collection $responses): array
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
        $average = $validCount > 0 ? (int) round(array_sum($scores) / $validCount) : 0;
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
        $stat['median'] = (int) round($this->median($scores));
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
    private function formatChoiceQuestion(object $question, Collection $responses): array
    {
        $optionLabels = $question->options
            ->mapWithKeys(fn ($option): array => [$option->id => $this->canonicalChoiceLabel((string) $option->option_text)])
            ->all();
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
            $record = $this->recordPayload($response, $question, implode(', ', $labels));
            $record['jawaban_lainnya'] = $this->otherAnswerDetail($response);
            $records[] = $record;
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
    private function formatTextQuestion(object $question, Collection $responses): array
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
    private function questionStat(object $question, int $validCount, ?int $average, array $categories): array
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
            if ($this->isOtherChoiceLabel($value)) {
                $labels[] = 'Lainnya';

                continue;
            }
            $matched = collect($optionLabels)->first(static fn (string $label): bool => mb_strtolower(trim($label)) === mb_strtolower($value));
            $labels[] = $matched ?: $this->canonicalChoiceLabel($value);
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
        $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : $json;
        if (is_array($decoded)) {
            $selected = $decoded['selected'] ?? $decoded['pilihan'] ?? $decoded;

            return is_array($selected) ? array_values($selected) : [$selected];
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
    private function recordPayload(object $response, object $question, string $answer, ?int $score = null): array
    {
        return [
            'response_id' => (int) $response->response_id,
            'biodata_id' => (int) $response->biodata_id,
            'nim' => $response->nim ?: '-',
            'nama' => $response->nama_akademik ?: ($response->nama_user ?: 'Alumni'),
            'tahun_lulus' => $response->tahun_lulus ?: '-',
            'nomor_telepon' => $response->nomor_telepon ?: null,
            'linkedin_url' => $response->linkedin_url ?: null,
            'email' => $response->email_pribadi ?: ($response->email_user ?: null),
            'aspek' => $question->question_text,
            'kode' => $question->code ?: 'Q'.$question->id,
            'type' => $question->type,
            'skor' => $score,
            'jawaban_label' => $answer,
            'tanggal_isi' => $response->answered_at ? substr((string) $response->answered_at, 0, 10) : '-',
        ];
    }

    /**
     * Menyatukan nama opsi lain agar label analitik tidak mengikuti variasi bahasa instrumen lama.
     */
    private function canonicalChoiceLabel(string $label): string
    {
        return $this->isOtherChoiceLabel($label) ? 'Lainnya' : trim($label);
    }

    /**
     * Menentukan apakah teks opsi menunjukkan pilihan isian bebas lainnya.
     */
    private function isOtherChoiceLabel(string $label): bool
    {
        $normalized = mb_strtolower(trim($label));

        return $normalized === 'other'
            || str_contains($normalized, 'lainnya')
            || str_contains($normalized, 'tuliskan');
    }

    /**
     * Mengambil isi bebas yang menyertai opsi Lainnya tanpa menjadikannya kategori grafik baru.
     */
    private function otherAnswerDetail(object $response): ?string
    {
        $json = $response->answer_json;
        $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : $json;
        if (is_array($decoded)) {
            $detail = $decoded['custom'] ?? $decoded['input'] ?? null;
            if (is_scalar($detail) && trim((string) $detail) !== '') {
                return trim((string) $detail);
            }
        }

        $answer = trim((string) $response->answer_text);
        if (preg_match('/^(?:other|lainnya(?:\s*\([^)]*\))?)\s*(?::|\()\s*(.+?)\)?$/iu', $answer, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Membuat narasi analisis tanpa menyimpulkan kategori di luar data.
     *
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $highest
     * @param  array<string, mixed>|null  $lowest
     * @param  array<int, array<string, mixed>>  $items
     * @return array{headline: string, points: array<int, string>}
     */
    private function buildInsights(
        object $section,
        Prodi $prodi,
        string $chartType,
        array $summary,
        ?array $highest,
        ?array $lowest,
        array $items,
    ): array {
        $headline = match ($chartType) {
            'likert' => "Evaluasi {$section->title} pada Program Studi {$prodi->nama_prodi} memiliki skor {$summary['average_score']} pada skala 1-5.",
            'text' => "Terdapat {$summary['valid_answers']} jawaban terbuka yang dapat ditinjau pada bagian {$section->title}.",
            default => "Distribusi {$summary['total_questions']} pertanyaan pilihan pada {$section->title} diringkas berdasarkan frekuensi jawaban valid.",
        };
        $points = ["{$summary['total_respondents']} alumni memberikan jawaban dari populasi {$summary['population_count']} alumni pada filter aktif."];
        if ($chartType === 'likert' && $highest && $lowest) {
            $points[] = "Rata-rata tertinggi terdapat pada \"{$highest['question_text']}\" sebesar {$highest['average']} dengan {$highest['valid_count']} jawaban valid.";
            if ($highest['question_id'] !== $lowest['question_id']) {
                $points[] = "Rata-rata terendah terdapat pada \"{$lowest['question_text']}\" sebesar {$lowest['average']} dengan {$lowest['valid_count']} jawaban valid.";
            }
            $points[] = "Rata-rata keseluruhan bagian adalah {$summary['average_score']} dari 5, sedangkan median keseluruhan adalah {$summary['median_score']}.";
        } elseif ($chartType === 'choice') {
            $choiceSummaries = collect($items)
                ->map(function (array $item): ?array {
                    $top = collect($item['stat']['categories'] ?? [])
                        ->filter(static fn (array $category): bool => (int) ($category['count'] ?? 0) > 0)
                        ->sortByDesc('count')
                        ->first();

                    return $top ? [
                        'question' => $item['title'],
                        'label' => $top['label'],
                        'count' => (int) $top['count'],
                        'percentage' => $top['percentage'],
                    ] : null;
                })
                ->filter()
                ->sortByDesc('count')
                ->values();

            if ($choiceSummaries->isNotEmpty()) {
                $top = $choiceSummaries->first();
                $points[] = "Pilihan terbanyak adalah \"{$top['label']}\" pada pertanyaan \"{$top['question']}\" dengan {$top['count']} jawaban ({$top['percentage']}%).";
                $points[] = "Sebanyak {$summary['valid_answers']} jawaban valid membentuk distribusi pilihan; pada pertanyaan ganda, satu alumni dapat menyumbang lebih dari satu pilihan.";
                $points[] = 'Pertanyaan aktif dapat dibandingkan melalui tab masing-masing, sedangkan rincian alumni tersedia pada tabel data pembentuk.';
            }
        } elseif ($chartType === 'text') {
            $points[] = "Sebanyak {$summary['valid_answers']} respons terisi dapat ditelusuri per alumni pada tab Data.";
            $points[] = 'Jawaban terbuka dipertahankan apa adanya agar konteks masukan alumni tidak hilang karena kategorisasi otomatis.';
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
                'averages' => array_map(static fn (array $item): int => (int) ($item['stat']['average'] ?? 0), $items),
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
    private function buildCaption(string $chartType, object $section, int $questionCount): string
    {
        return match ($chartType) {
            'likert' => "Diagram batang horizontal memperlihatkan skor bilangan bulat dari {$questionCount} pernyataan pada bagian {$section->title}; buka tab Data untuk distribusi skala dan median.",
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
