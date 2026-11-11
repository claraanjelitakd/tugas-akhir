<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EvaluasiAtasan Model
 *
 * Mengelola data respon kuesioner evaluasi tingkat kepuasan dan kinerja lulusan dari Atasan/Pengguna Lulusan UKDW.
 */
class EvaluasiAtasan extends Model
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
            'description' => 'Jumlah pegawai, sebaran alumni UKDW, dan standar gaji pertama yang dilaporkan atasan.',
            'source' => 'perusahaan, kuesioner_jawaban',
            'order' => 2,
        ],
        [
            'key' => 'penilaian',
            'title' => 'Penilaian Kesiapan & Kinerja Alumni',
            'description' => 'Kesiapan kerja dan aspek kompetensi alumni yang dinilai oleh atasan.',
            'source' => 'kuesioner_jawaban',
            'order' => 3,
        ],
    ];

    protected $table = 'evaluasi_atasan';

    protected $fillable = [
        'biodata_id',
        'perusahaan_id',
        'atasan_id',
        'token',
        'is_submitted',
        'submitted_at',
    ];

    protected $casts = [
        'is_submitted' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    /**
     * Susun payload aman untuk audit admin tanpa mengekspos token akses survei.
     *
     * Token hanya digunakan di sisi server untuk membuka dan memproses tautan
     * survei publik. Halaman audit cukup menerima status, identitas penilai,
     * instansi, serta jawaban per butir.
     *
     * @return array<string, mixed>
     */
    public function toAdminAuditArray(): array
    {
        $this->loadMissing([
            'atasan',
            'perusahaan.kabupaten',
            'perusahaan.propinsi',
            'biodata.perusahaan.kabupaten',
            'biodata.perusahaan.propinsi',
            'respons.pertanyaan',
            'respons.opsi',
        ]);

        $perusahaan = $this->perusahaan ?? $this->biodata?->perusahaan;
        $karakteristikJawaban = $this->respons
            ->mapWithKeys(static function ($response): array {
                $kode = (string) ($response->kode_pertanyaan ?? $response->pertanyaan?->kode_pertanyaan ?? '');
                $kode = str_starts_with($kode, 'EVA_') ? substr($kode, 4) : $kode;

                return [$kode => $response->jawaban_teks];
            });

        return [
            'id' => $this->id,
            'biodata_id' => $this->biodata_id,
            'is_submitted' => (bool) $this->is_submitted,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_at_formatted' => $this->submitted_at ? $this->submitted_at->translatedFormat('d F Y, H:i').' WIB' : null,
            'status' => $this->is_submitted ? 'Selesai' : 'Aktif',
            'jumlah_pegawai' => $karakteristikJawaban->get('jumlah_pegawai'),
            'jumlah_alumni_ukdw' => $karakteristikJawaban->get('jumlah_alumni_ukdw'),
            'standar_gaji_pertama' => $karakteristikJawaban->get('standar_gaji_pertama'),
            'catatan' => $this->respons->pluck('catatan')->filter()->first() ?? null,
            'atasan' => $this->atasan ? [
                'id' => $this->atasan->id,
                'nama' => $this->atasan->nama,
                'email' => $this->atasan->email,
                'telepon' => $this->atasan->telepon,
            ] : null,
            'perusahaan' => $perusahaan ? [
                'id' => $perusahaan->id,
                'nama_perusahaan' => $perusahaan->nama_perusahaan,
                'alamat' => $perusahaan->alamat,
                'jenis_lokasi' => $perusahaan->jenis_lokasi,
                'kode_negara' => $perusahaan->kode_negara,
                'kode_pos' => $perusahaan->kode_pos,
                'no_telp_fax' => $perusahaan->no_telp_fax,
                'homepage' => $perusahaan->homepage,
                'bentuk_perusahaan' => $perusahaan->bentuk_perusahaan,
                'skala' => $perusahaan->skala,
                'jumlah_pegawai' => $perusahaan->jumlah_pegawai,
                'jenis_perusahaan' => $perusahaan->jenis_perusahaan,
                'jenis_perusahaan_lainnya' => $perusahaan->jenis_perusahaan_lainnya,
                'kabupaten' => $perusahaan->kabupaten ? [
                    'kode_kabupaten' => $perusahaan->kabupaten->kode_kabupaten,
                    'kode_provinsi' => $perusahaan->kabupaten->kode_provinsi,
                    'nama_kabupaten' => $perusahaan->kabupaten->nama_kabupaten,
                ] : null,
                'propinsi' => $perusahaan->propinsi ? [
                    'kode_provinsi' => $perusahaan->propinsi->kode_provinsi,
                    'nama_provinsi' => $perusahaan->propinsi->nama_provinsi,
                ] : null,
            ] : null,
            'respons' => $this->respons->map(static fn ($response): array => [
                'pertanyaan_id' => $response->pertanyaan_id,
                'pertanyaan_kode' => $response->kode_pertanyaan ?? $response->pertanyaan?->kode_pertanyaan,
                'nilai' => $response->jawaban_teks,
                'score' => $response->opsi?->nilai_skor,
                'catatan' => is_array($response->jawaban_json) ? ($response->jawaban_json['catatan'] ?? null) : null,
            ])->values()->all(),
        ];
    }

    /**
     * Relasi ke model Biodata Alumni
     */
    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    /**
     * Relasi ke model Perusahaan
     */
    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    /**
     * Relasi ke model Atasan
     */
    public function atasan(): BelongsTo
    {
        return $this->belongsTo(Atasan::class, 'atasan_id');
    }

    /**
     * Relasi ke jawaban butir dinamis terpadu
     */
    public function respons(): HasMany
    {
        return $this->hasMany(KuesionerJawaban::class, 'evaluasi_atasan_id');
    }
}
