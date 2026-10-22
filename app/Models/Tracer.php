<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Tracer (tracer)
 *
 * Mengelola data jawaban kuesioner tracer study alumni.
 */
class Tracer extends Model
{
    use HasFactory;

    protected $table = 'tracer';

    protected $fillable = [
        'biodata_id',
        'question_id',
        'nim',
        'kelompok',
        'kode_pertanyaan',
        'subpertanyaan',
        'answer',
        'answer_text',
        'answer_json',
        'keterangan',
        'tahun_lulus',
    ];

    protected $casts = [
        'answer_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Tracer $tracer): void {
            // Auto-populate data pendukung dari relasi jika belum terisi
            if (empty($tracer->nim) && $tracer->biodata_id) {
                $biodata = Biodata::find($tracer->biodata_id);
                if ($biodata) {
                    $tracer->nim = $biodata->nim;
                    if (empty($tracer->tahun_lulus)) {
                        $tracer->tahun_lulus = $biodata->tahun_lulus ?? $biodata->dataAkademik?->tahun_lulus;
                    }
                }
            }

            if ((empty($tracer->attributes['kode_pertanyaan']) || empty($tracer->attributes['subpertanyaan'])) && $tracer->question_id) {
                $question = RefSubpertanyaan2021::find($tracer->question_id);
                if ($question) {
                    $tracer->kode_pertanyaan = $tracer->kode_pertanyaan ?: $question->kode_pertanyaan;
                    $tracer->subpertanyaan = $tracer->attributes['subpertanyaan'] ?? $question->subpertanyaan;
                    $tracer->kelompok = $tracer->kelompok ?: ($question->kelompok ?: substr($question->kode_pertanyaan ?: 'F1', 0, 3));
                    $tracer->keterangan = $tracer->keterangan ?: $question->keterangan;
                }
            }

            if (empty($tracer->kelompok)) {
                $tracer->kelompok = substr($tracer->kode_pertanyaan ?: 'F1', 0, 3);
            }

            $answer = trim((string) ($tracer->attributes['answer'] ?? ''));
            $answerJson = $tracer->getAttribute('answer_json');

            if (is_string($answerJson)) {
                $decodedAnswerJson = json_decode($answerJson, true);
                $answerJson = is_array($decodedAnswerJson) ? $decodedAnswerJson : null;
            }

            if (! is_array($answerJson) && $answerJson !== null) {
                $answerJson = ['value' => (string) $answerJson];
            }

            if ($answer === '' && is_array($answerJson) && $answerJson !== []) {
                $answer = self::flattenAnswerJson($answerJson);
            }

            if ($answer !== '' && (! is_array($answerJson) || $answerJson === [])) {
                $answerJson = ['value' => $answer];
            }

            $tracer->setAttribute('answer', $answer !== '' ? $answer : null);
            $tracer->setAttribute('answer_json', is_array($answerJson) && $answerJson !== [] ? $answerJson : null);
        });
    }

    /**
     * Menghasilkan representasi teks yang stabil dari jawaban JSON.
     *
     * @param  array<string|int, mixed>  $answerJson
     */
    private static function flattenAnswerJson(array $answerJson): string
    {
        if (array_key_exists('selected', $answerJson) || array_key_exists('pilihan', $answerJson)) {
            $selected = $answerJson['selected'] ?? $answerJson['pilihan'] ?? '';
            $input = $answerJson['input'] ?? $answerJson['text'] ?? '';
            $selectedText = is_scalar($selected) ? trim((string) $selected) : '';
            $inputText = is_scalar($input) ? trim((string) $input) : '';

            return $selectedText.($inputText !== '' ? ': '.$inputText : '');
        }

        if (array_key_exists('value', $answerJson)) {
            return self::flattenAnswerValue($answerJson['value']);
        }

        $parts = [];
        foreach ($answerJson as $key => $value) {
            if ($key === 'total') {
                continue;
            }

            $text = self::flattenAnswerValue($value);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    private static function flattenAnswerValue(mixed $value): string
    {
        if (is_array($value)) {
            return self::flattenAnswerJson($value);
        }

        return is_scalar($value) && ! is_bool($value) ? trim((string) $value) : '';
    }

    /**
     * Relasi ke biodata.
     */
    public function biodata()
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    /**
     * Relasi ke master butir pertanyaan (RefSubpertanyaan2021).
     *
     * Nama relasi tidak memakai `subpertanyaan` karena tabel tracer juga
     * memiliki kolom snapshot teks dengan nama tersebut.
     */
    public function masterPertanyaan(): BelongsTo
    {
        return $this->belongsTo(RefSubpertanyaan2021::class, 'question_id');
    }

    // Accessors & Mutators
    public function getAnswerTextAttribute()
    {
        return $this->attributes['answer'] ?? null;
    }

    public function setAnswerTextAttribute($value)
    {
        $this->attributes['answer'] = $value;
    }
}
