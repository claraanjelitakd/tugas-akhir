<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model AlumniLinkedinPlot
 *
 * Terhubung ke Database View `v_alumni_linkedin_plot` yang memetakan data staging
 * scraping LinkedIn (linkedin_sync_results) berdampingan langsung dengan profil aktual di biodata,
 * memverifikasi kesesuaian nama alumni dengan profil LinkedIn, dan mendeteksi apakah data
 * pekerjaan/perusahaan sudah terplot atau siap diplot.
 *
 * @property int $biodata_id
 * @property int|null $user_id
 * @property string $nim
 * @property string $nama_alumni
 * @property int|null $prodi_id
 * @property string|null $kode_prodi
 * @property string|null $nama_prodi
 * @property string|null $linkedin_url_biodata
 * @property string|null $linkedin_username_biodata
 * @property string|null $posisi_biodata
 * @property int|null $perusahaan_id_biodata
 * @property string|null $perusahaan_biodata
 * @property string|null $skills_biodata
 * @property string|null $experience_biodata
 * @property int|null $fakultas_id
 * @property string|null $kode_fakultas
 * @property string|null $nama_fakultas
 * @property string|null $tahun_lulus
 * @property string|null $tahun_akademik_lulus
 * @property int|null $sync_result_id
 * @property string|null $sync_status
 * @property string|null $scraped_at
 * @property string|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $reviewer_name
 * @property string|null $linkedin_url_scraped
 * @property string|null $linkedin_username_scraped
 * @property string|null $nama_linkedin
 * @property string|null $posisi_linkedin
 * @property string|null $perusahaan_linkedin
 * @property string|null $lokasi_linkedin
 * @property bool $is_nama_sesuai
 * @property string $keterangan_nama
 * @property bool $is_terplot_ke_biodata
 * @property string $status_plot_label
 */
class AlumniLinkedinPlot extends Model
{
    use HasFactory;

    protected $table = 'v_alumni_linkedin_plot';

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
            'perusahaan_id_biodata' => 'integer',
            'sync_result_id' => 'integer',
            'reviewed_by' => 'integer',
            'is_nama_sesuai' => 'boolean',
            'is_terplot_ke_biodata' => 'boolean',
            'scraped_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id_biodata');
    }

    public function syncResult(): BelongsTo
    {
        return $this->belongsTo(LinkedinSyncResult::class, 'sync_result_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Fallback accessor untuk nama LinkedIn jika actor baru menggunakan skema berbeda (firstName + lastName).
     */
    public function getNamaLinkedinAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        return $this->syncResult?->preview['name'] ?? null;
    }

    /**
     * Fallback accessor untuk posisi jabatan LinkedIn jika actor baru menggunakan skema berbeda (currentPosition/experience).
     */
    public function getPosisiLinkedinAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        return $this->syncResult?->preview['current_job'] ?? null;
    }

    /**
     * Fallback accessor untuk nama perusahaan LinkedIn jika actor baru menggunakan skema berbeda (currentPosition/experience).
     */
    public function getPerusahaanLinkedinAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        return $this->syncResult?->preview['current_company'] ?? null;
    }

    /**
     * Fallback accessor untuk display lokasi LinkedIn jika actor baru menggunakan skema berbeda (location.parsed.text).
     */
    public function getLokasiLinkedinAttribute(?string $value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        return $this->syncResult?->preview['location'] ?? null;
    }

    /**
     * Scope filter alumni yang namanya sesuai dengan profil LinkedIn
     */
    public function scopeNamaSesuai(Builder $query): Builder
    {
        return $query->where('is_nama_sesuai', true);
    }

    /**
     * Scope filter alumni yang namanya berbeda (perlu verifikasi)
     */
    public function scopeNamaBerbeda(Builder $query): Builder
    {
        return $query->where('is_nama_sesuai', false)->whereNotNull('sync_result_id');
    }

    /**
     * Scope filter alumni yang datanya sudah terplot (Approved / Sesuai)
     */
    public function scopeTerplot(Builder $query): Builder
    {
        return $query->where('is_terplot_ke_biodata', true);
    }

    /**
     * Scope filter hasil sinkronisasi yang masih pending review
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('sync_status', 'pending');
    }

    /**
     * Scope filter data yang sama / siap diplot ke biodata
     */
    public function scopeReadyToPlot(Builder $query): Builder
    {
        return $query->where('status_plot_label', 'Data Sama / Siap Diplot');
    }
}
