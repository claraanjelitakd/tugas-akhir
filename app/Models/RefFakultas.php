<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model RefFakultas (Tabel: ref_fakultas)
 *
 * Lokasi: app/Models/RefFakultas.php
 * Fungsi: Mengelola data master Fakultas di lingkungan Universitas Kristen Duta Wacana.
 */
class RefFakultas extends Model
{
    use HasFactory;

    protected $table = 'ref_fakultas';

    protected $primaryKey = 'kode_fakultas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'kode_fakultas',
        'nama_fakultas',
    ];

    /**
     * Relasi ke seluruh Program Studi di bawah naungan Fakultas ini.
     */
    public function prodi()
    {
        return $this->hasMany(Prodi::class, 'kode_fakultas', 'kode_fakultas');
    }

    /**
     * Alias plural untuk relasi program studi.
     */
    public function prodis()
    {
        return $this->prodi();
    }
}
