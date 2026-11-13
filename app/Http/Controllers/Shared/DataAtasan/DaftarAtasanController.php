<?php

namespace App\Http\Controllers\Shared\DataAtasan;

use App\Http\Controllers\Controller;
use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\Atasan\AtasanDirectoryQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DaftarAtasanController
 *
 * Menampilkan master Data Atasan yang dipakai bersama oleh seluruh admin
 * dengan scope data mengikuti relasi alumni dan evaluasi atasan.
 */
class DaftarAtasanController extends Controller
{
    /**
     * Tampilkan direktori Data Atasan.
     *
     * @param  Request  $request  Request HTTP terautentikasi.
     * @param  AtasanDirectoryQueryService  $directoryService  Service kueri master atasan.
     * @return Response Halaman Inertia Data Atasan.
     */
    public function index(Request $request, AtasanDirectoryQueryService $directoryService): Response
    {
        /** @var User $user */
        $user = $request->user();
        $role = (string) $user->role;
        $prefix = match ($role) {
            'admin_prodi' => 'prodi',
            'admin_fakultas' => 'fakultas',
            'admin_biro3' => 'biro3',
            default => 'superadmin',
        };
        $roleTitle = match ($role) {
            'admin_prodi' => 'Admin Program Studi',
            'admin_fakultas' => 'Admin Fakultas',
            'admin_biro3' => 'Biro 3 Kemahasiswaan',
            default => 'Super Administrator',
        };
        $directoryData = $directoryService->getDirectoryData($request, $user);

        $masterOptions = $role === 'superadmin'
            ? [
                'alumni' => Biodata::query()
                    ->with(['dataAkademik:nim,nama,tahun_lulus', 'prodi:id,kode_prodi,nama_prodi'])
                    ->orderBy('nim')
                    ->get(['id', 'nim', 'prodi_id', 'atasan_id'])
                    ->map(fn (Biodata $biodata): array => [
                        'id' => $biodata->id,
                        'nim' => $biodata->nim,
                        'nama' => $biodata->dataAkademik?->nama ?? $biodata->nama ?? $biodata->nim,
                        'prodi' => $biodata->prodi?->nama_prodi,
                        'has_atasan' => (bool) $biodata->atasan_id,
                    ])
                    ->values()
                    ->all(),
                'perusahaan' => Perusahaan::query()
                    ->where('status_verifikasi', 'Terverifikasi')
                    ->orderBy('nama_perusahaan')
                    ->get(['id', 'nama_perusahaan'])
                    ->all(),
            ]
            : ['alumni' => [], 'perusahaan' => []];

        return Inertia::render('Shared/DataAtasan/Index', [
            'user' => $user,
            'role' => $role,
            'roleTitle' => $roleTitle,
            'prefix' => $prefix,
            'scopeLabel' => $this->resolveScopeLabel($user),
            'showProdiFilter' => $role !== 'admin_prodi',
            'masterOptions' => $masterOptions,
            ...$directoryData,
        ]);
    }

    /**
     * Membuat atau memperbarui relasi atasan untuk satu profil alumni.
     *
     * @param  Request  $request  Request berisi alumni, perusahaan, dan kontak atasan.
     * @return RedirectResponse Pengalihan kembali ke direktori Data Atasan.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'superadmin', 403);

        $validated = $request->validate([
            'biodata_id' => 'required|integer|exists:biodata,id',
            'perusahaan_id' => 'nullable|integer|exists:perusahaan,id',
            'nama' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'telepon' => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($validated): void {
            $biodata = Biodata::query()->findOrFail($validated['biodata_id']);
            $atasan = $biodata->atasan_id
                ? Atasan::query()->find($biodata->atasan_id)
                : null;

            $attributes = [
                'perusahaan_id' => $validated['perusahaan_id'] ?? $biodata->perusahaan_id,
                'nama' => $validated['nama'],
                'email' => $validated['email'] ?? null,
                'telepon' => $validated['telepon'] ?? null,
            ];

            if ($atasan) {
                $atasan->update($attributes);
            } else {
                $atasan = Atasan::query()->create($attributes);
                $biodata->update(['atasan_id' => $atasan->id]);
            }
        });

        return redirect()->back()->with('success', 'Data atasan berhasil disimpan dan dihubungkan dengan profil alumni.');
    }

    /**
     * Memperbarui data kontak dan perusahaan pada master atasan.
     *
     * @param  Request  $request  Request berisi atribut atasan yang diperbarui.
     * @param  int  $id  ID data atasan.
     * @return RedirectResponse Pengalihan kembali ke direktori Data Atasan.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->role === 'superadmin', 403);

        $validated = $request->validate([
            'perusahaan_id' => 'nullable|integer|exists:perusahaan,id',
            'nama' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'telepon' => 'nullable|string|max:20',
        ]);

        Atasan::query()->findOrFail($id)->update($validated);

        return redirect()->back()->with('success', 'Data atasan berhasil diperbarui.');
    }

    /**
     * Bentuk label scope aktif untuk konteks halaman.
     *
     * @param  User  $user  Pengguna admin aktif.
     * @return string Label scope data yang sedang ditampilkan.
     */
    private function resolveScopeLabel(User $user): string
    {
        $user->loadMissing(['prodi', 'fakultas']);

        return match ($user->role) {
            'admin_prodi' => $user->prodi?->nama_prodi ?? 'Program Studi',
            'admin_fakultas' => $user->fakultas?->nama_fakultas ?? 'Fakultas',
            default => 'Seluruh universitas',
        };
    }
}
