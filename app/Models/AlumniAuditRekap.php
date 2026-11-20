<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model AlumniAuditRekap
 *
 * Terhubung ke Database View `v_alumni_audit_rekap` yang mengagregasikan
 * rekap data profil, progres kuesioner universitas, dan progres kuesioner prodi
 * untuk query berkecepatan tinggi (single-row fetch per alumni) tanpa N+1 query.
 *
 * @property int $biodata_id
 * @property int|null $user_id
 * @property string $nim
 * @property string $nama
 * @property string|null $email
 * @property string|null $nomor_telepon
 * @property int|null $prodi_id
 * @property string|null $nama_prodi
 * @property int|null $fakultas_id
 * @property string|null $nama_fakultas
 * @property int $is_profile_complete
 * @property int $univ_percentage
 * @property int $is_complete_univ
 * @property int $prodi_percentage
 * @property int $is_complete_prodi
 * @property int $is_complete_total
 * @property string $status_tracer_label
 */
class AlumniAuditRekap extends Model
{
    use HasFactory;

    protected $table = 'v_alumni_audit_rekap';

    protected $primaryKey = 'biodata_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'biodata_id' => 'integer',
            'user_id' => 'integer',
            'kode_prodi' => 'string',
            'kode_fakultas' => 'string',
            'ipk' => 'float',
            'total_sks' => 'integer',
            'is_profile_complete' => 'boolean',
            'total_mandatory_univ' => 'integer',
            'answered_mandatory_univ' => 'integer',
            'total_answered_univ' => 'integer',
            'univ_percentage' => 'integer',
            'is_complete_univ' => 'boolean',
            'total_prodi_questions' => 'integer',
            'answered_prodi_questions' => 'integer',
            'prodi_percentage' => 'integer',
            'is_complete_prodi' => 'boolean',
            'is_complete_total' => 'boolean',
        ];
    }

    /**
     * Relasi ke model Biodata asli
     */
    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    /**
     * Relasi ke model User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke model Program Studi
     */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'kode_prodi', 'kode_prodi');
    }

    /**
     * Relasi ke model Fakultas
     */
    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(RefFakultas::class, 'kode_fakultas', 'kode_fakultas');
    }

    /**
     * Scope filter alumni yang telah menyelesaikan seluruh tracer study (100%)
     */
    public function scopeSelesai(Builder $query): Builder
    {
        return $query->where('is_complete_total', 1);
    }

    /**
     * Scope filter alumni yang belum menyelesaikan tracer study (<100%)
     */
    public function scopeBelumSelesai(Builder $query): Builder
    {
        return $query->where('is_complete_total', 0);
    }

    /**
     * Scope filter alumni pada program studi tertentu
     */
    public function scopeProdi(Builder $query, string $prodiId): Builder
    {
        return $query->where('kode_prodi', $prodiId);
    }

    /**
     * Scope filter alumni pada fakultas tertentu
     */
    public function scopeFakultas(Builder $query, string $fakultasId): Builder
    {
        return $query->where('kode_fakultas', $fakultasId);
    }
}
