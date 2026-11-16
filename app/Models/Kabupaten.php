<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Kabupaten
 *
 * Mengelola data master wilayah kabupaten/kota di Indonesia.
 */
class Kabupaten extends Model
{
    use HasFactory;

    protected $table = 'kabupaten';

    protected $primaryKey = 'kode_kabupaten';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_kabupaten',
        'kode_provinsi',
        'nama_kabupaten',
    ];

    public function propinsi()
    {
        return $this->belongsTo(Propinsi::class, 'kode_provinsi', 'kode_provinsi');
    }

    public function perusahaan()
    {
        return $this->hasMany(Perusahaan::class, 'kode_kabupaten', 'kode_kabupaten');
    }
}
