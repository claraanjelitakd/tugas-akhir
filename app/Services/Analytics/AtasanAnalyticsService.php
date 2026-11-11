<?php

namespace App\Services\Analytics;

use App\Models\EvaluasiAtasan;
use App\Models\KuesionerPertanyaan;
use App\Models\Prodi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyusun statistik evaluasi pengguna lulusan dari jawaban atasan
 * menggunakan master bank soal terpadu (KuesionerPertanyaan target 'atasan').
 */
class AtasanAnalyticsService
{
    /**
     * Membangun payload halaman grafik evaluasi atasan.
     *
     * @return array<string, mixed>
     */
    public function build(?string $requestedYear = null, ?int $prodiId = null): array
    {
        $availableYears = $this->availableYears();
        $selectedYear = $this->resolveYear($requestedYear, $availableYears);
        $evaluations = $this->evaluations($selectedYear, $prodiId);
        $submitted = $evaluations->where('is_submitted', true)->values();

        // Ambil master pertanyaan evaluasi atasan dari bank soal terpadu
        $questions = KuesionerPertanyaan::query()
            ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
            ->join('kuesioner_section as s', 's.id', '=', 'sp.section_id')
            ->where('s.target', 'atasan')
            ->where('sp.is_active', true)
            ->where('kuesioner_pertanyaan.is_active', true)
            ->select('kuesioner_pertanyaan.*', 'sp.order as pivot_order')
            ->with('opsi')
            ->orderBy('sp.order')
            ->get()
            ->map(function ($q) {
                $q->kode = $q->kode_pertanyaan;
                $q->label_ringkas = $q->label_pertanyaan ?: Str::limit($q->teks_pertanyaan, 72);
                $q->aspek = $q->teks_pertanyaan;
                $q->deskripsi = $q->keterangan ?? '';
                $q->pilihan_jawaban = $q->opsi->pluck('label')->all();
                $q->is_company_field = in_array($q->kode_pertanyaan, [
                    'EVA_bentuk_perusahaan',
                    'EVA_skala_perusahaan',
                    'EVA_jumlah_pegawai',
                    'EVA_jumlah_alumni_ukdw',
                    'EVA_standar_gaji_pertama',
                    'bentuk_perusahaan',
                    'skala_perusahaan',
                    'jumlah_pegawai',
                    'jumlah_alumni_ukdw',
                    'standar_gaji_pertama',
                ], true);

                return $q;
            })
            ->unique('id')
            ->values();

        $questionStats = $this->questionStats($questions, $submitted);
        $readiness = $questionStats->firstWhere('kode', 'tingkat_kesiapan_kerja');
        $likertStats = $questionStats->filter(static fn (array $stat): bool => $stat['type'] === 'likert_5');
        $allScores = $likertStats->flatMap(static fn (array $stat): array => $stat['scores'])->values()->all();
        $prodiSummary = $this->prodiSummary($submitted, $questions);
        $population = $evaluations->count();
        $submittedCount = $submitted->count();

        return [
            'filters' => [
                'selected_year' => $selectedYear,
                'selected_prodi_id' => $prodiId ? (string) $prodiId : '',
                'available_years' => $availableYears,
                'prodis' => Prodi::query()->orderBy('nama_prodi')->get(['id', 'kode_prodi', 'nama_prodi'])->map(static fn (Prodi $prodi): array => [
                    'id' => $prodi->id,
                    'kode_prodi' => $prodi->kode_prodi,
                    'nama_prodi' => $prodi->nama_prodi,
                ])->values()->all(),
            ],
            'summary' => [
                'population' => $population,
                'submitted' => $submittedCount,
                'pending' => max(0, $population - $submittedCount),
                'response_rate' => $population > 0 ? round(($submittedCount / $population) * 100, 1) : 0,
                'valid_answers' => $likertStats->sum('valid_count'),
                'average_score' => $allScores !== [] ? (int) round(collect($allScores)->avg()) : null,
                'question_count' => $questions->reject->is_company_field->count(),
            ],
            'question_stats' => $questionStats->values()->all(),
            'readiness' => $readiness ?? [
                'kode' => 'tingkat_kesiapan_kerja',
                'aspek' => 'Tingkat Kesiapan Alumni dalam Bekerja',
                'distribution' => [],
                'valid_count' => 0,
            ],
            'prodi_summary' => $prodiSummary,
            'records' => $this->records($submitted, $questions),
            'methodology' => [
                'formula' => 'Rata-rata skor aspek = ROUND(Σ skor jawaban / n jawaban valid). Peringkat pilihan memakai frekuensi jawaban pada pertanyaan aktif.',
                'scale' => 'Nilai pilihan dibaca dari urutan opsi master pertanyaan; opsi pertama bernilai paling tinggi dan opsi terakhir paling rendah.',
                'denominator' => 'Response rate = evaluasi terkirim / seluruh evaluasi atasan pada filter aktif × 100%.',
                'source' => 'evaluasi_atasan, kuesioner_jawaban, kuesioner_pertanyaan, biodata, data_akademik, prodi, perusahaan, atasan.',
            ],
            'has_data' => $population > 0,
        ];
    }

