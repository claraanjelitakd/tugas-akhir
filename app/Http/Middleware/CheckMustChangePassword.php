<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses pengguna alumni yang masih wajib mengganti kata sandi.
 */
class CheckMustChangePassword
{
    /**
     * Handle an incoming request.
     * Mencegah pengguna yang wajib mengganti password mengakses dashboard.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        // Jika user sedang login DAN status must_change_password bernilai true
        if ($user instanceof User && $user->role === 'alumni' && $user->must_change_password) {

            // Abaikan pencegatan jika route saat ini sudah di form '/change-password'
            // atau jika user sedang mencoba melakukan proses '/logout'
            // Hal ini untuk mencegah infinite redirect (looping)
            if (! $request->is('change-password') && ! $request->is('logout')) {
                // Arahkan paksa user ke halaman ganti password
                return redirect('/change-password');
            }
        }

        // Jika kondisi aman, lanjutkan request ke rute tujuan
        return $next($request);
    }
}
