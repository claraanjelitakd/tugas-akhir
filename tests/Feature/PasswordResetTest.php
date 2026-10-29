<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memastikan token reset kata sandi memiliki masa berlaku dan hanya dapat digunakan sekali.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan token yang sudah dipakai ditolak pada percobaan kedua.
     */
    public function test_password_reset_token_can_only_be_used_once(): void
    {
        $user = User::create([
            'username' => 'admin_reset_once',
            'name' => 'Admin Reset Once',
            'email' => 'admin.reset.once@ukdw.ac.id',
            'password' => Hash::make('password-lama'),
            'role' => 'admin_prodi',
            'must_change_password' => false,
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('token-sekali-pakai'),
            'created_at' => now(),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => 'token-sekali-pakai',
            'email' => $user->email,
            'password' => 'password-baru-aman',
            'password_confirmation' => 'password-baru-aman',
        ]);

        $response->assertRedirectToRoute('login');
        $this->assertTrue(Hash::check('password-baru-aman', $user->refresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $usedLinkResponse = $this->get(route('password.reset', [
            'token' => 'token-sekali-pakai',
            'email' => $user->email,
        ]));

        $usedLinkResponse->assertRedirectToRoute('password.request');
        $usedLinkResponse->assertSessionHasErrors('email');

        $secondResponse = $this->from(route('password.reset', ['token' => 'token-sekali-pakai', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'token-sekali-pakai',
                'email' => $user->email,
                'password' => 'password-baru-kedua',
                'password_confirmation' => 'password-baru-kedua',
            ]);

        $secondResponse->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password-baru-aman', $user->refresh()->password));
    }

    /**
     * Memastikan token yang melewati 60 menit dihapus dan tidak dapat digunakan.
     */
    public function test_expired_password_reset_token_is_rejected_and_deleted(): void
    {
        $user = User::create([
            'username' => 'admin_reset_expired',
            'name' => 'Admin Reset Expired',
            'email' => 'admin.reset.expired@ukdw.ac.id',
            'password' => Hash::make('password-lama'),
            'role' => 'admin_prodi',
            'must_change_password' => false,
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('token-kadaluarsa'),
            'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => 'token-kadaluarsa',
            'email' => $user->email,
            'password' => 'password-baru-aman',
            'password_confirmation' => 'password-baru-aman',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertTrue(Hash::check('password-lama', $user->refresh()->password));

        $expiredLinkResponse = $this->get(route('password.reset', [
            'token' => 'token-kadaluarsa',
            'email' => $user->email,
        ]));

        $expiredLinkResponse->assertRedirectToRoute('password.request');
        $expiredLinkResponse->assertSessionHasErrors('email');
    }
}
