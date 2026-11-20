<?php

namespace App\Services\Atasan;

use App\Models\Biodata;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Menyediakan direktori relasi alumni dan atasan dari profil alumni.
 *
 * Satu baris direktori mewakili satu alumni. Nama atasan yang sama dapat
 * muncul berulang karena setiap alumni menyimpan input profilnya sendiri.
 */
class AtasanDirectoryQueryService
{
    /**
     * Ambil daftar relasi alumni-atasan, statistik, opsi filter, dan metadata scope.
     *
     * @param  Request  $request  Request halaman beserta search, prodi_id, dan per_page.
     * @param  User  $user  Pengguna admin yang menjadi dasar scope data.
     * @return array<string, mixed> Payload direktori untuk halaman Inertia.
     */
    public function getDirectoryData(Request $request, User $user): array
    {
        $search = trim((string) $request->input('search', ''));
        $companySearch = trim((string) $request->input('company', ''));
        $provinsiDomisili = trim((string) $request->input('provinsi', ''));
        $selectedProdiId = $request->input('kode_prodi');

        $query = $this->buildQuery($user, $search, $selectedProdiId, $companySearch, $provinsiDomisili);
        $matchedRecords = (clone $query)
            ->with([
                'atasan:id,nama,email,telepon,perusahaan_id',
                'atasan.perusahaan:id,nama_perusahaan',
                'perusahaan:id,nama_perusahaan',
                'evaluasiAtasan',
            ])
            ->get(['id', 'atasan_id', 'perusahaan_id', 'kode_provinsi', 'kode_kabupaten']);

        $perPage = min(max((int) $request->input('per_page', 10), 10), 50);
        $paginator = $query
            ->with($this->directoryRelations())
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection($paginator->getCollection()->map(
            fn (Biodata $biodata): array => $this->formatRecord($biodata)
        ));

        $prodisQuery = Prodi::query()->orderBy('kode_prodi');
        if ($user->role === 'admin_prodi') {
            $prodisQuery->whereKey($user->kode_prodi);
        } elseif ($user->role === 'admin_fakultas') {
            $prodisQuery->where('kode_fakultas', $user->kode_fakultas);
        }

        return [
            'atasans' => $paginator,
            'prodis' => $prodisQuery->get(['kode_prodi', 'nama_prodi']),
            'provinces' => Propinsi::query()->orderBy('nama_provinsi')->get(['kode_provinsi', 'nama_provinsi']),
            'filters' => [
                'search' => $search,
                'company' => $companySearch,
                'provinsi' => $provinsiDomisili,
                'kode_prodi' => $selectedProdiId ?: '',
                'per_page' => $perPage,
            ],
            'stats' => $this->buildStats($matchedRecords),
        ];
    }

