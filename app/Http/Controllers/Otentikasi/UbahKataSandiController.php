<?php

namespace App\Http\Controllers\Otentikasi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * UbahKataSandiController
 *
 * Fungsi: Menangani proses pergantian kata sandi wajib bagi pengguna yang baru pertama kali login.
 * Tujuan: Meningkatkan keamanan dengan memaksa pengguna (terutama alumni) mengganti kata sandi bawaan.
 */
class UbahKataSandiController extends Controller
{
    /**
     * Menampilkan Halaman Ubah Kata Sandi
     */
    public function tampilkanUbahKataSandi()
    {
        /** @var User $pengguna */
        $pengguna = Auth::user();

        if ($pengguna->role !== 'alumni' || ! $pengguna->must_change_password) {
            return LoginController::arahkanBerdasarkanPeran($pengguna->role);
        }

        return Inertia::render('Otentikasi/UbahKataSandi');
    }

    /**
     * Proses Penyimpanan Kata Sandi Baru
     */
    public function prosesUbahKataSandi(Request $request)
    {
        $pengguna = Auth::user();

        if ($pengguna->role !== 'alumni' || ! $pengguna->must_change_password) {
            return LoginController::arahkanBerdasarkanPeran($pengguna->role);
        }

        $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $pengguna->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return LoginController::arahkanBerdasarkanPeran($pengguna->role);
    }
}
