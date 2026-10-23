<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Model Biodata (tabel: biodata)
 *
 * Mengelola data profil aktif alumni untuk sistem Tracer Study UKDW.
 *
 * Kebijakan Integritas & Anti-Duplikasi Data:
 * - Data akademik master (nama, tempat/tanggal lahir, jenis kelamin, agama,
 *   golongan darah, status kelulusan, dan email_students)
 *   tersimpan terpusat di tabel `data_akademik`.
 * - Model Biodata ini menyediakan Accessor dinamis ke `dataAkademik` sehingga kode
 *   aplikasi yang memanggil `$biodata->nama`, `$biodata->tahun_lulus`, dsb. tetap berjalan lancar.
 * - Untuk korespondensi alumni, tabel ini hanya menyimpan `email_pribadi`.
 */
class Biodata extends Model
{
    use HasFactory;

    protected $table = 'biodata';

    /**
     * Bootstrap model events.
     * Otomatisasi: Menghubungkan relasi yudisium, prodi, dan akun user
     * saat biodata baru dibuat hanya berbekal NIM.
     */
    protected static function booted()
    {
        static::creating(function (Biodata $biodata) {
            if ($biodata->nim) {
                // 1. Hubungkan ke data yudisium jika belum terisi
                if (empty($biodata->yudisium_id)) {
                    $biodata->yudisium_id = Yudisium::where('nim', $biodata->nim)->value('id');
                }

                // 2. Hubungkan ke prodi dari parsing NIM jika belum terisi
                if (empty($biodata->prodi_id)) {
                    $parsed = self::parseNim($biodata->nim);
                    if ($parsed) {
                        $biodata->prodi_id = Prodi::where('kode_prodi', $parsed['kode_prodi'])->value('id');
                    }
                }

                // 3. Pastikan Akun Pengguna (User) tersedia jika belum terisi
                if (empty($biodata->user_id)) {
                    $dataAkad = DataAkademik::where('nim', $biodata->nim)->first();
                    $user = User::firstOrCreate(
                        ['username' => $biodata->nim],
                        [
                            'name' => $dataAkad?->nama ?? 'Alumni UKDW',
                            'email' => $dataAkad?->email_pribadi ?? ($biodata->nim.'@alumni.ukdw.ac.id'),
                            'password' => Hash::make(
                                $dataAkad?->tanggal_lahir ? Carbon::parse($dataAkad->tanggal_lahir)->format('dmY') : Str::password(32)
                            ),
                            'role' => 'alumni',
                            'must_change_password' => true,
                        ]
                    );
                    $biodata->user_id = $user->id;
                }
            }
        });

        static::saving(function (Biodata $biodata) {
            // 1. Jika linkedin_username diisi berupa URL utuh, bersihkan menjadi handle murni
            if (! empty($biodata->linkedin_username) && str_contains($biodata->linkedin_username, 'linkedin.com')) {
                $extracted = self::extractLinkedinHandle($biodata->linkedin_username);
                if (! empty($extracted)) {
                    $biodata->linkedin_username = $extracted;
                }
            }

            // 2. Jika linkedin_url tersedia, otomatis sinkronkan linkedin_username jika belum sesuai
            if (! empty($biodata->linkedin_url)) {
                $extracted = self::extractLinkedinHandle($biodata->linkedin_url);
                if (! empty($extracted)) {
                    $biodata->linkedin_username = $extracted;
                }
            }
        });
    }

    /**
     * Helper statis untuk mengekstrak username/handle publik dari URL LinkedIn atau string input.
     */
    public static function extractLinkedinHandle(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $clean = trim($input);

        // Jika input berupa URL LinkedIn (misal https://www.linkedin.com/in/username/?isSelfProfile=false)
        if (preg_match('#linkedin\.com/in/([a-zA-Z0-9\-_%]+)#i', $clean, $matches)) {
            return urldecode(trim($matches[1], '/'));
        }

        // Jika bukan format URL lengkap, bersihkan karakter awalan @ atau trailing slash/query
        if (! str_contains($clean, '/') && ! str_contains($clean, '?') && ! str_contains($clean, '&')) {
            return ltrim($clean, '@');
        }

        return null;
    }

