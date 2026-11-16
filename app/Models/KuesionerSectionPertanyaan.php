<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model relasi penempatan butir pertanyaan ke dalam section instrumen.
 *
 * Mengatur urutan penampilan butir, status kewajiban (wajib/opsional),
 * dan aturan logika tampilan percabangan. Nilai primary key model ini disebut
 * placement_id pada payload pengelolaan kuesioner.
 */
class KuesionerSectionPertanyaan extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_section_pertanyaan';

    protected $fillable = [
        'section_id',
        'pertanyaan_id',
        'order',
        'wajib',
        'is_active',
        'tampil_di',
    ];

    protected $casts = [
        'order' => 'integer',
        'wajib' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Section kuesioner tempat butir ditempatkan.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(KuesionerSection::class, 'section_id');
    }

    /**
     * Butir pertanyaan yang ditempatkan.
     */
    public function pertanyaan(): BelongsTo
    {
        return $this->belongsTo(KuesionerPertanyaan::class, 'pertanyaan_id');
    }
}
