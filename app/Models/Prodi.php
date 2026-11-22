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

    protected $table = 'ref_prodi';

    protected $primaryKey = 'kode_prodi';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'kode_prodi',
        'kode_fakultas',
        'nama_prodi',
        'jenjang',
    ];

    /**
     * Atribut tambahan virtual untuk kompatibilitas kode antarmuka.
     *
     * @var array<int, string>
     */
    protected $appends = ['id'];

    /**
     * Accessor id untuk menjaga keselarasan dengan kode yang merujuk primary key via id.
     */
    public function getIdAttribute(): string
    {
        return (string) ($this->attributes['kode_prodi'] ?? '');
    }

    /**
     * Relasi ke Fakultas penaung Program Studi.
     */
    public function fakultas()
    {
        return $this->belongsTo(RefFakultas::class, 'kode_fakultas', 'kode_fakultas');
    }

    public function biodata()
    {
        return $this->hasMany(Biodata::class, 'kode_prodi', 'kode_prodi');
    }

    public function biodatas()
    {
        return $this->biodata();
    }

    public function questionSections()
    {
        return $this->hasMany(KuesionerSection::class, 'kode_prodi', 'kode_prodi')->orderBy('order');
    }

    public function questions()
    {
        return $this->hasMany(KuesionerPertanyaan::class, 'pemilik_kode_prodi', 'kode_prodi');
    }
}
