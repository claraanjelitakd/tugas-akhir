<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Relasi ke bagian kuesioner universitas (KuesionerSection).
     */
    public function sections(): HasMany
    {
        return $this->hasMany(KuesionerSection::class, 'kuesioner_id')
            ->where('target', 'univ')
            ->orderBy('order');
    }

    /**
     * Relasi ke seluruh bagian kuesioner (universitas, prodi, maupun atasan).
     */
    public function allSections(): HasMany
    {
        return $this->hasMany(KuesionerSection::class, 'kuesioner_id')
            ->orderBy('order');
    }

    /**
     * Relasi ke seluruh butir jawaban responden yang terekam pada periode kuesioner ini.
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(KuesionerJawaban::class, 'kuesioner_id');
    }
}
