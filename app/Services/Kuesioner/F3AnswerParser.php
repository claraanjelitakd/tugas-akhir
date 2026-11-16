<?php

namespace App\Services\Kuesioner;

/**
 * Menormalisasi jawaban teks instrumen F3 menjadi fase dan masa tunggu kanonik.
 *
 * Parser ini menjadi satu-satunya tempat yang membaca format legacy seperti
 * "Sebelum lulus ... bulan: 3". Lapisan analitik menerima angka dan kategori
 * hasil normalisasi sehingga komponen frontend tidak perlu menebak isi teks.
 */
class F3AnswerParser
{
    /**
     * Mengubah jawaban mentah F3 menjadi struktur yang siap dipakai analitik.
     *
     * @param  string|null  $answer  Jawaban teks dari alumni.
     * @return array{
     *     raw_answer: string,
     *     phase_key: string,
     *     phase: string,
     *     months: float|null,
     *     range_key: string|null,
     *     range: string|null,
     *     segment_key: string,
     *     label: string
     * }
     */
    public function parse(?string $answer): array
    {
        $rawAnswer = trim((string) $answer);
        $normalizedAnswer = mb_strtolower($rawAnswer);
        $months = $this->extractMonths($rawAnswer);

        if (str_contains($normalizedAnswer, 'tidak mencari')) {
            return $this->result(
                $rawAnswer,
                'not_searching',
                'Tidak mencari kerja',
                $months,
                null,
                null,
                'Tidak mencari kerja',
            );
        }

        if (str_contains($normalizedAnswer, 'sebelum lulus')) {
            return $this->result(
                $rawAnswer,
                'before_graduation',
                'Sebelum lulus',
                $months,
                null,
                null,
                'Sebelum lulus',
            );
        }

        if (str_contains($normalizedAnswer, 'setelah lulus') || str_contains($normalizedAnswer, 'sesudah lulus')) {
            $range = $this->resolveAfterGraduationRange($months);

            return $this->result(
                $rawAnswer,
                'after_graduation',
                'Setelah lulus',
                $months,
                $range['key'],
                $range['label'],
                'Setelah lulus · '.$range['label'],
            );
        }

        return $this->result(
            $rawAnswer,
            'unknown',
            'Tidak teridentifikasi',
            $months,
            null,
            null,
            $rawAnswer ?: 'Tidak teridentifikasi',
        );
    }

    /**
     * Mengambil angka bulan dari jawaban terstruktur atau format angka polos.
     *
     * @param  string  $answer  Jawaban teks mentah.
     * @return float|null Angka bulan, atau null bila tidak tersedia.
     */
    private function extractMonths(string $answer): ?float
    {
        $patterns = [
            '/:\s*(\d+(?:[.,]\d+)?)/u',
            '/(\d+(?:[.,]\d+)?)\s*bulan/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $answer, $matches) !== 1) {
                continue;
            }

            return (float) str_replace(',', '.', $matches[1]);
        }

        return is_numeric($answer) ? (float) $answer : null;
    }

    /**
     * Menentukan rentang masa tunggu setelah lulus.
     *
     * @param  float|null  $months  Angka bulan hasil ekstraksi.
     * @return array{key: string, label: string}
     */
    private function resolveAfterGraduationRange(?float $months): array
    {
        if ($months === null) {
            return ['key' => 'after_unavailable', 'label' => 'Masa tunggu tidak tersedia'];
        }

        if ($months < 6) {
            return ['key' => 'after_under_6', 'label' => '< 6 bulan'];
        }

        if ($months <= 12) {
            return ['key' => 'after_6_to_12', 'label' => '6–12 bulan'];
        }

        return ['key' => 'after_over_12', 'label' => '> 12 bulan'];
    }

    /**
     * Membentuk payload hasil normalisasi dengan struktur yang konsisten.
     *
     * @param  string  $rawAnswer  Jawaban asli.
     * @param  string  $phaseKey  Kunci fase kanonik.
     * @param  string  $phase  Label fase kanonik.
     * @param  float|null  $months  Angka masa tunggu.
     * @param  string|null  $rangeKey  Kunci rentang kanonik.
     * @param  string|null  $range  Label rentang kanonik.
     * @param  string  $label  Label siap tampil.
     * @return array{
     *     raw_answer: string,
     *     phase_key: string,
     *     phase: string,
     *     months: float|null,
     *     range_key: string|null,
     *     range: string|null,
     *     segment_key: string,
     *     label: string
     * }
     */
    private function result(
        string $rawAnswer,
        string $phaseKey,
        string $phase,
        ?float $months,
        ?string $rangeKey,
        ?string $range,
        string $label,
    ): array {
        return [
            'raw_answer' => $rawAnswer,
            'phase_key' => $phaseKey,
            'phase' => $phase,
            'months' => $months,
            'range_key' => $rangeKey,
            'range' => $range,
            'segment_key' => $rangeKey ?: $phaseKey,
            'label' => $label,
        ];
    }
}
