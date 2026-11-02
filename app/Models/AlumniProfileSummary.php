<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model AlumniProfileSummary
 *
 * Terhubung ke Database View `v_alumni_profile_summary` yang merangkum data biodata,
 * akademik, yudisium, prodi, fakultas, pekerjaan, dan atasan alumni.
 */
class AlumniProfileSummary extends Model
{
    use HasFactory;

    protected $table = 'v_alumni_profile_summary';

    protected $primaryKey = 'biodata_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'biodata_id' => 'integer',
            'user_id' => 'integer',
            'prodi_id' => 'integer',
            'fakultas_id' => 'integer',
            'perusahaan_id' => 'integer',
            'ipk' => 'float',
            'total_sks' => 'integer',
            'is_profile_complete' => 'boolean',
        ];
    }

    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_id');
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(RefFakultas::class, 'fakultas_id');
    }

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    public function propinsi(): BelongsTo
    {
        return $this->belongsTo(Propinsi::class, 'propinsi_id');
    }

    public function kabupaten(): BelongsTo
    {
        return $this->belongsTo(Kabupaten::class, 'kabupaten_id');
    }
}
