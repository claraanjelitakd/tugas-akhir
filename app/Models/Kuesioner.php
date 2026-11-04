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
     * Relasi ke bagian kuesioner universitas (KuesionerSection).
     */
    public function sections()
    {
        return $this->hasMany(KuesionerSection::class, 'kuesioner_id')
            ->where('target', 'univ')
            ->orderBy('order');
    }
}
