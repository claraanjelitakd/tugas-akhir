<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tracer')) {
            return;
        }

        DB::table('tracer')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $answer = trim((string) ($row->answer ?? ''));
                    $decoded = is_string($row->answer_json ?? null)
                        ? json_decode($row->answer_json, true)
                        : null;
                    $answerJson = is_array($decoded) && $decoded !== [] ? $decoded : null;

                    if ($answer === '' && $answerJson !== null) {
                        $answer = self::flattenAnswerJson($answerJson);
                    }

                    if ($answer !== '' && $answerJson === null) {
                        $answerJson = ['value' => $answer];
                    }

                    if ($answer === '' && $answerJson === null) {
                        continue;
                    }

                    DB::table('tracer')
                        ->where('id', $row->id)
                        ->update([
                            'answer' => $answer !== '' ? $answer : null,
                            'answer_json' => $answerJson !== null
                                ? json_encode($answerJson, JSON_UNESCAPED_UNICODE)
                                : null,
                        ]);
                }
            });
    }

    /**
     * @param  array<string|int, mixed>  $answerJson
     */
    private static function flattenAnswerJson(array $answerJson): string
    {
        if (array_key_exists('selected', $answerJson) || array_key_exists('pilihan', $answerJson)) {
            $selected = $answerJson['selected'] ?? $answerJson['pilihan'] ?? '';
            $input = $answerJson['input'] ?? $answerJson['text'] ?? '';

            return trim((string) $selected).(trim((string) $input) !== '' ? ': '.trim((string) $input) : '');
        }

        if (array_key_exists('value', $answerJson)) {
            return is_scalar($answerJson['value']) ? trim((string) $answerJson['value']) : '';
        }

        $parts = [];
        foreach ($answerJson as $key => $value) {
            if ($key === 'total') {
                continue;
            }

            if (is_scalar($value) && ! is_bool($value) && trim((string) $value) !== '') {
                $parts[] = trim((string) $value);
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    /**
     * Data normalization is intentionally not reversed because the original
     * answer-only rows cannot be distinguished safely after backfilling.
     */
    public function down(): void
    {
        // No-op: this migration preserves the original answer column.
    }
};
