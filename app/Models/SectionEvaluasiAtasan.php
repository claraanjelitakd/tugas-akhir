<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model SectionEvaluasiAtasan
 *
 * Mengelola bagian / seksi instrumen kuesioner evaluasi kepuasan pengguna lulusan (atasan).
 */
class SectionEvaluasiAtasan extends Model
{
    use HasFactory;

    protected $table = 'section_evaluasi_atasan';

    protected $fillable = [
        'key',
        'title',
        'description',
        'order',
    ];

    /** @var list<string> */
    protected $appends = ['source'];

    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * Sumber tabel data untuk bagian evaluasi atasan.
     */
    public function getSourceAttribute(): string
    {
        return match ($this->key) {
            'instansi' => 'perusahaan, atasan, evaluasi_atasan',
            'karakteristik' => 'perusahaan, evaluasi_atasan',
            default => 'respon_evaluasi_atasan',
        };
    }

    /**
     * Relasi ke butir pertanyaan evaluasi atasan di bawah bagian ini.
     */
    public function pertanyaans(): HasMany
    {
        return $this->hasMany(PertanyaanEvaluasiAtasan::class, 'section_id')->orderBy('order');
    }
}
