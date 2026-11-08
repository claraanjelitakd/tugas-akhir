<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk pilihan jawaban master pada butir pertanyaan kuesioner.
 */
class KuesionerOpsi extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_opsi';

    protected $fillable = [
        'pertanyaan_id',
        'kode_opsi',
        'label',
        'label_singkat',
        'nilai_skor',
        'order',
    ];

    protected $casts = [
        'nilai_skor' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Butir pertanyaan pemilik opsi ini.
     */
    public function pertanyaan(): BelongsTo
    {
        return $this->belongsTo(KuesionerPertanyaan::class, 'pertanyaan_id');
    }

    /**
     * Seluruh jawaban responden yang memilih opsi ini.
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(KuesionerJawaban::class, 'opsi_id');
    }
}
