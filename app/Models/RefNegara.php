<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Master Referensi Negara (ref_negara)
 *
 * Mengelola data master negara seluruh dunia beserta ibu kota, kode ISO 2, dan benua.
 */
class RefNegara extends Model
{
    use HasFactory;

    /**
     * Nama tabel eksplisit singular di basis data.
     */
    protected $table = 'ref_negara';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kode_iso2',
        'kode_iso3',
        'nama_negara',
        'nama_resmi',
        'benua',
        'ibu_kota',
    ];
}
