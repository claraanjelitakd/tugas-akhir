<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Propinsi
 *
 * Mengelola data master wilayah tingkat provinsi di Indonesia.
 */
class Propinsi extends Model
{
    use HasFactory;

    protected $table = 'propinsi';

    protected $primaryKey = 'kode_provinsi';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kode_provinsi', 'nama_provinsi'];

    public function kabupaten()
    {
        return $this->hasMany(Kabupaten::class, 'kode_provinsi', 'kode_provinsi');
    }

    public function perusahaan()
    {
        return $this->hasMany(Perusahaan::class, 'kode_provinsi', 'kode_provinsi');
    }

    public function ump()
    {
        return $this->hasOne(Ump::class, 'kode_provinsi', 'kode_provinsi')->latestOfMany('tahun');
    }

    public function umps()
    {
        return $this->hasMany(Ump::class, 'kode_provinsi', 'kode_provinsi');
    }
}
