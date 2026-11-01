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

    protected $table = 'evaluasi_atasan';

    protected $fillable = [
        'biodata_id',
        'perusahaan_id',
        'atasan_id',
        'token',
        'is_submitted',
        'submitted_at',
        'jumlah_alumni_ukdw',
        'standar_gaji_pertama',
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
        ]);

        $perusahaan = $this->perusahaan ?? $this->biodata?->perusahaan;

        return [
            'id' => $this->id,
            'biodata_id' => $this->biodata_id,
            'is_submitted' => (bool) $this->is_submitted,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_at_formatted' => $this->submitted_at ? $this->submitted_at->translatedFormat('d F Y, H:i').' WIB' : null,
            'status' => $this->is_submitted ? 'Selesai' : 'Aktif',
            'jumlah_alumni_ukdw' => $this->jumlah_alumni_ukdw,
            'standar_gaji_pertama' => $this->standar_gaji_pertama,
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
                'negara' => $perusahaan->negara,
                'kode_pos' => $perusahaan->kode_pos,
                'no_telp_fax' => $perusahaan->no_telp_fax,
                'homepage' => $perusahaan->homepage,
                'bentuk_perusahaan' => $perusahaan->bentuk_perusahaan,
                'skala' => $perusahaan->skala,
                'jumlah_pegawai' => $perusahaan->jumlah_pegawai,
                'jenis_perusahaan' => $perusahaan->jenis_perusahaan,
                'jenis_perusahaan_lainnya' => $perusahaan->jenis_perusahaan_lainnya,
                'kabupaten' => $perusahaan->kabupaten ? [
                    'id' => $perusahaan->kabupaten->id,
                    'nama_kabupaten' => $perusahaan->kabupaten->nama_kabupaten,
                ] : null,
                'propinsi' => $perusahaan->propinsi ? [
                    'id' => $perusahaan->propinsi->id,
                    'nama_provinsi' => $perusahaan->propinsi->nama_provinsi,
                ] : null,
            ] : null,
            'respons' => $this->respons->map(static fn (ResponEvaluasiAtasan $response): array => [
                'pertanyaan_id' => $response->pertanyaan_id,
                'pertanyaan_kode' => $response->pertanyaan?->kode,
                'nilai' => $response->nilai,
                'catatan' => $response->catatan,
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
     * Relasi ke jawaban butir dinamis
     */
    public function respons(): HasMany
    {
        return $this->hasMany(ResponEvaluasiAtasan::class, 'evaluasi_atasan_id');
    }
}
