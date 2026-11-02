<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Perusahaan
 *
 * Mengelola data master institusi/perusahaan tempat alumni bekerja.
 */
class Perusahaan extends Model
{
    use HasFactory;

    protected $table = 'perusahaan';

    protected $fillable = [
        'nama_perusahaan',
        'propinsi_id',
        'kabupaten_id',
        'alamat',
        'homepage',
        'no_telp_fax',
        'kode_pos',
        'sektor',
        'skala',
        'bentuk_perusahaan',
        'jumlah_pegawai',
        'jenis_perusahaan',
        'jenis_perusahaan_lainnya',
        'jenis_lokasi',
        'negara',
        'status_verifikasi',
        'created_by_user_id',
        'created_by_prodi_id',
    ];

    protected $appends = ['province_id'];

    public function getProvinceIdAttribute()
    {
        return $this->attributes['propinsi_id'] ?? null;
    }

    public function propinsi()
    {
        return $this->belongsTo(Propinsi::class, 'propinsi_id');
    }

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'kabupaten_id');
    }

    public function biodata()
    {
        return $this->hasMany(Biodata::class, 'perusahaan_id');
    }

    public function evaluasiAtasan()
    {
        return $this->hasMany(EvaluasiAtasan::class, 'perusahaan_id');
    }

    public function atasan()
    {
        return $this->hasMany(Atasan::class, 'perusahaan_id');
    }

    public function atasans()
    {
        return $this->atasan();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdProdi()
    {
        return $this->belongsTo(Prodi::class, 'created_by_prodi_id');
    }

    public function creatorProdi()
    {
        return $this->createdProdi();
    }

    public function latestVerificationLog()
    {
        return $this->hasOne(LogActivity::class, 'model_id')
            ->where('model_type', self::class)
            ->whereIn('action', ['ACC_VERIFIKASI_PERUSAHAAN', 'UPDATE_VERIFIKASI_PERUSAHAAN', 'REJECT_PERUSAHAAN', 'GANTI_PERUSAHAAN'])
            ->with('user:id,name,role,username')
            ->latestOfMany();
    }
}
