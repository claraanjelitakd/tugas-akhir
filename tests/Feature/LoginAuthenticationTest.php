<?php

namespace Tests\Feature;

use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengujian Fitur Login Multi-Peran dan Multi-Kredensial
 *
 * Menguji skenario login untuk superadmin, admin biro 3, admin prodi, serta alumni
 * baik melalui username, email, maupun format kata sandi awal (tanggal lahir / default).
 */
class LoginAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_login_using_username(): void
    {
        $superadmin = User::create([
            'username' => 'superadmin',
            'name' => 'Super Administrator',
            'email' => 'superadmin@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($superadmin);
        $response->assertRedirect('/superadmin/dashboard');
    }

    public function test_superadmin_can_login_using_email(): void
    {
        $superadmin = User::create([
            'username' => 'superadmin',
            'name' => 'Super Administrator',
            'email' => 'superadmin@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'superadmin@ukdw.ac.id',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($superadmin);
        $response->assertRedirect('/superadmin/dashboard');
    }

    public function test_admin_biro3_can_login_and_redirects_to_biro3_dashboard(): void
    {
        $adminBiro3 = User::create([
            'username' => 'admin_biro3',
            'name' => 'Admin Biro 3',
            'email' => 'biro3@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'admin_biro3',
            'must_change_password' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'admin_biro3',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($adminBiro3);
        $response->assertRedirect('/biro3/dashboard');
    }

    public function test_admin_prodi_can_login_and_redirects_to_prodi_dashboard(): void
    {
        $fakultas = RefFakultas::create([
            'kode_fakultas' => 'FTI',
            'nama_fakultas' => 'Fakultas Teknologi Informasi',
        ]);

        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
            'fakultas_id' => $fakultas->id,
        ]);

        $adminProdi = User::create([
            'username' => 'admin_si',
            'name' => 'Admin Prodi SI',
            'email' => 'admin_si@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'admin_si',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($adminProdi);
        $response->assertRedirect('/prodi/dashboard');
    }

    public function test_alumni_can_login_with_password123_fallback(): void
    {
        DataAkademik::create([
            'nim' => '72220001',
            'nama' => 'Joshua Andrean',
            'tanggal_lahir' => '2004-08-15',
            'strata' => 'S1',
            'status_mahasiswa' => 'Lulus',
            'ip_kumulatif' => 3.80,
        ]);

        $alumni = User::create([
            'username' => '72220001',
            'name' => 'Joshua Andrean',
            'email' => 'joshua@gmail.com',
            'password' => Hash::make('15082004'),
            'role' => 'alumni',
            'must_change_password' => true,
        ]);

        $response = $this->post('/login', [
            'username' => '72220001',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($alumni);
        $response->assertRedirect('/change-password');
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        User::create([
            'username' => 'superadmin',
            'name' => 'Super Administrator',
            'email' => 'superadmin@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
    }
}
