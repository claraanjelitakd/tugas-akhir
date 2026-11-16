<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Master Referensi Negara (ref_negara)
 *
 * Mengelola data master negara seluruh dunia.
 */
class RefNegara extends Model
{
    use HasFactory;

    /**
     * Nama tabel eksplisit singular di basis data.
     */
    protected $table = 'ref_negara';

    /**
     * Kode ISO-3166-1 alpha-2 adalah satu-satunya identitas negara.
     */
    protected $primaryKey = 'kode_negara';

    /**
     * Kunci negara berbentuk string dan tidak bertambah otomatis.
     */
    public $incrementing = false;

    /**
     * Tipe primary key negara.
     */
    protected $keyType = 'string';

    /**
     * Atribut yang dapat diisi secara massal (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kode_negara',
        'kode_iso3',
        'nama_negara',
        'nama_resmi',
        'benua',
        'ibu_kota',
    ];
}