    /**
     * Mengambil tahun lulusan yang benar-benar terhubung ke evaluasi atasan.
     *
     * @return list<string>
     */
    private function availableYears(): array
    {
        return DB::table('evaluasi_atasan')
            ->join('biodata', 'evaluasi_atasan.biodata_id', '=', 'biodata.id')
            ->join('data_akademik', 'biodata.nim', '=', 'data_akademik.nim')
            ->whereNotNull('data_akademik.tahun_lulus')
            ->distinct()
            ->orderByDesc('data_akademik.tahun_lulus')
            ->pluck('data_akademik.tahun_lulus')
            ->map(static fn (mixed $year): string => (string) $year)
            ->values()
            ->prepend('ALL')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Menentukan tahun aktif tanpa mengunci fallback pada tahun tertentu.
     *
     * @param  list<string>  $availableYears
     */
    private function resolveYear(?string $requestedYear, array $availableYears): string
    {
        if ($requestedYear === 'ALL' || $requestedYear === null || $requestedYear === '') {
            return 'ALL';
        }

        return in_array($requestedYear, $availableYears, true) ? $requestedYear : 'ALL';
    }

    /**
     * Mengambil evaluasi beserta relasi yang diperlukan dalam satu payload.
     *
     * @return Collection<int, EvaluasiAtasan>
     */
    private function evaluations(string $year, ?int $prodiId): Collection
    {
        return EvaluasiAtasan::query()
            ->with([
                'biodata.dataAkademik',
                'biodata.prodi',
                'perusahaan',
                'atasan',
                'respons.pertanyaan',
            ])
            ->when($year !== 'ALL', static fn ($query) => $query->whereHas('biodata.dataAkademik', static fn ($academic) => $academic->where('tahun_lulus', (int) $year)))
            ->when($prodiId, static fn ($query) => $query->whereHas('biodata', static fn ($biodata) => $biodata->where('prodi_id', $prodiId)))
            ->latest('updated_at')
            ->get();
    }

    /**
     * Mengubah jawaban setiap butir menjadi distribusi dan skor yang dapat digambar.
     *
     * @param  Collection<int, mixed>  $questions
     * @param  Collection<int, EvaluasiAtasan>  $submitted
     * @return Collection<int, array<string, mixed>>
     */
    private function questionStats(Collection $questions, Collection $submitted): Collection
    {
        return $questions
            ->reject->is_company_field
            ->map(function ($question) use ($submitted): array {
                $answers = $submitted
                    ->flatMap(fn (EvaluasiAtasan $evaluation): Collection => $evaluation->respons
                        ->filter(fn ($response): bool => $response->pertanyaan_id === $question->id)
                        ->map(static fn ($response): string => trim((string) ($response->jawaban_teks ?? ($response->nilai ?? '')))))
                    ->filter()
                    ->values();
                $options = collect($question->pilihan_jawaban ?? [])->map(static fn (mixed $option): string => trim((string) $option))->filter()->values();
                $labels = $options->concat($answers)->filter()->unique()->values();
                $distribution = $labels->map(function (string $label) use ($answers): array {
                    return [
                        'label' => $label,
                        'count' => $answers->filter(static fn (string $answer): bool => $answer === $label)->count(),
                    ];
                })->values()->all();
                $scores = $answers
                    ->map(fn (string $answer): ?int => $this->scoreFor($answer, $options->all()))
                    ->filter(static fn (?int $score): bool => $score !== null)
                    ->map(static fn (int $score): int => $score)
                    ->values()
                    ->all();

                return [
                    'id' => $question->id,
                    'kode' => $question->kode,
                    'label' => $question->label_ringkas,
                    'aspek' => $question->aspek,
                    'deskripsi' => $question->deskripsi,
                    'type' => $question->tipe,
                    'options' => $options->all(),
                    'distribution' => $distribution,
                    'valid_count' => $answers->count(),
                    'scores' => $scores,
                    'average' => $scores !== [] ? (int) round(collect($scores)->avg()) : null,
                    'top_choice' => $this->topChoice($distribution),
                ];
            });
    }

    /**
     * Mengambil label dengan frekuensi jawaban tertinggi.
     *
     * @param  list<array{label:string,count:int}>  $distribution
     */
    private function topChoice(array $distribution): ?string
    {
        $top = collect($distribution)->sortByDesc('count')->firstWhere('count', '>', 0);

        return is_array($top) ? ($top['label'] ?? null) : null;
    }

    /**
     * Mengubah label jawaban menjadi skor berdasarkan opsi master pertanyaan.
     *
     * @param  list<string>  $options
     */
    private function scoreFor(string $answer, array $options): ?int
    {
        if (is_numeric($answer) && (int) $answer >= 1 && (int) $answer <= 5) {
            return (int) $answer;
        }

        $index = array_search($answer, $options, true);
        if ($index === false || $options === []) {
            return null;
        }

        return max(1, count($options) - $index);
    }

    /**
     * Menyusun ringkasan skor per program studi untuk grafik perbandingan.
     *
     * @param  Collection<int, EvaluasiAtasan>  $submitted
     * @param  Collection<int, mixed>  $questions
     * @return list<array<string, mixed>>
     */
    private function prodiSummary(Collection $submitted, Collection $questions): array
    {
        $optionsByQuestion = $questions->keyBy('id')->mapWithKeys(static fn ($question): array => [$question->id => $question->pilihan_jawaban ?? []]);

        return $submitted
            ->groupBy(fn (EvaluasiAtasan $evaluation): string => $evaluation->biodata?->prodi?->nama_prodi ?: 'Tanpa prodi')
            ->map(function (Collection $evaluations, string $prodi) use ($optionsByQuestion): array {
                $scores = $evaluations->flatMap(function (EvaluasiAtasan $evaluation) use ($optionsByQuestion): Collection {
                    return $evaluation->respons
                        ->filter(fn ($response): bool => ($response->pertanyaan?->tipe ?? '') === 'likert_5')
                        ->map(fn ($response): ?int => $this->scoreFor((string) ($response->jawaban_teks ?? ($response->nilai ?? '')), $optionsByQuestion->get($response->pertanyaan_id, [])))
                        ->filter()
                        ->values();
                })->values();

                return [
                    'prodi' => $prodi,
                    'respondents' => $evaluations->count(),
                    'average' => $scores->isNotEmpty() ? (int) round($scores->avg()) : null,
                ];
            })
            ->sortByDesc('average')
            ->values()
            ->all();
    }

    /**
     * Menyusun tabel responden yang menjadi sumber grafik.
     *
     * @param  Collection<int, EvaluasiAtasan>  $submitted
     * @param  Collection<int, mixed>  $questions
     * @return list<array<string, mixed>>
     */
    private function records(Collection $submitted, Collection $questions): array
    {
        $optionsByQuestion = $questions->keyBy('id')->mapWithKeys(static fn ($question): array => [$question->id => $question->pilihan_jawaban ?? []]);

        return $submitted->map(function (EvaluasiAtasan $evaluation) use ($optionsByQuestion): array {
            $scores = $evaluation->respons
                ->filter(fn ($response): bool => ($response->pertanyaan?->tipe ?? '') === 'likert_5')
                ->map(fn ($response): ?int => $this->scoreFor((string) ($response->jawaban_teks ?? ($response->nilai ?? '')), $optionsByQuestion->get($response->pertanyaan_id, [])))
                ->filter()
                ->values();

            return [
                'evaluasi_id' => $evaluation->id,
                'biodata_id' => $evaluation->biodata_id,
                'nama' => $evaluation->biodata?->nama ?: $evaluation->biodata?->dataAkademik?->nama,
                'nim' => $evaluation->biodata?->nim,
                'nama_prodi' => $evaluation->biodata?->prodi?->nama_prodi,
                'linkedin_url' => $evaluation->biodata?->linkedin_url,
                'nama_perusahaan' => $evaluation->perusahaan?->nama_perusahaan,
                'nama_atasan' => $evaluation->atasan?->nama,
                'submitted_at' => $evaluation->submitted_at?->toIso8601String(),
                'answers_count' => $evaluation->respons->count(),
                'average_score' => $scores->isNotEmpty() ? (int) round($scores->avg()) : null,
            ];
        })->values()->all();
    }
}
