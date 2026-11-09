<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Atasan
 *
 * Mengelola data profil pimpinan/atasan langsung alumni di tempat kerja.
 */
class Atasan extends Model
{
    use HasFactory;

    protected $table = 'atasan';

    protected $fillable = [
        'user_id',
        'perusahaan_id',
        'nama',
        'email',
        'telepon',
    ];

    /**
     * Relasi ke Institusi / Perusahaan tempat atasan bertugas
     */
    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    /**
     * Relasi ke User Login
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Biodata yang dibawahi oleh atasan ini
     */
    public function biodata(): HasMany
    {
        return $this->hasMany(Biodata::class, 'atasan_id');
    }

    /**
     * Alias plural untuk relasi biodata alumni yang berada di bawah atasan ini.
     *
     * @return HasMany<Biodata, $this>
     */
    public function biodatas(): HasMany
    {
        return $this->biodata();
    }

    /**
     * Relasi evaluasi atasan yang menggunakan profil atasan ini.
     *
     * @return HasMany<EvaluasiAtasan, $this>
     */
    public function evaluasiAtasan(): HasMany
    {
        return $this->hasMany(EvaluasiAtasan::class, 'atasan_id');
    }
}
