<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk butir pertanyaan master pada Bank Soal kuesioner terpadu.
 *
 * Pertanyaan bersifat universal dan reusable, dapat dipasang di satu atau banyak
 * bagian (section) kuesioner tingkat universitas maupun program studi.
 */
class KuesionerPertanyaan extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_pertanyaan';

    protected $fillable = [
        'kode_pertanyaan',
        'teks_pertanyaan',
        'tipe',
        'pemilik_prodi_id',
        'is_reusable',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'is_reusable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Program studi yang membuat pertanyaan awal (NULL jika milik umum/universitas).
     */
    public function pemilikProdi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'pemilik_prodi_id');
    }

    /**
     * Seluruh section kuesioner tempat butir pertanyaan ini ditempatkan.
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(
            KuesionerSection::class,
            'kuesioner_section_pertanyaan',
            'pertanyaan_id',
            'section_id'
        )
            ->withPivot(['id', 'order', 'wajib', 'tampil_di'])
            ->withTimestamps();
    }

    /**
     * Opsi atau pilihan jawaban master untuk pertanyaan ini.
     */
    public function opsi(): HasMany
    {
        return $this->hasMany(KuesionerOpsi::class, 'pertanyaan_id')->orderBy('order');
    }

    /**
     * Seluruh respon jawaban alumni maupun atasan untuk butir pertanyaan ini.
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(KuesionerJawaban::class, 'pertanyaan_id');
    }

    /**
     * Alias aspek teks pertanyaan untuk kompatibilitas instrumen atasan.
     */
    public function getAspekAttribute(): ?string
    {
        return $this->teks_pertanyaan;
    }

    /**
     * Alias kode pertanyaan (tanpa prefix EVA_) untuk kompatibilitas form evaluasi.
     */
    public function getKodeAttribute(): ?string
    {
        return str_starts_with($this->kode_pertanyaan, 'EVA_')
            ? substr($this->kode_pertanyaan, 4)
            : $this->kode_pertanyaan;
    }

    /**
     * Cek apakah pertanyaan merupakan atribut profil perusahaan.
     */
    public function getIsCompanyFieldAttribute(): bool
    {
        $codes = [
            'EVA_skala_perusahaan', 'EVA_jumlah_pegawai', 'EVA_jumlah_alumni_ukdw', 'EVA_standar_gaji_pertama',
            'skala_perusahaan', 'jumlah_pegawai', 'jumlah_alumni_ukdw', 'standar_gaji_pertama',
        ];

        return in_array($this->kode_pertanyaan, $codes, true) || in_array($this->kode, $codes, true);
    }
}
