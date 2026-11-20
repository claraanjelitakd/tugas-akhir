<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model AlumniTracerProdiStatus
 *
 * Terhubung ke Database View `v_alumni_tracer_prodi_status` yang mengagregasikan
 * jumlah pertanyaan kuesioner program studi yang sudah dijawab vs total pertanyaan prodi per alumni.
 */
class AlumniTracerProdiStatus extends Model
{
    use HasFactory;

    protected $table = 'v_alumni_tracer_prodi_status';

    protected $primaryKey = 'biodata_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'biodata_id' => 'integer',
            'kode_prodi' => 'string',
            'total_prodi_questions' => 'integer',
            'answered_prodi_questions' => 'integer',
            'prodi_percentage' => 'integer',
            'is_complete_prodi' => 'boolean',
        ];
    }

    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'kode_prodi', 'kode_prodi');
    }
}
