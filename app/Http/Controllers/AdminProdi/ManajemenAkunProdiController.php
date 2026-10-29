<?php

namespace App\Http\Controllers\AdminProdi;

use App\Http\Controllers\Controller;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Models\User;
use App\Services\Otentikasi\PasswordResetNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Mengelola akun alumni dalam lingkup program studi admin yang sedang login.
 */
class ManajemenAkunProdiController extends Controller
{
    /**
     * Menyiapkan service pengiriman tautan reset kata sandi.
     *
     * @param  PasswordResetNotificationService  $passwordResetNotificationService  Service reset kata sandi terpusat.
     */
    public function __construct(
        private PasswordResetNotificationService $passwordResetNotificationService
    ) {}

    /**
     * Tampilkan Halaman Manajemen Akun Mahasiswa / Alumni Prodi Sendiri
     */
    public function index(Request $request): Response
    {
        $adminUser = Auth::user();
        $prodiId = $adminUser->prodi_id;
        $search = $request->input('search');

        $query = User::with(['prodi', 'biodata.dataAkademik'])
            ->where('role', 'alumni')
            ->where('prodi_id', $prodiId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        $currentProdi = Prodi::find($prodiId);

        return Inertia::render('AdminProdi/ManajemenAkun/Index', [
            'users' => $users,
            'currentProdi' => $currentProdi,
            'filters' => ['search' => $search],
            'stats' => [
                'total_alumni' => User::where('role', 'alumni')->where('prodi_id', $prodiId)->count(),
            ],
        ]);
    }

    /**
     * Perbarui Email / Nama Mahasiswa oleh Admin Prodi
     */
    public function update(Request $request, $id)
    {
        $adminUser = Auth::user();
        $user = User::where('prodi_id', $adminUser->prodi_id)->where('role', 'alumni')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
        ]);

        $oldValues = $user->only(['name', 'email', 'username']);
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
        ]);

        if ($user->biodata) {
            $user->biodata->update([
                'nama' => $request->name,
                'email_pribadi' => $request->email,
            ]);
        }

        // Catat Audit Trail ke tabel log_activities
        LogActivity::record(
            'UPDATE_USER_ACCOUNT',
            "Admin Prodi memperbarui data mahasiswa {$user->name} (@{$user->username}). Email diatur ke {$request->email}.",
            $user,
            $oldValues,
            $user->only(['name', 'email', 'username'])
        );

        return back()->with('success', "Data mahasiswa/alumni {$user->name} berhasil diperbarui.");
    }

    /**
     * Mengirim tautan reset kata sandi kepada alumni dalam prodi admin.
     *
     * @param  int  $id  ID akun alumni yang menerima tautan reset.
     * @return RedirectResponse Redirect kembali dengan status keberhasilan atau error.
     */
    public function sendPasswordResetLink(int $id): RedirectResponse
    {
        $adminUser = Auth::user();
        $user = User::where('prodi_id', $adminUser->prodi_id)->where('role', 'alumni')->findOrFail($id);

        try {
            $email = $this->passwordResetNotificationService->send($user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'reset' => 'Tautan reset gagal dikirim. Periksa email alumni dan konfigurasi mail server.',
            ]);
        }

        // Catat Audit Trail ke tabel log_activities
        LogActivity::record(
            'REQUEST_PASSWORD_RESET',
            "Admin Prodi mengirim tautan reset kata sandi untuk mahasiswa {$user->name} (@{$user->username}).",
            $user
        );

        return back()->with('success', "Tautan reset kata sandi untuk {$user->name} telah dikirim ke {$email}.");
    }
}
