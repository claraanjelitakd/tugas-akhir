<?php

namespace App\Http\Controllers\AdminFakultas;

use App\Http\Controllers\Controller;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use App\Services\Otentikasi\PasswordResetNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Mengelola akun alumni dalam lingkup fakultas admin yang sedang login.
 */
class ManajemenAkunFakultasController extends Controller
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
     * Tampilkan Halaman Manajemen Akun Mahasiswa / Alumni Se-Fakultas
     */
    public function index(Request $request): Response
    {
        $adminUser = Auth::user();
        $fakultasId = $adminUser->fakultas_id;
        $search = $request->input('search');
        $prodiFilter = $request->input('prodi_id');

        // Ambil seluruh prodi dalam fakultas ini
        $prodiIdsInFakultas = Prodi::where('fakultas_id', $fakultasId)->pluck('id')->toArray();

        $query = User::with(['prodi', 'biodata.dataAkademik'])
            ->where('role', 'alumni')
            ->whereIn('prodi_id', $prodiIdsInFakultas);

        if ($prodiFilter) {
            $query->where('prodi_id', $prodiFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        $currentFakultas = RefFakultas::find($fakultasId);
        $prodisInFakultas = Prodi::where('fakultas_id', $fakultasId)->orderBy('nama_prodi', 'asc')->get();

        return Inertia::render('AdminFakultas/ManajemenAkun/Index', [
            'users' => $users,
            'currentFakultas' => $currentFakultas,
            'prodis' => $prodisInFakultas,
            'filters' => ['search' => $search, 'prodi_id' => $prodiFilter],
            'stats' => [
                'total_alumni' => User::where('role', 'alumni')->whereIn('prodi_id', $prodiIdsInFakultas)->count(),
            ],
        ]);
    }

    /**
     * Perbarui Email / Nama Mahasiswa oleh Admin Fakultas
     */
    public function update(Request $request, $id)
    {
        $adminUser = Auth::user();
        $prodiIdsInFakultas = Prodi::where('fakultas_id', $adminUser->fakultas_id)->pluck('id')->toArray();
        $user = User::whereIn('prodi_id', $prodiIdsInFakultas)->where('role', 'alumni')->findOrFail($id);

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
            "Admin Fakultas memperbarui data mahasiswa {$user->name} (@{$user->username}). Email diatur ke {$request->email}.",
            $user,
            $oldValues,
            $user->only(['name', 'email', 'username'])
        );

        return back()->with('success', "Data mahasiswa/alumni {$user->name} berhasil diperbarui.");
    }

    /**
     * Mengirim tautan reset kata sandi kepada alumni dalam fakultas admin.
     *
     * @param  int  $id  ID akun alumni yang menerima tautan reset.
     * @return RedirectResponse Redirect kembali dengan status keberhasilan atau error.
     */
    public function sendPasswordResetLink(int $id): RedirectResponse
    {
        $adminUser = Auth::user();
        $prodiIdsInFakultas = Prodi::where('fakultas_id', $adminUser->fakultas_id)->pluck('id')->toArray();
        $user = User::whereIn('prodi_id', $prodiIdsInFakultas)->where('role', 'alumni')->findOrFail($id);

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
            "Admin Fakultas mengirim tautan reset kata sandi untuk mahasiswa {$user->name} (@{$user->username}).",
            $user
        );

        return back()->with('success', "Tautan reset kata sandi untuk {$user->name} telah dikirim ke {$email}.");
    }
}
