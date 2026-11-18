<?php

namespace App\Http\Controllers\Shared\DataAtasan;

use App\Http\Controllers\Controller;
use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Models\User;
use App\Services\Atasan\AtasanDirectoryQueryService;
use Illuminate\Http\JsonResponse;
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

        $masterOptions = [
            'alumni' => Biodata::query()
                ->when($role === 'admin_prodi', fn ($q) => $q->where('prodi_id', $user->prodi_id))
                ->when($role === 'admin_fakultas', fn ($q) => $q->whereHas('prodi', fn ($pq) => $pq->where('fakultas_id', $user->fakultas_id)))
                ->with(['dataAkademik:nim,nama,tahun_lulus', 'prodi:id,kode_prodi,nama_prodi'])
                ->orderBy('nim')
                ->get(['id', 'nim', 'prodi_id', 'atasan_id', 'perusahaan_id'])
                ->map(fn (Biodata $biodata): array => [
                    'id' => $biodata->id,
                    'nim' => $biodata->nim,
                    'nama' => $biodata->dataAkademik?->nama ?? $biodata->nama ?? $biodata->nim,
                    'prodi' => $biodata->prodi?->nama_prodi,
                    'tahun_lulus' => $biodata->dataAkademik?->tahun_lulus,
                    'has_atasan' => (bool) $biodata->atasan_id,
                    'perusahaan_id' => $biodata->perusahaan_id,
                ])
                ->values()
                ->all(),
            'perusahaan' => Perusahaan::query()
                ->orderBy('nama_perusahaan')
                ->get(['id', 'nama_perusahaan', 'status_verifikasi', 'bentuk_perusahaan', 'kode_provinsi', 'kode_kabupaten', 'alamat'])
                ->all(),
            'propinsi' => Propinsi::query()->orderBy('nama_provinsi')->get(['kode_provinsi', 'nama_provinsi'])->all(),
            'kabupaten' => Kabupaten::query()->orderBy('nama_kabupaten')->get(['kode_kabupaten', 'kode_provinsi', 'nama_kabupaten'])->all(),
            'negara' => RefNegara::query()->where('kode_negara', '!=', 'ID')->orderBy('nama_negara')->get(['kode_negara', 'nama_negara', 'benua'])->all(),
        ];

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
        $user = $request->user();
        abort_unless(in_array($user?->role, ['superadmin', 'admin_biro3', 'admin_fakultas', 'admin_prodi'], true), 403);

        $validated = $request->validate([
            'biodata_id' => 'required|integer|exists:biodata,id',
            'perusahaan_id' => 'nullable|integer|exists:perusahaan,id',
            'nama' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'telepon' => 'nullable|string|max:20',
        ]);

        $biodata = Biodata::query()->findOrFail($validated['biodata_id']);
        if ($user->role === 'admin_prodi' && (int) $biodata->prodi_id !== (int) $user->prodi_id) {
            abort(403, 'Anda tidak berwenang mengelola data atasan alumni di luar program studi Anda.');
        }
        if ($user->role === 'admin_fakultas') {
            $fakultasProdiIds = Prodi::where('fakultas_id', $user->fakultas_id)->pluck('id')->all();
            abort_unless(in_array((int) $biodata->prodi_id, $fakultasProdiIds, true), 403, 'Anda tidak berwenang mengelola data atasan alumni di luar fakultas Anda.');
        }

        DB::transaction(function () use ($validated, $biodata): void {
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

            if (! empty($validated['perusahaan_id']) && $biodata->perusahaan_id !== (int) $validated['perusahaan_id']) {
                $biodata->update(['perusahaan_id' => $validated['perusahaan_id']]);
            }

            if ($biodata->evaluasiAtasan) {
                $biodata->evaluasiAtasan->update([
                    'atasan_id' => $atasan->id,
                    'perusahaan_id' => $atasan->perusahaan_id,
                ]);
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
        $user = $request->user();
        abort_unless(in_array($user?->role, ['superadmin', 'admin_biro3', 'admin_fakultas', 'admin_prodi'], true), 403);

        $validated = $request->validate([
            'perusahaan_id' => 'nullable|integer|exists:perusahaan,id',
            'nama' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'telepon' => 'nullable|string|max:20',
        ]);

        $atasan = Atasan::query()->findOrFail($id);

        if ($user->role === 'admin_prodi') {
            $hasAccess = Biodata::where('atasan_id', $atasan->id)->where('prodi_id', $user->prodi_id)->exists();
            abort_unless($hasAccess, 403, 'Anda tidak berwenang mengelola data atasan alumni di luar program studi Anda.');
        } elseif ($user->role === 'admin_fakultas') {
            $fakultasProdiIds = Prodi::where('fakultas_id', $user->fakultas_id)->pluck('id')->all();
            $hasAccess = Biodata::where('atasan_id', $atasan->id)->whereIn('prodi_id', $fakultasProdiIds)->exists();
            abort_unless($hasAccess, 403, 'Anda tidak berwenang mengelola data atasan alumni di luar fakultas Anda.');
        }

        $atasan->update($validated);

        if (! empty($validated['perusahaan_id'])) {
            Biodata::query()->where('atasan_id', $atasan->id)->update([
                'perusahaan_id' => $validated['perusahaan_id'],
            ]);
        }

        return redirect()->back()->with('success', 'Data atasan berhasil diperbarui.');
    }

    /**
     * Menambahkan instansi / perusahaan baru dari form data atasan dengan status Menunggu Verifikasi.
     *
     * @param  Request  $request  Request berisi atribut perusahaan baru.
     */
    public function storePerusahaan(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($request->user()?->role, ['superadmin', 'admin_biro3', 'admin_fakultas', 'admin_prodi'], true), 403);

        $jenisLokasi = $request->input('jenis_lokasi', 'Dalam Negeri');

        $rules = [
            'nama_perusahaan' => 'required|string|max:255',
            'jenis_lokasi' => 'required|string|in:Dalam Negeri,Luar Negeri',
            'kode_negara' => $jenisLokasi === 'Luar Negeri' ? 'required|string|size:2' : 'nullable|string|size:2',
            'kode_provinsi' => $jenisLokasi === 'Dalam Negeri' ? 'nullable|exists:propinsi,kode_provinsi' : 'nullable',
            'kode_kabupaten' => $jenisLokasi === 'Dalam Negeri' ? 'nullable|exists:kabupaten,kode_kabupaten' : 'nullable',
            'alamat' => 'nullable|string|max:500',
            'kode_pos' => 'nullable|string|max:15',
            'skala' => 'nullable|string|in:Lokal,Nasional,Internasional',
            'jenis_perusahaan' => 'nullable|string|max:100',
            'jenis_perusahaan_lainnya' => 'nullable|string|max:150',
            'sektor' => 'nullable|string|max:100',
            'bentuk_perusahaan' => 'nullable|string|max:60',
            'jumlah_pegawai' => 'nullable|string|max:60',
            'homepage' => 'nullable|string|max:255',
            'no_telp_fax' => 'nullable|string|max:100',
        ];

        $validated = $request->validate($rules);

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => $validated['nama_perusahaan'],
            'jenis_lokasi' => $jenisLokasi,
            'kode_negara' => $jenisLokasi === 'Luar Negeri' ? strtoupper($validated['kode_negara'] ?? 'US') : 'ID',
            'kode_provinsi' => $jenisLokasi === 'Dalam Negeri' ? ($validated['kode_provinsi'] ?? null) : null,
            'kode_kabupaten' => $jenisLokasi === 'Dalam Negeri' ? ($validated['kode_kabupaten'] ?? null) : null,
            'alamat' => $validated['alamat'] ?? null,
            'kode_pos' => $validated['kode_pos'] ?? null,
            'skala' => $validated['skala'] ?? ($jenisLokasi === 'Luar Negeri' ? 'Internasional' : 'Nasional'),
            'jenis_perusahaan' => $validated['jenis_perusahaan'] ?? null,
            'jenis_perusahaan_lainnya' => $validated['jenis_perusahaan_lainnya'] ?? null,
            'sektor' => $validated['sektor'] ?? null,
            'bentuk_perusahaan' => $validated['bentuk_perusahaan'] ?? null,
            'jumlah_pegawai' => $validated['jumlah_pegawai'] ?? null,
            'homepage' => $validated['homepage'] ?? null,
            'no_telp_fax' => $validated['no_telp_fax'] ?? null,
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_user_id' => $request->user()?->id,
            'created_by_prodi_id' => $request->user()?->prodi_id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Perusahaan berhasil ditambahkan dengan status Menunggu Verifikasi.',
                'perusahaan' => $perusahaan,
            ]);
        }

        return redirect()->back()->with('success', 'Perusahaan berhasil ditambahkan dengan status Menunggu Verifikasi.');
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
