<?php

namespace App\Services\Kuesioner;

use App\Models\Biodata;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KuesionerSyncService
 *
 * Fungsi: Menyinkronkan data profil biodata alumni (Data Akademik, Akun User, Perusahaan, dan Atasan)
 * secara otomatis ke tabel `kuesioner_jawaban` untuk butir instrumen profil kanonik.
 */
class KuesionerSyncService
{
    /**
     * Ambil pertanyaan tambahan yang ditandai tampil pada halaman profil.
     *
     * @return Collection<int, KuesionerPertanyaan>
     */
    public static function getDynamicProfileQuestions(): Collection
    {
        $profileCodes = array_merge(
            array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES),
            ['BIO_BIDANG_DIVISI']
        );

        return KuesionerPertanyaan::query()
            ->join('kuesioner_section_pertanyaan as sp', 'sp.pertanyaan_id', '=', 'kuesioner_pertanyaan.id')
            ->whereIn('sp.tampil_di', ['profil', 'profile', 'both'])
            ->where('sp.is_active', true)
            ->whereNotIn(DB::raw('UPPER(kuesioner_pertanyaan.kode_pertanyaan)'), $profileCodes)
            ->where('kuesioner_pertanyaan.tipe', '!=', 'header')
            ->where('kuesioner_pertanyaan.is_active', true)
            ->with('opsi')
            ->select('kuesioner_pertanyaan.*', 'sp.wajib as pivot_wajib')
            ->orderBy('sp.order')
            ->orderBy('kuesioner_pertanyaan.id')
            ->get();
    }

    /**
     * Bentuk jawaban tambahan profil yang sudah tersimpan agar dapat dipakai kembali oleh Vue.
     *
     * @param  Collection<int, KuesionerPertanyaan>  $questions
     * @return array{answers: array<int, string>, custom: array<int, string>}
     */
    public static function getDynamicProfileAnswers(Biodata $biodata, Collection $questions): array
    {
        if ($questions->isEmpty()) {
            return ['answers' => [], 'custom' => []];
        }

        $responses = KuesionerJawaban::where('biodata_id', $biodata->id)
            ->whereIn('pertanyaan_id', $questions->pluck('id')->all())
            ->get()
            ->keyBy('pertanyaan_id');

        $answers = [];
        $custom = [];

        foreach ($questions as $question) {
            $resp = $responses->get($question->id);
            if (! $resp) {
                continue;
            }

            $rawText = $resp->jawaban_teks;
            $rawJson = $resp->jawaban_json;

            if (is_array($rawJson) && isset($rawJson['selected'])) {
                $answers[$question->id] = (string) $rawJson['selected'];
                if (isset($rawJson['input'])) {
                    $custom[$question->id] = (string) $rawJson['input'];
                }

                continue;
            }

            if ($rawText !== null && str_starts_with((string) $rawText, 'Lainnya: ')) {
                $answers[$question->id] = 'Lainnya';
                $custom[$question->id] = trim(substr((string) $rawText, 9));

                continue;
            }

            if ($rawText !== null) {
                $answers[$question->id] = (string) $rawText;
            }
        }

        return ['answers' => $answers, 'custom' => $custom];
    }

    /**
     * Menyimpan respon pertanyaan dinamis tambahan profil ke tabel kuesioner_jawaban.
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
        $univSection = KuesionerSection::where('target', 'univ')->first();
        $univSectionId = $univSection?->id ?: 1;

        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);
            if (! $question) {
                continue;
            }

            $rawAnswer = $answers[$questionId] ?? $answers[(string) $questionId] ?? null;
            $customText = $customAnswers[$questionId] ?? $customAnswers[(string) $questionId] ?? null;
            [$answerText, $answerJson] = self::normalizeDynamicProfileAnswer($question, $rawAnswer, $customText);

            if ($answerText === null && $answerJson === null) {
                KuesionerJawaban::where('biodata_id', $biodata->id)
                    ->where('pertanyaan_id', $question->id)
                    ->delete();

                continue;
            }

            $opsi = null;
            if ($answerText !== null && $question->opsi->isNotEmpty()) {
                $opsi = $question->opsi->first(fn ($o) => $o->label === $answerText || $o->kode_opsi === $answerText);
            }

            KuesionerJawaban::updateOrCreate(
                [
                    'responden_type' => 'alumni',
                    'biodata_id' => $biodata->id,
                    'pertanyaan_id' => $question->id,
                ],
                [
                    'evaluasi_atasan_id' => null,
                    'section_id' => $univSectionId,
                    'kode_pertanyaan' => $question->kode_pertanyaan,
                    'opsi_id' => $opsi?->id,
                    'kode_opsi' => $opsi?->kode_opsi,
                    'jawaban_teks' => $answerText,
                    'jawaban_json' => $answerJson,
                ]
            );
        }
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>|null}
     */
    private static function normalizeDynamicProfileAnswer(KuesionerPertanyaan $question, mixed $rawAnswer, mixed $customText): array
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

        if ($question->opsi->isNotEmpty()) {
            $selectedOption = $question->opsi->first(function (KuesionerOpsi $option) use ($selectedText): bool {
                return mb_strtolower(trim((string) $option->label)) === mb_strtolower($selectedText)
                    || mb_strtolower(trim((string) $option->kode_opsi)) === mb_strtolower($selectedText);
            });

            if (! $selectedOption) {
                return [null, null];
            }

            $selectedText = (string) $selectedOption->label;
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
            $question->opsi->isNotEmpty() ? ['selected' => $selectedText, 'input' => ''] : null,
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
     * Menyinkronkan data profil biodata alumni ke tabel kuesioner_jawaban untuk pertanyaan identitas dan pekerjaan.
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

        $profileMap = (array) $autofill;
        $profileMap['BIO_PEND_TINGKAT'] = $biodata->pendidikan_tingkat;

        unset($profileMap['user_id'], $profileMap['biodata_id'], $profileMap['nim']);

        $profileCodesUpper = array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES);
        $univSection = KuesionerSection::where('target', 'univ')->first();
        $univSectionId = $univSection?->id ?: 1;

        foreach ($profileMap as $code => $val) {
            $codeUpper = strtoupper($code);

            if ($codeUpper === 'F505') {
                $val = $biodata->gaji;
            }

            $question = KuesionerPertanyaan::whereRaw('UPPER(kode_pertanyaan) = ?', [$codeUpper])
                ->with('opsi')
                ->first();

            if (! $question) {
                continue;
            }

            if ($val === null || trim((string) $val) === '') {
                if (in_array($codeUpper, $profileCodesUpper)) {
                    KuesionerJawaban::where('biodata_id', $biodata->id)
                        ->where('pertanyaan_id', $question->id)
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
                    $vStr = strtolower(trim((string) $val));
                    $matchedOpt = $question->opsi->first(function ($opt) use ($vStr) {
                        $optText = strtolower(trim((string) $opt->label));

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
                        $answerString = $matchedOpt->label;
                    }
                }

                $opsi = null;
                if (! empty($answerString)) {
                    $opsi = $question->opsi->first(fn ($o) => $o->label === $answerString || $o->kode_opsi === $answerString);
                }

                KuesionerJawaban::updateOrCreate(
                    [
                        'responden_type' => 'alumni',
                        'biodata_id' => $biodata->id,
                        'pertanyaan_id' => $question->id,
                    ],
                    [
                        'evaluasi_atasan_id' => null,
                        'section_id' => $univSectionId,
                        'kode_pertanyaan' => $question->kode_pertanyaan,
                        'opsi_id' => $opsi?->id,
                        'kode_opsi' => $opsi?->kode_opsi,
                        'jawaban_teks' => $answerString,
                        'jawaban_json' => $answerJson,
                    ]
                );
            }
        }

        // Sinkronkan juga data akademik alumni ke kuesioner program studi
        self::syncProdiResponses($biodata);
    }

    /**
     * Menyinkronkan data akademik alumni (Nama, NIM, Tahun Kelulusan)
     * secara otomatis ke tabel `kuesioner_jawaban` untuk pertanyaan kuesioner prodi.
     */
    public static function syncProdiResponses(Biodata $biodata): void
    {
        if (! $biodata->prodi_id) {
            return;
        }

        $prodiQuestions = KuesionerPertanyaan::query()
            ->where('is_active', true)
            ->whereHas('sections', function ($query) use ($biodata) {
                $query->where('kuesioner_section.target', 'prodi')
                    ->where('kuesioner_section.prodi_id', $biodata->prodi_id)
                    ->where('kuesioner_section_pertanyaan.is_active', true);
            })
            ->get();

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

        $sec = KuesionerSection::where('target', 'prodi')
            ->where('prodi_id', $biodata->prodi_id)
            ->first();
        $secId = $sec?->id ?: 1;

        foreach ($prodiSyncMap as $textKey => $val) {
            if ($val === null || trim((string) $val) === '') {
                continue;
            }

            $pQ = $prodiQuestions->first(fn ($q) => self::resolveAcademicIdentityField((string) $q->teks_pertanyaan) === $textKey);
            if ($pQ) {
                KuesionerJawaban::updateOrCreate(
                    [
                        'responden_type' => 'alumni',
                        'biodata_id' => $biodata->id,
                        'pertanyaan_id' => $pQ->id,
                    ],
                    [
                        'evaluasi_atasan_id' => null,
                        'section_id' => $secId,
                        'kode_pertanyaan' => $pQ->kode_pertanyaan,
                        'opsi_id' => null,
                        'kode_opsi' => null,
                        'jawaban_teks' => (string) $val,
                        'jawaban_json' => null,
                    ]
                );
            }
        }
    }

    /**
     * Memetakan variasi label pertanyaan identitas ke field akademik kanonik.
     *
     * @return 'nama'|'nim'|'tahun kelulusan'|null
     */
    public static function resolveAcademicIdentityField(string $questionText): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($questionText))) ?? '';

        if ($normalized === 'nama' || str_contains($normalized, 'nama lengkap')) {
            return 'nama';
        }

        if ($normalized === 'nim' || str_contains($normalized, 'nomor induk mahasiswa')) {
            return 'nim';
        }

        if (str_contains($normalized, 'tahun kelulusan') || str_contains($normalized, 'tahun lulus')) {
            return 'tahun kelulusan';
        }

        return null;
    }
}
