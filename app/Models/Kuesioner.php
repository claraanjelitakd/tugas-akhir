<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Kuesioner (kuesioner)
 *
 * Mengelola instrumen kuesioner tracer study (misal: Tracer Study UKDW 2021).
 */
class Kuesioner extends Model
{
    use HasFactory;

    protected $table = 'kuesioner';

    protected $fillable = [
        'title',
        'description',
        'is_active',
        'year',
        'target_response_rate',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'target_response_rate' => 'float',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relasi ke kelompok pertanyaan (KelompokPertanyaan / QuestionSection).
     */
    public function kelompokPertanyaan()
    {
        return $this->hasMany(KelompokPertanyaan::class, 'kuesioner_id')->orderBy('order');
    }

    public function kelompokPertanyaans()
    {
        return $this->kelompokPertanyaan();
    }

    /**
     * Alias relasi sections untuk kompatibilitas frontend & kode lama.
     */
    public function sections()
    {
        return $this->kelompokPertanyaan();
    }

    /**
     * Relasi ke seluruh butir pertanyaan melalui kelompok pertanyaan.
     */
    public function subpertanyaans()
    {
        return $this->hasManyThrough(RefSubpertanyaan2021::class, KelompokPertanyaan::class, 'kuesioner_id', 'kelompok_pertanyaan_id');
    }

    /**
     * Alias relasi questions untuk kompatibilitas.
     */
    public function questions()
    {
        return $this->subpertanyaans();
    }
}
