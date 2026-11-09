<?php

namespace App\Services\Atasan;

use App\Models\Biodata;
use App\Models\Prodi;
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
        $selectedProdiId = $request->input('prodi_id');
        $query = $this->buildQuery($user, $search, $selectedProdiId);
        $matchedRecords = (clone $query)
            ->with([
                'atasan:id,nama,email,telepon,perusahaan_id',
                'atasan.perusahaan:id,nama_perusahaan',
                'perusahaan:id,nama_perusahaan',
                'evaluasiAtasan',
            ])
            ->get(['id', 'atasan_id', 'perusahaan_id']);

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
            $prodisQuery->whereKey($user->prodi_id);
        } elseif ($user->role === 'admin_fakultas') {
            $prodisQuery->where('fakultas_id', $user->fakultas_id);
        }

        return [
            'atasans' => $paginator,
            'prodis' => $prodisQuery->get(['id', 'kode_prodi', 'nama_prodi']),
            'filters' => [
                'search' => $search,
                'prodi_id' => $selectedProdiId ?: '',
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
    private function buildQuery(User $user, string $search, mixed $selectedProdiId): Builder
    {
        $query = Biodata::query()->whereNotNull('atasan_id');

        $this->applyRoleScope($query, $user);

        if ($user->role !== 'admin_prodi' && filled($selectedProdiId) && $selectedProdiId !== 'all') {
            $query->where('prodi_id', $selectedProdiId);
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
            abort_if(! $user->prodi_id, 403, 'Akun Anda belum dikaitkan dengan Program Studi.');
            $query->where('prodi_id', $user->prodi_id);
        }

        if ($user->role === 'admin_fakultas') {
            abort_if(! $user->fakultas_id, 403, 'Akun Anda belum dikaitkan dengan Fakultas.');
            $query->whereHas('prodi', fn (Builder $prodiQuery): Builder => $prodiQuery->where('fakultas_id', $user->fakultas_id));
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
            'atasan.perusahaan:id,nama_perusahaan',
            'perusahaan:id,nama_perusahaan',
            'dataAkademik:nim,nama,tahun_lulus',
            'prodi:id,nama_prodi',
            'evaluasiAtasan',
        ];
    }

    /**
     * Ubah satu profil alumni menjadi payload master Data Atasan.
     *
     * @param  Biodata  $biodata  Profil alumni dengan relasi atasan yang sudah dimuat.
     * @return array<string, mixed> Data satu relasi alumni-atasan.
     */
    private function formatRecord(Biodata $biodata): array
    {
        $company = $biodata->atasan?->perusahaan ?? $biodata->perusahaan;
        $evaluation = $biodata->evaluasiAtasan;

        return [
            'id' => $biodata->id,
            'alumni' => [
                'id' => $biodata->id,
                'nim' => $biodata->nim,
                'nama' => $biodata->dataAkademik?->nama ?? $biodata->nama ?? $biodata->nim,
                'prodi' => $biodata->prodi?->nama_prodi,
                'tahun_lulus' => $biodata->dataAkademik?->tahun_lulus,
            ],
            'atasan' => $biodata->atasan ? [
                'id' => $biodata->atasan->id,
                'nama' => $biodata->atasan->nama,
                'email' => $biodata->atasan->email,
                'telepon' => $biodata->atasan->telepon,
            ] : null,
            'perusahaan' => $company ? [
                'id' => $company->id,
                'nama' => $company->nama_perusahaan,
            ] : null,
            'diisi_oleh' => 'Alumni',
            'sumber_data' => 'Profil alumni',
            'evaluasi' => [
                'status' => ! $evaluation
                    ? 'Belum ada evaluasi'
                    : ($evaluation->is_submitted ? 'Selesai' : 'Menunggu'),
                'submitted_at' => $evaluation?->submitted_at?->toIso8601String(),
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
