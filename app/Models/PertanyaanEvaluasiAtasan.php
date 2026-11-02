<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model PertanyaanEvaluasiAtasan
 *
 * Mengelola daftar butir pertanyaan instrumen kepuasan pengguna lulusan / atasan UKDW yang dapat dikonfigurasi dinamis oleh Superadmin.
 */
class PertanyaanEvaluasiAtasan extends Model
{
    use HasFactory;

    /** @var list<array{key: string, title: string, description: string, source: string, order: int}> */
    public const STEPPER_SECTIONS = [
        [
            'key' => 'instansi',
            'title' => 'Profil Perusahaan & Atasan',
            'description' => 'Identitas perusahaan, alamat, dan atasan langsung yang menilai alumni.',
            'source' => 'perusahaan, atasan, evaluasi_atasan',
            'order' => 1,
        ],
        [
            'key' => 'karakteristik',
            'title' => 'Karakteristik Perusahaan & Sebaran Alumni',
            'description' => 'Bentuk, skala, jumlah pegawai, sebaran alumni UKDW, dan standar gaji pertama.',
            'source' => 'perusahaan, evaluasi_atasan',
            'order' => 2,
        ],
        [
            'key' => 'penilaian',
            'title' => 'Penilaian Kesiapan & Kinerja Alumni',
            'description' => 'Kesiapan kerja dan aspek kompetensi alumni yang dinilai oleh atasan.',
            'source' => 'respon_evaluasi_atasan',
            'order' => 3,
        ],
    ];

    /**
     * Mengembalikan kontrak section stepper yang dipakai admin dan formulir publik.
     *
     * @return list<array{key: string, title: string, description: string, source: string, order: int}>
     */
    public static function stepperSections(): array
    {
        return self::STEPPER_SECTIONS;
    }

    /**
     * Kode pertanyaan yang menyimpan atribut perusahaan/evaluasi, bukan respons Likert.
     *
     * Atribut ini tetap memakai tabel pertanyaan yang sama agar seluruh isi stepper
     * dapat dikelola dari satu layar tanpa menambah tabel metadata kedua.
     *
     * @var list<string>
     */
    public const COMPANY_FIELD_CODES = [
        'bentuk_perusahaan',
        'skala_perusahaan',
        'jumlah_pegawai',
        'jumlah_alumni_ukdw',
        'standar_gaji_pertama',
    ];

    protected $table = 'pertanyaan_evaluasi_atasan';

    protected $fillable = [
        'kode',
        'aspek',
        'deskripsi',
        'kategori',
        'tipe',
        'pilihan_jawaban',
        'is_active',
        'order',
    ];

    /** @var list<string> */
    protected $appends = ['is_company_field', 'langkah'];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'pilihan_jawaban' => 'array',
    ];

    public function respons(): HasMany
    {
        return $this->hasMany(ResponEvaluasiAtasan::class, 'pertanyaan_id');
    }

    /**
     * Menentukan apakah butir ini terhubung langsung ke field profil perusahaan.
     */
    public function getIsCompanyFieldAttribute(): bool
    {
        return in_array($this->kode, self::COMPANY_FIELD_CODES, true);
    }

    /**
     * Mengubah kategori lama menjadi kunci stepper yang stabil di frontend.
     */
    public function getLangkahAttribute(): string
    {
        return match (strtolower(trim((string) $this->kategori))) {
            'profil perusahaan', 'langkah 1', 'instansi' => 'instansi',
            'karakteristik perusahaan', 'langkah 2', 'karakteristik' => 'karakteristik',
            default => 'penilaian',
        };
    }
}