    /**
     * Kolom yang dapat diisi secara massal (Mass Assignable).
     * Telah diaudit bersih dari kolom-kolom yang menduplikasi tabel data_akademik.
     */
    protected $fillable = [
        'user_id',
        'nim',
        'yudisium_id',
        'prodi_id',
        'foto',
        'nomor_telepon',
        'email_pribadi',
        'alamat_saat_ini',
        'kabupaten_id',
        'propinsi_id',
        'kode_pos',
        'instagram_url',
        'facebook_url',
        'linkedin_url',
        'linkedin_username',
        'skills',
        'experience',
        'perusahaan_id',
        'atasan_id',
        'posisi_jabatan',
        'bidang_pekerjaan',
        'kategori_pekerjaan',
        'posisi_wiraswasta',
        'pendidikan_tingkat',
        'perguruan_tinggi',
        'pendidikan_prodi',
        'jenis_pekerjaan',
        'gaji',
    ];

    // =========================================================================
    // RELASI ELOQUENT
    // =========================================================================

    /**
     * Relasi ke Akun Pengguna (User).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Data Akademik Master Mahasiswa (Kunci Penghubung: NIM).
     */
    public function dataAkademik()
    {
        return $this->belongsTo(DataAkademik::class, 'nim', 'nim');
    }

    /**
     * Relasi ke Yudisium (Kelulusan Akademik & Tugas Akhir).
     */
    public function yudisium()
    {
        return $this->belongsTo(Yudisium::class, 'yudisium_id');
    }

    /**
     * Relasi ke Program Studi.
     */
    public function prodi()
    {
        return $this->belongsTo(Prodi::class);
    }

    /**
     * Relasi ke Perusahaan tempat bekerja.
     */
    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    /**
     * Relasi ke Atasan Langsung.
     */
    public function atasan()
    {
        return $this->belongsTo(Atasan::class);
    }

    /**
     * Relasi ke Provinsi Domisili.
     */
    public function propinsi()
    {
        return $this->belongsTo(Propinsi::class, 'propinsi_id');
    }

