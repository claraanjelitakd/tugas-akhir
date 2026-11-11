<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk rekam respon jawaban kuesioner terpadu.
 *
 * Menghubungkan responden (alumni/atasan) dengan butir pertanyaan,
 * bagian kuesioner, serta opsi yang dipilih atau isian teks bebas.
 */
class KuesionerJawaban extends Model
{
    use HasFactory;

    /**
     * Menjaga tiga kolom referensi pilihan tetap sinkron setiap kali jawaban disimpan.
     *
     * Resolusi selalu dibatasi pada pertanyaan pemilik jawaban. Dengan demikian,
     * kode opsi yang sama pada pertanyaan berbeda tidak pernah tertukar.
     */
    protected static function booted(): void
    {
        static::saving(function (self $answer): void {
            $question = $answer->relationLoaded('pertanyaan')
                ? $answer->pertanyaan
                : KuesionerPertanyaan::with('opsi')->find($answer->pertanyaan_id);

            if (! $question && filled($answer->kode_pertanyaan)) {
                $question = KuesionerPertanyaan::query()
                    ->where('kode_pertanyaan', $answer->kode_pertanyaan)
                    ->with('opsi')
                    ->first();
            }

            if (! $question || $question->opsi->isEmpty()) {
                return;
            }

            $option = $question->opsi->firstWhere('id', $answer->opsi_id);
            $option ??= $question->opsi->first(function (KuesionerOpsi $candidate) use ($answer): bool {
                return filled($answer->kode_opsi)
                    && ! str_starts_with(trim((string) $answer->kode_opsi), '[')
                    && (string) $candidate->kode_opsi === trim((string) $answer->kode_opsi);
            });

            $option ??= $question->opsi->first(function (KuesionerOpsi $candidate) use ($answer, $question): bool {
                $answerText = mb_strtolower(trim((string) $answer->jawaban_teks));
                $optionText = mb_strtolower(trim((string) $candidate->label));

                if ($answerText === $optionText) {
                    return true;
                }

                return strtoupper((string) $question->kode_pertanyaan) === 'F8'
                    && $answerText === 'wirausaha'
                    && $optionText === 'wiraswasta';
            });

            if (! $option) {
                return;
            }

            $answer->opsi_id = $option->id;
            $answer->kode_opsi = $option->kode_opsi;
            $answer->jawaban_teks = $option->label;

            if (is_array($answer->jawaban_json) && array_key_exists('selected', $answer->jawaban_json)) {
                $answer->jawaban_json = [
                    ...$answer->jawaban_json,
                    'selected' => $option->label,
                ];
            }
        });
    }

    protected $table = 'kuesioner_jawaban';

    protected $fillable = [
        'responden_type',
        'biodata_id',
        'evaluasi_atasan_id',
        'section_id',
        'pertanyaan_id',
        'kode_pertanyaan',
        'opsi_id',
        'kode_opsi',
        'jawaban_teks',
        'jawaban_json',
    ];

    protected $casts = [
        'jawaban_json' => 'array',
    ];

    /**
     * Relasi ke biodata alumni pengisi (jika responden = alumni).
     */
    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    /**
     * Relasi ke evaluasi atasan (jika responden = atasan).
     */
    public function evaluasiAtasan(): BelongsTo
    {
        return $this->belongsTo(EvaluasiAtasan::class, 'evaluasi_atasan_id');
    }

    /**
     * Section kuesioner tempat pertanyaan dijawab.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(KuesionerSection::class, 'section_id');
    }

    /**
     * Butir pertanyaan yang dijawab.
     */
    public function pertanyaan(): BelongsTo
    {
        return $this->belongsTo(KuesionerPertanyaan::class, 'pertanyaan_id');
    }

    /**
     * Nilai jawaban teks untuk kompatibilitas evaluasi atasan.
     */
    public function getNilaiAttribute(): ?string
    {
        return $this->jawaban_teks;
    }

    /**
     * Catatan tambahan kualitatif responden jika ada.
     */
    public function getCatatanAttribute(): ?string
    {
        return is_array($this->jawaban_json) ? ($this->jawaban_json['catatan'] ?? null) : null;
    }

    /**
     * Opsi jawaban master yang dipilih (jika bertipe pilihan).
     */
    public function opsi(): BelongsTo
    {
        return $this->belongsTo(KuesionerOpsi::class, 'opsi_id');
    }
}