    /**
     * Bentuk kueri direktori relasi profil alumni dan atasan.
     *
     * @param  User  $user  Pengguna admin pemilik scope.
     * @param  string  $search  Teks pencarian bebas.
     * @param  mixed  $selectedProdiId  ID prodi dari filter atau null.
     * @return Builder<Biodata> Kueri profil alumni yang memiliki atasan.
     */
    private function buildQuery(User $user, string $search, mixed $selectedProdiId, string $companySearch = '', string $provinsiDomisili = ''): Builder
    {
        $query = Biodata::query()->whereNotNull('atasan_id');

        $this->applyRoleScope($query, $user);

        if ($user->role !== 'admin_prodi' && filled($selectedProdiId) && $selectedProdiId !== 'all') {
            $query->where('kode_prodi', $selectedProdiId);
        }

        if ($companySearch !== '') {
            $query->where(function (Builder $q) use ($companySearch): void {
                $q->whereHas('perusahaan', fn (Builder $cq) => $cq->where('nama_perusahaan', 'like', "%{$companySearch}%"))
                    ->orWhereHas('atasan.perusahaan', fn (Builder $cq) => $cq->where('nama_perusahaan', 'like', "%{$companySearch}%"));
            });
        }

        if ($provinsiDomisili === 'LUAR_NEGERI' || $provinsiDomisili === 'Luar Negeri') {
            $query->where(function (Builder $q): void {
                $q->whereHas('perusahaan', function (Builder $cq): void {
                    $cq->where('jenis_lokasi', 'Luar Negeri')
                        ->orWhere(function (Builder $sq): void {
                            $sq->whereNotNull('kode_negara')->where('kode_negara', '!=', 'ID');
                        });
                })->orWhereHas('atasan.perusahaan', function (Builder $cq): void {
                    $cq->where('jenis_lokasi', 'Luar Negeri')
                        ->orWhere(function (Builder $sq): void {
                            $sq->whereNotNull('kode_negara')->where('kode_negara', '!=', 'ID');
                        });
                });
            });
        } elseif ($provinsiDomisili !== '' && $provinsiDomisili !== 'all') {
            $query->where('kode_provinsi', $provinsiDomisili);
        }

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('nim', 'like', "%{$search}%")
                    ->orWhereHas('dataAkademik', fn (Builder $academicQuery): Builder => $academicQuery->where('nama', 'like', "%{$search}%"))
                    ->orWhereHas('atasan', function (Builder $atasanQuery) use ($search): void {
                        $atasanQuery
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('telepon', 'like', "%{$search}%");
                    })
                    ->orWhereHas('atasan.perusahaan', fn (Builder $companyQuery): Builder => $companyQuery->where('nama_perusahaan', 'like', "%{$search}%"))
                    ->orWhereHas('perusahaan', fn (Builder $companyQuery): Builder => $companyQuery->where('nama_perusahaan', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    /**
     * Terapkan scope admin prodi atau admin fakultas pada profil alumni.
     *
     * @param  Builder<Biodata>  $query  Kueri profil alumni yang akan dibatasi.
     * @param  User  $user  Pengguna admin pemilik scope.
     */
    private function applyRoleScope(Builder $query, User $user): void
    {
        if ($user->role === 'admin_prodi') {
            abort_if(! $user->kode_prodi, 403, 'Akun Anda belum dikaitkan dengan Program Studi.');
            $query->where('kode_prodi', $user->kode_prodi);
        }

        if ($user->role === 'admin_fakultas') {
            abort_if(! $user->kode_fakultas, 403, 'Akun Anda belum dikaitkan dengan Fakultas.');
            $query->whereHas('prodi', fn (Builder $prodiQuery): Builder => $prodiQuery->where('kode_fakultas', $user->kode_fakultas));
        }
    }

    /**
     * Daftar relasi yang dibutuhkan untuk satu baris relasi alumni-atasan.
     *
     * @return array<int, string> Relasi Eloquent untuk eager loading.
     */
    private function directoryRelations(): array
    {
        return [
            'atasan:id,nama,email,telepon,perusahaan_id',
            'atasan.perusahaan:id,nama_perusahaan,bentuk_perusahaan,skala,jumlah_pegawai',
            'perusahaan:id,nama_perusahaan,bentuk_perusahaan,skala,jumlah_pegawai',
            'dataAkademik:nim,nama,tahun_lulus',
            'prodi:kode_prodi,nama_prodi',
            'propinsi:kode_provinsi,nama_provinsi',
            'kabupaten:kode_kabupaten,nama_kabupaten',
            'evaluasiAtasan.respons.pertanyaan',
            'evaluasiAtasan.respons.opsi',
        ];
    }

    /**
     * Ubah satu profil alumni menjadi payload master Data Atasan lengkap dengan seluruh isi evaluasi.
     *
     * @param  Biodata  $biodata  Profil alumni dengan relasi atasan yang sudah dimuat.
     * @return array<string, mixed> Data satu relasi alumni-atasan.
     */
    private function formatRecord(Biodata $biodata): array
    {
        $company = $biodata->atasan?->perusahaan ?? $biodata->perusahaan;
        $evaluation = $biodata->evaluasiAtasan;

        $profilKarakteristik = [];
        $kinerjaKompetensi = [];
        $kesiapanKerja = null;
        $saranCatatan = null;

        if ($evaluation && $evaluation->is_submitted) {
            foreach ($evaluation->respons as $resp) {
                if (! empty($resp->catatan) && ! $saranCatatan) {
                    $saranCatatan = trim((string) $resp->catatan);
                }

                $p = $resp->pertanyaan;
                if (! $p) {
                    continue;
                }

                $kode = (string) ($p->kode_pertanyaan ?? '');
                $cleanKode = str_starts_with($kode, 'EVA_') ? substr($kode, 4) : $kode;
                $jawaban = trim((string) ($resp->jawaban_teks ?? ''));
                $skor = $resp->opsi?->nilai_skor;

                if (in_array($cleanKode, ['bentuk_perusahaan', 'skala_perusahaan', 'jumlah_pegawai', 'jumlah_alumni_ukdw', 'standar_gaji_pertama'], true)) {
                    $profilKarakteristik[$cleanKode] = [
                        'kode' => $cleanKode,
                        'pertanyaan' => $p->teks_pertanyaan,
                        'jawaban' => $jawaban,
                    ];
                } elseif ($cleanKode === 'tingkat_kesiapan_kerja') {
                    $kesiapanKerja = [
                        'pertanyaan' => $p->teks_pertanyaan,
                        'jawaban' => $jawaban,
                    ];
                } elseif ($p->tipe === 'likert_5') {
                    $kinerjaKompetensi[] = [
                        'kode' => $cleanKode,
                        'pertanyaan' => $p->teks_pertanyaan,
                        'jawaban' => $jawaban,
                        'skor' => $skor,
                    ];
                }
            }
        }

        return [
            'id' => $biodata->id,
            'alumni' => [
                'id' => $biodata->id,
                'nim' => $biodata->nim,
                'nama' => $biodata->dataAkademik?->nama ?? $biodata->nama ?? $biodata->nim,
                'prodi' => $biodata->prodi?->nama_prodi,
                'tahun_lulus' => $biodata->dataAkademik?->tahun_lulus,
                'domisili' => $biodata->kabupaten
                    ? "{$biodata->kabupaten->nama_kabupaten}, {$biodata->propinsi?->nama_provinsi}"
                    : ($biodata->propinsi?->nama_provinsi ?? (($company?->jenis_lokasi === 'Luar Negeri' || ($company?->kode_negara && $company?->kode_negara !== 'ID')) ? 'Luar Negeri' : null)),
                'kode_provinsi' => $biodata->kode_provinsi,
            ],
            'atasan' => $biodata->atasan ? [
                'id' => $biodata->atasan->id,
                'perusahaan_id' => $biodata->atasan->perusahaan_id,
                'nama' => $biodata->atasan->nama,
                'email' => $biodata->atasan->email,
                'telepon' => $biodata->atasan->telepon,
            ] : null,
            'perusahaan' => $company ? [
                'id' => $company->id,
                'nama' => $company->nama_perusahaan,
                'bentuk_perusahaan' => $company->bentuk_perusahaan,
                'skala' => $company->skala,
            ] : null,
            'diisi_oleh' => 'Alumni',
            'sumber_data' => 'Profil alumni',
            'evaluasi' => [
                'status' => ! $evaluation
                    ? 'Belum ada evaluasi'
                    : ($evaluation->is_submitted ? 'Selesai' : 'Menunggu'),
                'submitted_at' => $evaluation?->submitted_at?->toIso8601String(),
                'is_submitted' => (bool) ($evaluation?->is_submitted),
                'bentuk_perusahaan' => $profilKarakteristik['bentuk_perusahaan']['jawaban'] ?? $company?->bentuk_perusahaan ?? null,
                'skala_perusahaan' => $profilKarakteristik['skala_perusahaan']['jawaban'] ?? $company?->skala ?? null,
                'jumlah_pegawai' => $profilKarakteristik['jumlah_pegawai']['jawaban'] ?? $company?->jumlah_pegawai ?? null,
                'jumlah_alumni_ukdw' => $profilKarakteristik['jumlah_alumni_ukdw']['jawaban'] ?? null,
                'standar_gaji_pertama' => $profilKarakteristik['standar_gaji_pertama']['jawaban'] ?? null,
                'kesiapan_kerja' => $kesiapanKerja['jawaban'] ?? null,
                'saran' => $saranCatatan,
                'karakteristik' => array_values($profilKarakteristik),
                'kompetensi' => $kinerjaKompetensi,
            ],
        ];
    }

    /**
     * Hitung statistik berdasarkan seluruh relasi yang sesuai scope dan filter.
     *
     * @param  Collection<int, Biodata>  $records  Profil alumni hasil query ringkasan.
     * @return array<string, int> Statistik master Data Atasan.
     */
    private function buildStats(Collection $records): array
    {
        $atasanIds = $records->pluck('atasan_id')->filter()->unique();
        $companyIds = $records
            ->flatMap(fn (Biodata $record): Collection => collect([$record->atasan?->perusahaan_id, $record->perusahaan_id]))
            ->filter()
            ->unique();

        return [
            'total_data_atasan' => $records->count(),
            'alumni_terhubung' => $records->pluck('id')->unique()->count(),
            'atasan_unik' => $atasanIds->count(),
            'perusahaan_terhubung' => $companyIds->count(),
            'evaluasi_selesai' => $records->filter(fn (Biodata $record): bool => (bool) $record->evaluasiAtasan?->is_submitted)->count(),
        ];
    }
}
