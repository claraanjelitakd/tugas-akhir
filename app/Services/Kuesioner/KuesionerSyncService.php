<?php

namespace App\Services\Kuesioner;

use App\Models\Biodata;
use App\Models\ProdiQuestion;
use App\Models\ProdiResponse;
use App\Models\RefSubpertanyaan2021;
use App\Models\RefSubpertanyaanDetil;
use App\Models\Tracer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KuesionerSyncService
 *
 * Fungsi: Menyinkronkan data profil biodata alumni (Data Akademik, Akun User, Perusahaan, dan Atasan)
 * secara otomatis ke tabel `tracer` untuk kelompok instrumen F1 s/d F2H, F5B..F5D, F510, dan BIO_*.
 */
class KuesionerSyncService
{
    /**
     * Ambil pertanyaan tambahan yang ditandai tampil pada halaman profil.
     * Pertanyaan profil bawaan dikeluarkan agar tidak tampil dua kali pada form khusus.
     *
     * @return Collection<int, RefSubpertanyaan2021>
     */
    public static function getDynamicProfileQuestions(): Collection
    {
        $profileCodes = array_merge(
            array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES),
            ['BIO_BIDANG_DIVISI']
        );

        return RefSubpertanyaan2021::query()
            ->whereIn('tampil_di', ['profil', 'profile', 'both'])
            ->whereNotIn(DB::raw('UPPER(kode_pertanyaan)'), $profileCodes)
            ->where('type', '!=', 'header')
            ->with('detils')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Bentuk jawaban tambahan profil yang sudah tersimpan agar dapat dipakai kembali oleh Vue.
     *
     * @param  Collection<int, RefSubpertanyaan2021>  $questions
     * @return array{answers: array<int, string>, custom: array<int, string>}
     */
    public static function getDynamicProfileAnswers(Biodata $biodata, Collection $questions): array
    {
        $questionIds = $questions->modelKeys();
        if ($questionIds === []) {
            return ['answers' => [], 'custom' => []];
        }

        $responses = $biodata->tracer()
            ->whereIn('question_id', $questionIds)
            ->get()
            ->keyBy('question_id');

        $answers = [];
        $custom = [];

        foreach ($responses as $response) {
            $json = is_array($response->answer_json) ? $response->answer_json : [];
            $selected = $json['selected'] ?? $json['pilihan'] ?? null;
            $customText = $json['input'] ?? $json['text'] ?? null;
            $answerText = (string) ($response->answer ?? '');

            if ($selected === null && str_starts_with($answerText, 'Lainnya: ')) {
                $selected = 'Lainnya';
                $customText = trim(substr($answerText, 9));
            }

            $answers[(int) $response->question_id] = (string) ($selected ?? $answerText);
            if ($customText !== null && trim((string) $customText) !== '') {
                $custom[(int) $response->question_id] = trim((string) $customText);
            }
        }

        return ['answers' => $answers, 'custom' => $custom];
    }

    /**
     * Simpan jawaban pertanyaan tambahan profil ke tabel tracer.
     * Hanya pertanyaan yang memang ditandai untuk profil yang boleh diproses.
     *
     * @param  array<int|string, mixed>  $answers
     * @param  array<int|string, mixed>  $customAnswers
     */
    public static function syncDynamicProfileResponses(Biodata $biodata, array $answers, array $customAnswers = []): void
    {
        $questionIds = array_values(array_filter(
            array_map('intval', array_keys($answers)),
            static fn (int $id): bool => $id > 0
        ));

        if ($questionIds === []) {
            return;
        }

        $questions = self::getDynamicProfileQuestions()->whereIn('id', $questionIds)->keyBy('id');

        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);
            if (! $question) {
                continue;
            }

            $rawAnswer = $answers[$questionId] ?? $answers[(string) $questionId] ?? null;
            $customText = $customAnswers[$questionId] ?? $customAnswers[(string) $questionId] ?? null;
            [$answerText, $answerJson] = self::normalizeDynamicProfileAnswer($question, $rawAnswer, $customText);

            if ($answerText === null && $answerJson === null) {
                Tracer::where('biodata_id', $biodata->id)
                    ->where('question_id', $question->id)
                    ->delete();

                continue;
            }

