<?php

namespace App\Services\Kuesioner;

use App\Models\ProdiQuestion;
use App\Models\RefSubpertanyaan2021;

/**
 * Service untuk menyediakan opsi pilihan jawaban default saat butir pertanyaan baru dibuat
 * oleh Administrator (Super Admin maupun Admin Program Studi).
 */
class QuestionDefaultOptionsService
{
    /**
     * Dapatkan petunjuk pengisian default berdasarkan tipe pertanyaan.
     */
    public static function getDefaultInstruction(string $type): ?string
    {
        $normalizedType = match ($type) {
            'radio' => 'single_choice',
            'checkbox' => 'multiple_choice',
            default => $type,
        };

        return match ($normalizedType) {
            'single_choice' => 'Pilih salah satu jawaban.',
            'radio_text' => 'Pilih salah satu jawaban. Jika memilih lainnya, tuliskan jawaban.',
            'radio_input' => 'Pilih salah satu jawaban dan isi angka jika diperlukan.',
            'multiple_choice' => 'Jawaban dapat lebih dari satu.',
            'dropdown' => 'Pilih salah satu jawaban.',
            'searchable_select' => 'Cari dan pilih salah satu jawaban.',
            'rating_5' => 'Pilih satu nilai pada skala 1 sampai 5.',
            'multiple_number' => 'Isi nominal pada setiap komponen yang sesuai.',
            'matrix' => 'Pilih satu jawaban pada setiap baris.',
            'matrix_dual' => 'Berikan penilaian pada setiap pernyataan.',
            'multiple_textbox' => 'Isi jawaban pada kolom yang tersedia.',
            'text' => 'Isi jawaban singkat.',
            'textarea' => 'Isi jawaban pada kolom yang tersedia.',
            'number' => 'Isi jawaban dalam angka.',
            'date' => 'Pilih tanggal yang sesuai.',
            'time' => 'Pilih waktu yang sesuai.',
            'file' => 'Unggah berkas sesuai petunjuk.',
            default => null,
        };
    }

    /**
     * Dapatkan daftar teks opsi default berdasarkan tipe pertanyaan.
     *
     * @return array<int, string>
     */
    public static function getDefaultOptionTexts(string $type): array
    {
        $normalizedType = match ($type) {
            'radio' => 'single_choice',
            'checkbox' => 'multiple_choice',
            default => $type,
        };

        return match ($normalizedType) {
            'single_choice' => [
                'Pilihan 1',
                'Pilihan 2',
            ],
            'radio_text' => [
                'Pilihan 1',
                'Pilihan 2',
                'Lainnya (Tuliskan)',
            ],
            'radio_input' => [
                'Bulan sebelum lulus (Isi angka)',
                'Bulan sesudah lulus (Isi angka)',
                'Tidak mencari kerja / Tidak berlaku',
            ],
            'rating_5' => [
                '1 - Sangat Rendah',
                '2 - Rendah',
                '3 - Cukup',
                '4 - Tinggi',
                '5 - Sangat Tinggi',
            ],
            'multiple_choice' => [
                'Pilihan 1',
                'Pilihan 2',
            ],
            'dropdown' => [
                'Pilihan 1',
                'Pilihan 2',
            ],
            'searchable_select' => [
                'Pilihan 1',
                'Pilihan 2',
            ],
            'multiple_number' => [
                'Komponen Utama',
                'Komponen Tambahan',
            ],
            default => [],
        };
    }

    /**
     * Generate opsi default untuk ProdiQuestion jika belum memiliki opsi.
     */
    public static function generateForProdiQuestion(ProdiQuestion $question): void
    {
        if ($question->options()->exists()) {
            return;
        }

        $defaultTexts = self::getDefaultOptionTexts($question->type);
        if (empty($defaultTexts)) {
            return;
        }

        $order = 1;
        foreach ($defaultTexts as $idx => $text) {
            $code = $question->code.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $question->options()->create([
                'code' => $code,
                'option_text' => $text,
                'order' => $order++,
            ]);
        }
    }

    /**
     * Generate opsi default untuk RefSubpertanyaan2021 jika belum memiliki opsi.
     */
    public static function generateForUnivQuestion(RefSubpertanyaan2021 $question): void
    {
        if ($question->detils()->exists()) {
            return;
        }

        $defaultTexts = self::getDefaultOptionTexts($question->type);
        if (empty($defaultTexts)) {
            return;
        }

        $order = 1;
        foreach ($defaultTexts as $idx => $text) {
            $code = $question->kode_pertanyaan.'-'.str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $question->detils()->create([
                'kode_pertanyaan' => $question->kode_pertanyaan,
                'kode_opsi' => $code,
                'option_text' => $text,
                'order' => $order++,
            ]);
        }
    }
}
