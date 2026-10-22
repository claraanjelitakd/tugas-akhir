<?php

namespace Tests\Feature;

use App\Models\DataAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_index_never_exposes_an_evaluation_token(): void
    {
        $response = $this->get('/evaluasi-atasan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/EvaluasiAtasanNotFound')
            ->missing('token')
        );
    }

    public function test_logout_cannot_be_triggered_with_get(): void
    {
        $user = User::create([
            'username' => 'security-admin',
            'name' => 'Security Admin',
            'email' => 'security@example.com',
            'password' => Hash::make('strong-password'),
            'role' => 'superadmin',
        ]);

        $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($user);
    }

    public function test_birth_date_cannot_restore_access_after_alumni_changed_password(): void
    {
        DataAkademik::create([
            'nim' => '72219999',
            'nama' => 'Alumni Aman',
            'tanggal_lahir' => '2001-08-15',
        ]);

        User::create([
            'username' => '72219999',
            'name' => 'Alumni Aman',
            'email' => 'aman@example.com',
            'password' => Hash::make('PasswordBaruYangAman!'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        $this->post('/login', [
            'username' => '72219999',
            'password' => '15082001',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_non_alumni_is_not_forced_into_alumni_password_change_flow(): void
    {
        $admin = User::create([
            'username' => 'admin-biro',
            'name' => 'Admin Biro',
            'email' => 'biro@example.com',
            'password' => Hash::make('PasswordAdminYangAman!'),
            'role' => 'admin_biro3',
            'must_change_password' => true,
        ]);

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'PasswordAdminYangAman!',
        ])->assertRedirect('/biro3/dashboard');
    }

    public function test_superadmin_login_always_uses_the_superadmin_dashboard(): void
    {
        User::create([
            'username' => 'superadmin-login',
            'name' => 'Superadmin Login',
            'email' => 'superadmin-login@example.com',
            'password' => Hash::make('PasswordSuperadminYangAman!'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->withSession([
            'url.intended' => '/alumni/dashboard',
        ])->post('/login', [
            'username' => 'superadmin-login',
            'password' => 'PasswordSuperadminYangAman!',
        ]);

        $response->assertRedirect('/superadmin/dashboard');
    }
}
