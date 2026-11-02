<?php

namespace App\Http\Controllers\Otentikasi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Otentikasi\PasswordResetNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Menangani permintaan tautan dan proses reset kata sandi melalui email.
 */
class LupaKataSandiController extends Controller
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
     * Menampilkan halaman permintaan tautan reset kata sandi.
     */
    public function tampilkanHalamanLupaKataSandi(): Response
    {
        return Inertia::render('Otentikasi/ForgotPassword');
    }

    /**
     * Mengirimkan tautan reset kata sandi ke email terdaftar pengguna.
     */
    public function prosesKirimLinkReset(Request $request): RedirectResponse
    {
        $request->validate([
            'username_atau_email' => ['required', 'string'],
        ], [
            'username_atau_email.required' => 'Masukkan NIM/Username atau Email terdaftar Anda.',
        ]);

        $input = trim($request->input('username_atau_email'));

        // Cari pengguna berdasarkan username (NIM) atau email di tabel users & biodata saja
        $user = User::where('username', $input)
            ->orWhere('email', $input)
            ->orWhereHas('biodata', function ($q) use ($input) {
                $q->where('email_pribadi', $input);
            })
            ->first();

        $genericStatus = 'Jika akun dan email terdaftar valid, tautan reset kata sandi akan dikirim.';

        if (! $user) {
            return back()->with('status', $genericStatus);
        }

        $email = $user->email;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('status', $genericStatus);
        }

        try {
            $this->passwordResetNotificationService->send($user);

            return back()->with('status', $genericStatus);

        } catch (Throwable $exception) {
            report($exception);

            return back()->with('status', $genericStatus);
        }
    }

    /**
     * Menampilkan halaman reset kata sandi saat pengguna mengklik tautan dari email.
     *
     * @return Response|RedirectResponse Halaman reset jika token valid atau redirect dengan error.
     */
    public function tampilkanHalamanResetKataSandi(Request $request, string $token): Response|RedirectResponse
    {
        $email = (string) $request->query('email', '');
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Tautan reset kata sandi sudah digunakan atau tidak ditemukan. Silakan minta tautan baru.',
            ]);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return redirect()->route('password.request')->withErrors([
                'email' => 'Tautan reset kata sandi telah kadaluarsa. Silakan minta tautan baru.',
            ]);
        }

        if (! Hash::check($token, $record->token)) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Tautan reset kata sandi tidak valid. Silakan minta tautan baru.',
            ]);
        }

        return Inertia::render('Otentikasi/ResetPassword', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Memproses penggantian kata sandi baru.
     */
    public function prosesResetKataSandi(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'email.required' => 'Email tidak boleh kosong.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal terdiri dari 12 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        return DB::transaction(function () use ($request): RedirectResponse {
            // Kunci token selama verifikasi dan update agar satu token tidak lolos pada dua request bersamaan.
            $record = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return back()->withErrors([
                    'email' => 'Permintaan reset kata sandi tidak ditemukan atau sudah kadaluarsa.',
                ]);
            }

            // Cek kedaluwarsa token (60 menit) dan hapus token yang sudah tidak valid.
            if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                return back()->withErrors([
                    'email' => 'Tautan reset kata sandi telah kadaluarsa. Silakan minta tautan baru.',
                ]);
            }

            // Verifikasi token rahasia yang tersimpan dalam bentuk hash.
            if (! Hash::check($request->token, $record->token)) {
                return back()->withErrors([
                    'email' => 'Token reset kata sandi tidak valid.',
                ]);
            }

            // Cari pengguna dan simpan password baru di transaksi yang sama.
            $user = User::where('email', $request->email)->first();

            if (! $user) {
                $user = User::whereHas('biodata', function ($q) use ($request) {
                    $q->where('email_pribadi', $request->email);
                })->first();
            }

            if (! $user) {
                return back()->withErrors([
                    'email' => 'Pengguna tidak ditemukan.',
                ]);
            }

            $user->password = Hash::make($request->password);
            $user->must_change_password = false;
            $user->save();

            // Token dihapus dalam transaksi yang sama sehingga hanya bisa dipakai satu kali.
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return redirect()->route('login')->with('status', 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.');
        });
    }
}
