<?php

namespace Tests\Feature;

use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengujian akses Analitik dan Sinkronisasi LinkedIn lintas seluruh peran administrator.
 */
class MultiRoleAnalyticsAndLinkedInAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_roles_can_access_analytics_and_linkedin_sync(): void
    {
        $fakultas = RefFakultas::create([
            'kode_fakultas' => '7',
            'nama_fakultas' => 'Fakultas Teknologi Informasi',
        ]);

        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
            'fakultas_id' => $fakultas->id,
        ]);

        $adminProdi = User::create([
            'username' => 'prodi-test',
            'name' => 'Admin Prodi SI',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $prodi->id,
            'status_akun' => 'aktif',
            'must_change_password' => false,
        ]);

        $adminFakultas = User::create([
            'username' => 'fakultas-test',
            'name' => 'Admin FTI',
            'password' => Hash::make('password123'),
            'role' => 'admin_fakultas',
            'fakultas_id' => $fakultas->id,
            'status_akun' => 'aktif',
            'must_change_password' => false,
        ]);

        $adminBiro3 = User::create([
            'username' => 'biro3-test',
            'name' => 'Admin Biro 3',
            'password' => Hash::make('password123'),
            'role' => 'admin_biro3',
            'status_akun' => 'aktif',
            'must_change_password' => false,
        ]);

        // 1. Verifikasi Admin Prodi
        $this->actingAs($adminProdi)->get('/prodi/analitik')->assertStatus(200);
        $this->actingAs($adminProdi)->get('/prodi/analitik/data')->assertStatus(200);
        $this->actingAs($adminProdi)->get('/prodi/analitik/export-response-rate')->assertStatus(200);
        $this->actingAs($adminProdi)->get('/prodi/analitik-prodi')->assertStatus(200);
        $this->actingAs($adminProdi)->get('/prodi/analitik-prodi/data')->assertStatus(200);
        $this->actingAs($adminProdi)->get('/prodi/analitik-atasan')->assertStatus(200);
        $this->actingAs($adminProdi)->get('/prodi/analitik-atasan/data')->assertStatus(200);
        $this->actingAs($adminProdi)->get('/prodi/linkedin-sync')->assertStatus(200);

        // 2. Verifikasi Admin Fakultas
        $this->actingAs($adminFakultas)->get('/fakultas/analitik')->assertStatus(200);
        $this->actingAs($adminFakultas)->get('/fakultas/analitik/data')->assertStatus(200);
        $this->actingAs($adminFakultas)->get('/fakultas/analitik-prodi')->assertStatus(200);
        $this->actingAs($adminFakultas)->get('/fakultas/analitik-prodi/data')->assertStatus(200);
        $this->actingAs($adminFakultas)->get('/fakultas/analitik-atasan')->assertStatus(200);
        $this->actingAs($adminFakultas)->get('/fakultas/analitik-atasan/data')->assertStatus(200);
        $this->actingAs($adminFakultas)->get('/fakultas/linkedin-sync')->assertStatus(200);

        // 3. Verifikasi Biro 3
        $this->actingAs($adminBiro3)->get('/biro3/analitik')->assertStatus(200);
        $this->actingAs($adminBiro3)->get('/biro3/analitik/data')->assertStatus(200);
        $this->actingAs($adminBiro3)->get('/biro3/analitik-prodi')->assertStatus(200);
        $this->actingAs($adminBiro3)->get('/biro3/analitik-prodi/data')->assertStatus(200);
        $this->actingAs($adminBiro3)->get('/biro3/analitik-atasan')->assertStatus(200);
        $this->actingAs($adminBiro3)->get('/biro3/analitik-atasan/data')->assertStatus(200);
        $this->actingAs($adminBiro3)->get('/biro3/linkedin-sync')->assertNotFound();
    }
}
