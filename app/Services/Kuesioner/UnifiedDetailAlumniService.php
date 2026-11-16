<?php

namespace App\Services\Kuesioner;

use App\Models\Biodata;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use Illuminate\Support\Collection;

/**
 * UnifiedDetailAlumniService
 *
 * Menyediakan agregasi section dan respon kuesioner terpadu untuk tampilan
 * detail alumni pada seluruh panel admin (Biro III, Fakultas, Prodi, Superadmin).
 */
class UnifiedDetailAlumniService
{
    /**
     * Menyusun data section dan jawaban kuesioner tingkat universitas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getUnivSectionsWithAnswers(Biodata $alumni): array
    {
        $sections = KuesionerSection::where('target', 'univ')
            ->where('is_active', true)
            ->with(['pertanyaan' => function ($query) {
                $query->where('kuesioner_pertanyaan.is_active', true)
                    ->with('opsi');
            }])
            ->orderBy('order', 'asc')
            ->get();

        $savedResponses = KuesionerJawaban::where('biodata_id', $alumni->id)
            ->get()
            ->keyBy('pertanyaan_id');

        $profileCodesUpper = array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES);
        $sectionsWithAnswers = [];

        foreach ($sections as $section) {
            $subpertanyaansList = [];

            foreach ($section->pertanyaan as $pertanyaan) {
                $codeUpper = strtoupper(trim((string) $pertanyaan->kode_pertanyaan));
                $pivotTampilDi = strtolower(trim((string) ($pertanyaan->pivot->tampil_di ?? '')));

                // Saring pertanyaan yang dikelola di modul profil
                if (in_array($codeUpper, $profileCodesUpper, true) || in_array($pivotTampilDi, ['profil', 'profile'], true)) {
                    continue;
                }

                $resp = $savedResponses->get($pertanyaan->id);
                $isMandatory = (bool) ($pertanyaan->pivot->wajib ?? true);
                $isHeader = in_array($pertanyaan->tipe, ['header', 'section_header'], true);

                $hasAnswer = false;
                $displayAnswer = null;

                if ($resp && ! $isHeader) {
                    if (! empty($resp->jawaban_teks) && trim((string) $resp->jawaban_teks) !== '') {
                        $hasAnswer = true;
                        $displayAnswer = (string) $resp->jawaban_teks;
                    } elseif (is_array($resp->jawaban_json) && count($resp->jawaban_json) > 0) {
                        $hasAnswer = true;
                        $displayAnswer = implode(', ', $resp->jawaban_json);
                    }
                }

                $subpertanyaansList[] = [
                    'id' => $pertanyaan->id,
                    'kode_pertanyaan' => $pertanyaan->kode_pertanyaan,
                    'subpertanyaan' => $pertanyaan->teks_pertanyaan,
                    'type' => $pertanyaan->tipe,
                    'kelompok' => $section->kode_section,
                    'is_mandatory' => $isMandatory,
                    'is_header' => $isHeader,
                    'is_answered' => $hasAnswer,
                    'answer' => $displayAnswer,
                    'answer_json' => $resp?->jawaban_json,
                    'detils' => $pertanyaan->opsi->map(fn ($o) => [
                        'id' => $o->id,
                        'option_text' => $o->label,
                        'kode_opsi' => $o->kode_opsi,
                        'jump_to' => $o->lompat_ke_pertanyaan_id,
                    ]),
                ];
            }

            if (empty($subpertanyaansList)) {
                continue;
            }

            $unansweredMandatoryCount = count(array_filter($subpertanyaansList, fn ($item) => $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header']));
            $unansweredOptionalCount = count(array_filter($subpertanyaansList, fn ($item) => ! $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header']));
            $answeredCount = count(array_filter($subpertanyaansList, fn ($item) => $item['is_answered'] && ! $item['is_header']));
            $totalQuestionsCount = count(array_filter($subpertanyaansList, fn ($item) => ! $item['is_header']));

            $sectionsWithAnswers[] = [
                'id' => $section->id,
                'section' => $section->kode_section,
                'title' => $section->judul ?: ('Bagian '.$section->order),
                'order' => $section->order,
                'subpertanyaans' => $subpertanyaansList,
                'unanswered_mandatory_count' => $unansweredMandatoryCount,
                'unanswered_optional_count' => $unansweredOptionalCount,
                'answered_count' => $answeredCount,
                'total_questions_count' => $totalQuestionsCount,
            ];
        }

        return $sectionsWithAnswers;
    }

    /**
     * Menyusun data section dan jawaban kuesioner program studi.
     *
     * @return array{sections: array<int, array<string, mixed>>, evaluasi: array<string, mixed>}
     */
    public static function getProdiSectionsWithAnswers(Biodata $alumni): array
    {
        $defaultResult = [
            'sections' => [],
            'evaluasi' => [
                'is_complete' => false,
                'percentage' => 0,
                'answered_count' => 0,
                'total_questions' => 0,
            ],
        ];

        if (! $alumni->prodi_id) {
            return $defaultResult;
        }

        $prodiSections = KuesionerSection::where('target', 'prodi')
            ->where('prodi_id', $alumni->prodi_id)
            ->where('is_active', true)
            ->with(['pertanyaan' => function ($query) {
                $query->where('kuesioner_pertanyaan.is_active', true)
                    ->with('opsi');
            }])
            ->orderBy('order', 'asc')
            ->get();

        $savedResponses = KuesionerJawaban::where('biodata_id', $alumni->id)
            ->get()
            ->keyBy('pertanyaan_id');

        $prodiSectionsWithAnswers = [];
        $totalProdiQuestions = 0;
        $totalProdiAnswered = 0;

        foreach ($prodiSections as $pSection) {
            $pQuestionsList = [];

            foreach ($pSection->pertanyaan as $pQuestion) {
                $isHeader = in_array($pQuestion->tipe, ['header', 'section_header'], true);
                $pResp = $savedResponses->get($pQuestion->id);

                $hasAnswer = false;
                $displayAnswer = null;

                if ($pResp && ! $isHeader) {
                    if (! empty($pResp->jawaban_teks) && trim((string) $pResp->jawaban_teks) !== '') {
                        $hasAnswer = true;
                        $displayAnswer = (string) $pResp->jawaban_teks;
                    } elseif (is_array($pResp->jawaban_json) && count($pResp->jawaban_json) > 0) {
                        $hasAnswer = true;
                        $displayAnswer = implode(', ', $pResp->jawaban_json);
                    }
                }

                if (! $isHeader) {
                    $totalProdiQuestions++;
                    if ($hasAnswer) {
                        $totalProdiAnswered++;
                    }
                }

                $pQuestionsList[] = [
                    'id' => $pQuestion->id,
                    'code' => $pQuestion->kode_pertanyaan,
                    'question_text' => $pQuestion->teks_pertanyaan,
                    'type' => $pQuestion->tipe,
                    'is_header' => $isHeader,
                    'is_mandatory' => (bool) ($pQuestion->pivot->wajib ?? true),
                    'is_answered' => $hasAnswer,
                    'answer_text' => $displayAnswer,
                    'options' => $pQuestion->opsi->map(fn ($opt) => [
                        'id' => $opt->id,
                        'text' => $opt->label,
                        'jump_to' => $opt->lompat_ke_pertanyaan_id,
                    ]),
                ];
            }

            $unansweredProdiMandatory = count(array_filter($pQuestionsList, fn ($item) => $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header']));
            $unansweredProdiOptional = count(array_filter($pQuestionsList, fn ($item) => ! $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header']));
            $answeredProdiCount = count(array_filter($pQuestionsList, fn ($item) => $item['is_answered'] && ! $item['is_header']));
            $totalProdiCountInSec = count(array_filter($pQuestionsList, fn ($item) => ! $item['is_header']));

            $prodiSectionsWithAnswers[] = [
                'id' => $pSection->id,
                'title' => $pSection->judul,
                'description' => $pSection->deskripsi,
                'order' => $pSection->order,
                'questions' => $pQuestionsList,
                'unanswered_mandatory_count' => $unansweredProdiMandatory,
                'unanswered_optional_count' => $unansweredProdiOptional,
                'answered_count' => $answeredProdiCount,
                'total_questions_count' => $totalProdiCountInSec,
            ];
        }

        $prodiPercentage = $totalProdiQuestions > 0
            ? (int) round(($totalProdiAnswered / $totalProdiQuestions) * 100)
            : 0;

        return [
            'sections' => $prodiSectionsWithAnswers,
            'evaluasi' => [
                'is_complete' => ($totalProdiQuestions > 0 && $totalProdiAnswered >= $totalProdiQuestions),
                'percentage' => $prodiPercentage,
                'answered_count' => $totalProdiAnswered,
                'total_questions' => $totalProdiQuestions,
            ],
        ];
    }

    /**
     * Mengambil master butir instrumen evaluasi atasan pengguna lulusan.
     */
    public static function getPertanyaanEvaluasiAtasan(): Collection
    {
        return KuesionerPertanyaan::query()
            ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
            ->join('kuesioner_section as s', 's.id', '=', 'sp.section_id')
            ->where('s.target', 'atasan')
            ->where('kuesioner_pertanyaan.is_active', true)
            ->select('kuesioner_pertanyaan.*', 'sp.order as pivot_order')
            ->orderBy('sp.order')
            ->get();
    }
}
