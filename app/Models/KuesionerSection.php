<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk bagian/kelompok instrumen kuesioner terpadu.
 *
 * Menentukan target instrumen (universitas, program studi, atasan)
 * serta mengelompokkan butir pertanyaan secara modular.
 */
class KuesionerSection extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_section';

    protected $fillable = [
        'kode_section',
        'target',
        'prodi_id',
        'judul',
        'deskripsi',
        'order',
        'is_active',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke program studi pemilik section (jika kuesioner khusus prodi).
     */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_id');
    }

    /**
     * Relasi ke seluruh pertanyaan yang ditempatkan di section ini.
     */
    public function pertanyaan(): BelongsToMany
    {
        return $this->belongsToMany(
            KuesionerPertanyaan::class,
            'kuesioner_section_pertanyaan',
            'section_id',
            'pertanyaan_id'
        )
            ->withPivot(['id', 'order', 'wajib', 'tampil_di'])
            ->withTimestamps()
            ->orderByPivot('order');
    }

    /**
     * Relasi ke tabel pivot penempatan pertanyaan.
     */
    public function sectionPertanyaan(): HasMany
    {
        return $this->hasMany(KuesionerSectionPertanyaan::class, 'section_id')->orderBy('order');
    }

    /**
     * Relasi ke respon jawaban yang masuk pada section ini.
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(KuesionerJawaban::class, 'section_id');
    }
}
