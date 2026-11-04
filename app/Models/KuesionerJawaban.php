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