            Tracer::updateOrCreate(
                ['biodata_id' => $biodata->id, 'question_id' => $question->id],
                [
                    'nim' => $biodata->nim,
                    'kelompok' => $question->kelompok,
                    'kode_pertanyaan' => $question->kode_pertanyaan,
                    'subpertanyaan' => $question->subpertanyaan,
                    'answer' => $answerText,
                    'answer_json' => $answerJson,
                    'keterangan' => $question->keterangan,
                    'tahun_lulus' => $biodata->tahun_lulus ?? $biodata->dataAkademik?->tahun_lulus,
                ]
            );
        }
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>|null}
     */
    private static function normalizeDynamicProfileAnswer(RefSubpertanyaan2021 $question, mixed $rawAnswer, mixed $customText): array
    {
        $selected = is_array($rawAnswer)
            ? ($rawAnswer['selected'] ?? $rawAnswer['pilihan'] ?? $rawAnswer['value'] ?? null)
            : $rawAnswer;
        $custom = is_array($rawAnswer)
            ? ($rawAnswer['input'] ?? $rawAnswer['text'] ?? $customText)
            : $customText;
        $selectedText = self::toAnswerString($selected);

        if ($selectedText === '') {
            return [null, null];
        }

        if ($question->detils->isNotEmpty()) {
            $selectedOption = $question->detils->first(function (RefSubpertanyaanDetil $option) use ($selectedText): bool {
                return mb_strtolower(trim((string) $option->option_text)) === mb_strtolower($selectedText)
                    || mb_strtolower(trim((string) $option->kode_opsi)) === mb_strtolower($selectedText);
            });

            if (! $selectedOption) {
                return [null, null];
            }

            $selectedText = (string) $selectedOption->option_text;
        }

        $customText = trim(self::toAnswerString($custom));
        $isOther = self::isOtherOption($selectedText);

        if ($isOther) {
            if ($customText === '') {
                return [null, null];
            }

            return [
                'Lainnya: '.$customText,
                ['selected' => $selectedText, 'input' => $customText],
            ];
        }

        return [
            $selectedText,
            $question->detils->isNotEmpty() ? ['selected' => $selectedText, 'input' => ''] : null,
        ];
    }

    private static function toAnswerString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function isOtherOption(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return str_contains($normalized, 'lainnya')
            || str_contains($normalized, 'tuliskan')
            || str_contains($normalized, '...');
    }

    /**
     * Menyinkronkan data profil biodata alumni ke tabel tracer untuk pertanyaan identitas dan pekerjaan.
     *
     * @param  Biodata  $biodata  Model biodata yang akan disinkronkan datanya.
     */
    public static function syncProfileResponses(Biodata $biodata): void
    {
        // Ambil data terpadu alumni dari Database View
        $autofill = DB::table('v_alumni_kuesioner_autofill')
            ->where('biodata_id', $biodata->id)
            ->first();

        if (! $autofill) {
            return;
        }

        $tahunLulus = $biodata->tahun_lulus ?? ($biodata->dataAkademik?->tahun_lulus ? (string) $biodata->dataAkademik->tahun_lulus : null);
        $profileMap = (array) $autofill;

        // Alias singkat digunakan sebagai kode master agar tetap muat di varchar(20).
        $profileMap['BIO_PEND_TINGKAT'] = $biodata->pendidikan_tingkat;

        // Hilangkan kolom non-pertanyaan
        unset($profileMap['user_id'], $profileMap['biodata_id'], $profileMap['nim']);

        $profileCodesUpper = array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES);

        foreach ($profileMap as $code => $val) {
            $codeUpper = strtoupper($code);

            $question = RefSubpertanyaan2021::whereRaw('UPPER(kode_pertanyaan) = ?', [$codeUpper])
                ->with('detils')
                ->first();

            if (! $question) {
                continue;
            }

            if ($val === null || trim((string) $val) === '') {
                // Hanya hapus baris tracer jika kolom ini memang bagian kelola profil (PROFILE_MANAGED_CODES)
                if (in_array($codeUpper, $profileCodesUpper)) {
                    Tracer::where('biodata_id', $biodata->id)
                        ->where(function ($q) use ($question) {
                            $q->where('question_id', $question->id)
                                ->orWhere('kode_pertanyaan', $question->kode_pertanyaan);
                        })
                        ->delete();
                }
            } else {
                $answerJson = null;
                $answerString = (string) $val;
                if ($codeUpper === 'F505') {
                    $intGaji = (int) round((float) $val);
                    $answerString = (string) $intGaji;
                    $answerJson = ['F5051' => (string) $intGaji, 'total' => $intGaji];
                } elseif ($codeUpper === 'F8') {
                    // Normalisasikan ke teks opsi resmi F8 di ref_subpertanyaan_detil agar selalu cocok dengan pilihan radio button di frontend
                    $vStr = strtolower(trim((string) $val));
                    $matchedOpt = $question->detils->first(function ($opt) use ($vStr) {
                        $optText = strtolower(trim((string) $opt->option_text));

                        return $vStr === $optText
                            || ($vStr === 'pekerja' && str_contains($optText, 'bekerja'))
                            || ($vStr === 'bekerja' && str_contains($optText, 'bekerja'))
                            || ($vStr === 'mencari kerja' && str_contains($optText, 'mencari kerja'))
                            || ($vStr === 'tidak bekerja' && (str_contains($optText, 'mencari kerja') || str_contains($optText, 'belum memungkinkan')))
                            || ($vStr === 'belum bekerja' && (str_contains($optText, 'mencari kerja') || str_contains($optText, 'belum memungkinkan')))
                            || ($vStr === 'belum memungkinkan bekerja' && str_contains($optText, 'belum memungkinkan'))
                            || ($vStr === 'melanjutkan pendidikan' && str_contains($optText, 'melanjutkan pendidikan'))
                            || ($vStr === 'wiraswasta' && str_contains($optText, 'wiraswasta'));
                    });

                    if ($matchedOpt) {
                        $answerString = $matchedOpt->option_text;
                    }
                }

                // Hapus baris dengan kode_pertanyaan sama tetapi id pertanyaan berbeda jika ada
                Tracer::where('biodata_id', $biodata->id)
                    ->where('kode_pertanyaan', $question->kode_pertanyaan)
                    ->where('question_id', '!=', $question->id)
                    ->delete();

                Tracer::updateOrCreate(
                    ['biodata_id' => $biodata->id, 'question_id' => $question->id],
                    [
                        'nim' => $biodata->nim,
                        'kelompok' => $question->kelompok,
                        'kode_pertanyaan' => $question->kode_pertanyaan,
                        'subpertanyaan' => $question->subpertanyaan,
                        'answer' => $answerString,
                        'answer_json' => $answerJson,
                        'keterangan' => $question->keterangan,
                        'tahun_lulus' => $tahunLulus,
                    ]
                );
            }
        }

        // Sinkronkan juga data akademik alumni ke kuesioner program studi
        self::syncProdiResponses($biodata);
    }

    /**
     * Menyinkronkan data akademik alumni (Nama, NIM, Tahun Kelulusan)
     * secara otomatis ke tabel `prodi_response`.
     */
    public static function syncProdiResponses(Biodata $biodata): void
    {
        if (! $biodata->prodi_id) {
            return;
        }

        $prodiQuestions = ProdiQuestion::where('prodi_id', $biodata->prodi_id)->get();
        if ($prodiQuestions->isEmpty()) {
            return;
        }

        $namaLengkap = $biodata->nama ?? $biodata->dataAkademik?->nama;
        $nim = $biodata->nim;
        $tahunLulus = $biodata->tahun_lulus ?? ($biodata->dataAkademik?->tahun_lulus ?? $biodata->dataAkademik?->tahun_akademik_lulus);

        $prodiSyncMap = [
            'nama' => $namaLengkap,
            'nim' => $nim,
            'tahun kelulusan' => $tahunLulus ? (string) $tahunLulus : null,
        ];

        foreach ($prodiSyncMap as $textKey => $val) {
            if ($val === null || trim((string) $val) === '') {
                continue;
            }

            $pQ = $prodiQuestions->first(fn ($q) => strtolower(trim((string) $q->question_text)) === $textKey);
            if ($pQ) {
                ProdiResponse::updateOrCreate(
                    ['biodata_id' => $biodata->id, 'prodi_question_id' => $pQ->id],
                    [
                        'answer_text' => (string) $val,
                        'answer_json' => null,
                    ]
                );
            }
        }
    }
}
