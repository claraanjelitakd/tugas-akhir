<?php

use App\Models\RefSubpertanyaan2021;
use App\Services\Kuesioner\QuestionDefaultOptionsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        RefSubpertanyaan2021::query()
            ->where(function (Builder $query): void {
                $query->whereNull('keterangan')->orWhere('keterangan', '');
            })
            ->get()
            ->each(function (RefSubpertanyaan2021 $question): void {
                $instruction = QuestionDefaultOptionsService::getDefaultInstruction($question->type);

                if ($instruction !== null) {
                    $question->updateQuietly(['keterangan' => $instruction]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