    /**
     * Relasi ke Kabupaten/Kota Domisili.
     */
    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'kabupaten_id');
    }

    /**
     * Relasi ke Hasil Pengisian Kuesioner Universitas (Tracer).
     */
    public function tracer()
    {
        return $this->hasMany(Tracer::class, 'biodata_id');
    }

    public function tracers()
    {
        return $this->tracer();
    }

    /**
     * Relasi ke Hasil Pengisian Kuesioner Khusus Program Studi.
     */
    public function prodiResponse()
    {
        return $this->hasMany(ProdiResponse::class, 'biodata_id');
    }

    public function prodiResponses()
    {
        return $this->prodiResponse();
    }

    /**
     * Relasi ke Evaluasi Atasan / Pengguna Lulusan Terakhir.
     */
    public function evaluasiAtasan()
    {
        return $this->hasOne(EvaluasiAtasan::class, 'biodata_id')->latestOfMany();
    }

    /**
     * Relasi ke Seluruh Riwayat Evaluasi Atasan.
     */
    public function allEvaluasiAtasan()
    {
        return $this->hasMany(EvaluasiAtasan::class, 'biodata_id');
    }

    /**
     * Relasi ke Log Aktivitas Sinkronisasi LinkedIn Terakhir (Audit Trail).
     */
    public function latestLinkedinSyncLog()
    {
        return $this->hasOne(LogActivity::class, 'model_id')
            ->where('model_type', self::class)
            ->where('action', 'linkedin_sync')
            ->latestOfMany();
    }

    /**
     * Relasi ke Seluruh Hasil Staging Sinkronisasi LinkedIn (Apify).
     */
    public function linkedinSyncResults()
    {
        return $this->hasMany(LinkedinSyncResult::class, 'biodata_id')->orderByDesc('id');
    }

    /**
     * Relasi ke Hasil Staging Sinkronisasi LinkedIn Terakhir.
     */
    public function latestLinkedinSyncResult()
    {
        return $this->hasOne(LinkedinSyncResult::class, 'biodata_id')->latestOfMany();
    }

    /**
     * Relasi ke View LinkedIn Plotting & Validasi Nama
     */
    public function linkedinPlot()
    {
        return $this->hasOne(AlumniLinkedinPlot::class, 'biodata_id');
    }

    /**
     * Relasi ke View Ringkasan Profil Konsolidasi
     */
    public function profileSummary()
    {
        return $this->hasOne(AlumniProfileSummary::class, 'biodata_id');
    }

    /**
     * Relasi ke View Master Rekapitulasi Audit Tracer Study
     */
    public function auditRekap()
    {
        return $this->hasOne(AlumniAuditRekap::class, 'biodata_id');
    }

    // =========================================================================
    // ACCESSOR (DELEGASI DATA MASTER AKADEMIK TANPA DUPLIKASI TABEL)
    // =========================================================================

    /**
     * Akses nama alumni (mengambil langsung dari tabel data_akademik master).
     */
    public function getNamaAttribute(): ?string
    {
        return $this->dataAkademik?->nama ?? $this->user?->name;
    }

    /**
     * Akses email umum (mengutamakan email_pribadi aktif alumni).
     */
    public function getEmailAttribute(): ?string
    {
        return $this->email_pribadi ?? $this->user?->email;
    }

    /**
     * Akses email mahasiswa kampus (mengambil dari data_akademik saja).
     */
    public function getEmailStudentsAttribute(): ?string
    {
        return $this->dataAkademik?->email_students;
    }

    /**
     * Akses tempat lahir (dari data_akademik).
     */
    public function getTempatLahirAttribute(): ?string
    {
        return $this->dataAkademik?->tempat_lahir;
    }

    /**
     * Akses tanggal lahir (dari data_akademik).
     */
    public function getTanggalLahirAttribute(): ?string
    {
        return $this->dataAkademik?->tanggal_lahir;
    }

    /**
     * Akses jenis kelamin (dari data_akademik).
     */
    public function getJenisKelaminAttribute(): ?string
    {
        return $this->dataAkademik?->jenis_kelamin;
    }

    /**
     * Akses golongan darah (dari data_akademik).
     */
    public function getGolonganDarahAttribute(): ?string
    {
        return $this->dataAkademik?->golongan_darah;
    }

    /**
     * Akses agama (dari data_akademik).
     */
    public function getAgamaAttribute(): ?string
    {
        return $this->dataAkademik?->agama;
    }

    /**
     * Akses tahun kelulusan resmi (otomatis mengambil dari data_akademik).
     */
    public function getTahunLulusAttribute(): ?string
    {
        return $this->dataAkademik?->tahun_lulus ? (string) $this->dataAkademik->tahun_lulus : null;
    }

    /**
     * Akses tahun akademik kelulusan (dari data_akademik).
     */
    public function getTahunAkademikLulusAttribute(): ?string
    {
        return $this->dataAkademik?->tahun_akademik_lulus;
    }

    /**
     * Akses status kewarganegaraan (dari data_akademik).
     */
    public function getWargaNegaraAttribute(): string
    {
        return $this->dataAkademik?->warga_negara ?? 'WNI';
    }

    public function getNisnAttribute(): ?string
    {
        return $this->dataAkademik?->nisn;
    }

    public function getNoBpjsAttribute(): ?string
    {
        return $this->dataAkademik?->no_bpjs;
    }

    /**
     * Akses alamat domisili saat ini (dengan fallback ke data_akademik jika belum diisi).
     */
    public function getAlamatSaatIniAttribute(): ?string
    {
        return $this->attributes['alamat_saat_ini'] ?? $this->dataAkademik?->alamat_saat_ini;
    }

    /**
     * Akses kode pos domisili saat ini (dengan fallback ke data_akademik).
     */
    public function getKodePosAttribute(): ?string
    {
        return $this->attributes['kode_pos'] ?? $this->dataAkademik?->kode_pos;
    }

    /**
     * Kode pos kantor / perusahaan diperoleh langsung dari relasi master perusahaan (Zero Duplication).
     */
    public function getZipcodeAttribute(): ?string
    {
        return $this->perusahaan?->kode_pos;
    }

    public function getKelurahanAttribute(): ?string
    {
        return $this->dataAkademik?->kelurahan;
    }

    public function getKecamatanAttribute(): ?string
    {
        return $this->dataAkademik?->kecamatan;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Helper: Ekstrak Angkatan dan Kode Prodi dari NIM.
     */
    public static function parseNim($nim): ?array
    {
        if (strlen((string) $nim) >= 4) {
            $kodeProdi = substr((string) $nim, 0, 2);
            $tahunKode = substr((string) $nim, 2, 2);

            $angkatan = (int) $tahunKode > 50 ? 1900 + (int) $tahunKode : 2000 + (int) $tahunKode;

            return [
                'kode_prodi' => $kodeProdi,
                'angkatan' => $angkatan,
            ];
        }

        return null;
    }
}
