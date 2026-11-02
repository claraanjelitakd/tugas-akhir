<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    /**
     * Relasi ke User Login
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Biodata yang dibawahi oleh atasan ini
     */
    public function biodata()
    {
        return $this->hasMany(Biodata::class, 'atasan_id');
    }

    public function biodatas()
    {
        return $this->biodata();
    }
}
