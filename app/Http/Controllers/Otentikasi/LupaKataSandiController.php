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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        $throttleKey = 'forgot-password|'.Str::lower($input).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'username_atau_email' => "Terlalu banyak permintaan reset kata sandi. Silakan tunggu {$seconds} detik lagi sebelum mencoba kembali.",
            ])->with('error', "Terlalu banyak permintaan reset kata sandi. Silakan tunggu {$seconds} detik lagi sebelum mencoba kembali.");
        }

        RateLimiter::hit($throttleKey, 60);

        // Cari pengguna berdasarkan username (NIM) atau email di tabel users & biodata
        $user = User::where('username', $input)
            ->orWhere('email', $input)
            ->orWhereHas('biodata', function ($q) use ($input) {
                $q->where('email_pribadi', $input);
            })
            ->first();

        if (! $user) {
            Log::info("Permintaan reset kata sandi diabaikan: input '{$input}' tidak ditemukan di database.");

            return back()->withErrors([
                'username_atau_email' => "Akun dengan NIM/Username atau Email '{$input}' tidak ditemukan di sistem Tracer Study.",
            ])->with('error', "Akun dengan identitas '{$input}' tidak ditemukan di sistem Tracer Study.");
        }

        $email = $user->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $user->biodata?->email_pribadi;
        }

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Permintaan reset kata sandi diabaikan: pengguna {$user->username} tidak memiliki email valid.");

            return back()->withErrors([
                'username_atau_email' => 'Akun Anda belum memiliki alamat email aktif yang terdaftar. Silakan hubungi admin Biro 3 UKDW.',
            ])->with('error', 'Akun Anda belum memiliki alamat email aktif yang terdaftar.');
        }

        try {
            $this->passwordResetNotificationService->send($user);
            $maskedEmail = $this->maskEmail($email);

            return back()
                ->with('status', "Tautan reset kata sandi berhasil dikirim ke email {$maskedEmail}. Silakan periksa kotak masuk atau folder spam Anda.")
                ->with('success', "Tautan reset kata sandi berhasil dikirim ke email {$maskedEmail}. Silakan periksa kotak masuk atau folder spam Anda.");

        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'username_atau_email' => 'Gagal mengirim email reset kata sandi. Pastikan mail server aktif atau coba beberapa saat lagi.',
            ])->with('error', 'Gagal mengirim email reset kata sandi. Silakan coba beberapa saat lagi.');
        }
    }

    /**
     * Menyamarkan sebagian alamat email untuk keamanan tampilan (contoh: a***s@students.ukdw.ac.id).
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];
        $len = strlen($name);

        if ($len <= 2) {
            $maskedName = substr($name, 0, 1).'*';
        } else {
            $maskedName = substr($name, 0, 2).str_repeat('*', min(4, max(1, $len - 3))).substr($name, -1);
        }

        return $maskedName.'@'.$domain;
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
