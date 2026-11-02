<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Yudisium (tabel: yudisium)
 *
 * Mengelola data kelulusan yudisium, tugas akhir/skripsi, dan publikasi ilmiah.
 *
 * Saat referensi menyatakan lulus, aplikasi menyiapkan profil tracer tanpa mengubah
 * data akademik atau data yudisium yang menjadi sumber referensi.
 */
class Yudisium extends Model
{
    use HasFactory;

    protected $table = 'yudisium';

    protected $guarded = ['id'];

    /**
     * Bootstrap model events.
     * Siapkan biodata tracer untuk alumni yang dinyatakan lulus.
     */
    protected static function booted()
    {
        static::saved(function (Yudisium $yudisium) {
            // Ketika referensi yudisium menyatakan lulus, siapkan profil tracer tanpa mengubah sumber akademik.
            if ($yudisium->status_lulus === 'Lulus') {
                // Jika lulus di Yudisium, otomatis masukkan ke Biodata alumni!
                // Seluruh relasi yudisium_id, prodi_id, dan akun user
                // otomatis dirakit langsung oleh Model Biodata::booted (creating hook).
                Biodata::firstOrCreate(['nim' => $yudisium->nim]);
            }
        });
    }

    /**
     * Relasi ke Data Akademik (NIM).
     */
    public function dataAkademik()
    {
        return $this->belongsTo(DataAkademik::class, 'nim', 'nim');
    }

    /**
     * Relasi ke Biodata Alumni.
     */
    public function biodata()
    {
        return $this->hasOne(Biodata::class, 'nim', 'nim');
    }

    // =========================================================================
    // ACCESSOR & MUTATOR UNTUK MENCEGAH BREAKING CHANGE
    // =========================================================================

    /**
     * Kompatibilitas mundur dengan nama lama `proses_yudisium`.
     */
    public function getProsesYudisiumAttribute(): ?string
    {
        return $this->status_lulus ?? 'Belum';
    }

    public function setProsesYudisiumAttribute(?string $value): void
    {
        $this->attributes['status_lulus'] = $value ?? 'Belum';
    }

    /**
     * Akses tahun kelulusan resmi langsung dari relasi DataAkademik.
     */
    public function getTahunLulusAttribute(): ?int
    {
        return $this->dataAkademik?->tahun_lulus;
    }

    /**
     * Akses periode akademik lulus langsung dari relasi DataAkademik.
     */
    public function getTahunAkademikLulusAttribute(): ?string
    {
        return $this->dataAkademik?->tahun_akademik_lulus;
    }
}
