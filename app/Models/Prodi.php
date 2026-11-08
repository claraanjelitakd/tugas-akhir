<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Prodi
 *
 * Mengelola data master Program Studi di UKDW.
 */
class Prodi extends Model
{
    use HasFactory;

    protected $table = 'prodi';

    protected $fillable = [
        'fakultas_id',
        'kode_prodi',
        'nama_prodi',
        'jenjang',
    ];

    /**
     * Relasi ke Fakultas penaung Program Studi.
     */
    public function fakultas()
    {
        return $this->belongsTo(RefFakultas::class, 'fakultas_id');
    }

    public function biodata()
    {
        return $this->hasMany(Biodata::class, 'prodi_id');
    }

    public function biodatas()
    {
        return $this->biodata();
    }

    public function questionSections()
    {
        return $this->hasMany(KuesionerSection::class, 'prodi_id')->orderBy('order');
    }

    public function questions()
    {
        return $this->hasMany(KuesionerPertanyaan::class, 'pemilik_prodi_id');
    }
}
