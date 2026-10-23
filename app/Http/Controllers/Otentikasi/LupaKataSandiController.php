<?php

namespace App\Http\Controllers\Otentikasi;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LupaKataSandiController extends Controller
{
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

        // Buat token reset password acak
        $token = Str::random(64);

        // Simpan token ke database (password_reset_tokens)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Buat tautan reset password
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $email,
        ]);

        try {
            // Kirim email reset password
            Mail::to($email)->send(new ResetPasswordMail($user, $resetUrl));

            return back()->with('status', $genericStatus);

        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('status', $genericStatus);
        }
    }

    /**
     * Menampilkan halaman reset kata sandi saat pengguna mengklik tautan dari email.
     */
    public function tampilkanHalamanResetKataSandi(Request $request, string $token): Response
    {
        return Inertia::render('Otentikasi/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
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

        // Cek record token di password_reset_tokens
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return back()->withErrors([
                'email' => 'Permintaan reset kata sandi tidak ditemukan atau sudah kadaluarsa.',
            ]);
        }

        // Cek kedaluwarsa token (60 menit)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return back()->withErrors([
                'email' => 'Tautan reset kata sandi telah kadaluarsa. Silakan minta tautan baru.',
            ]);
        }

        // Verifikasi token
        if (! Hash::check($request->token, $record->token)) {
            return back()->withErrors([
                'email' => 'Token reset kata sandi tidak valid.',
            ]);
        }

        // Cari user dan update password
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

        // Hapus token yang sudah digunakan
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('status', 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.');
    }
}
