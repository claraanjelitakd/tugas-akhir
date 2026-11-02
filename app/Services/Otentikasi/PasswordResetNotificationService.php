<?php

namespace App\Services\Otentikasi;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Menyiapkan token reset kata sandi dan mengirim tautannya ke email pengguna.
 *
 * Service ini menjadi satu pintu untuk fitur lupa kata sandi dan reset dari
 * Manajemen Akun, sehingga format token, masa berlaku, dan email tetap sama.
 */
class PasswordResetNotificationService
{
    /**
     * Membuat token reset baru dan mengirim email tautan reset.
     *
     * @param  User  $user  Pengguna yang akan menerima tautan reset.
     * @return string Alamat email tujuan yang sudah divalidasi.
     *
     * @throws InvalidArgumentException Jika pengguna tidak memiliki email valid.
     */
    public function send(User $user): string
    {
        $email = $user->email;

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Pengguna belum memiliki alamat email yang valid.');
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $email,
        ]);

        Mail::to($email)->send(new ResetPasswordMail($user, $resetUrl));

        return $email;
    }
}
