<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'kode_provinsi',
        'kode_kabupaten',
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
        'kode_negara',
        'status_verifikasi',
        'created_by_user_id',
        'created_by_prodi_id',
    ];

    public function propinsi()
    {
        return $this->belongsTo(Propinsi::class, 'kode_provinsi', 'kode_provinsi');
    }

    /**
     * Resolve the country master row through the canonical natural key.
     */
    public function negaraMaster(): BelongsTo
    {
        return $this->belongsTo(RefNegara::class, 'kode_negara', 'kode_negara');
    }

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'kode_kabupaten', 'kode_kabupaten');
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
