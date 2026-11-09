<?php

namespace App\Http\Controllers\Shared\DataAtasan;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Atasan\AtasanDirectoryQueryService;
use Illuminate\Http\Request;
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

        return Inertia::render('Shared/DataAtasan/Index', [
            'user' => $user,
            'role' => $role,
            'roleTitle' => $roleTitle,
            'prefix' => $prefix,
            'scopeLabel' => $this->resolveScopeLabel($user),
            'showProdiFilter' => $role !== 'admin_prodi',
            ...$directoryData,
        ]);
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
