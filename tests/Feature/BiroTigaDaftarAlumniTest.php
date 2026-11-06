<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BiroTigaDaftarAlumniTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminBiro3;

    protected User $alumniUser;

    protected Biodata $alumni;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminBiro3 = User::create([
            'username' => 'biro3_test',
            'name' => 'Admin Biro 3 Test',
            'password' => Hash::make('password123'),
            'role' => 'admin_biro3',
            'must_change_password' => false,
        ]);

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $this->alumniUser = User::create([
            'username' => '72200001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200001',
            'nama' => 'Budi Santoso',
            'angkatan_masuk' => '2020',
            'tahun_akademik_lulus' => 'Genap 2025/2026',
            'tahun_lulus' => '2026',
            'ip_kumulatif' => '3.85',
        ]);

        $this->alumni = Biodata::create([
            'user_id' => $this->alumniUser->id,
            'prodi_id' => $this->prodi->id,
            'nim' => '72200001',
            'nama' => 'Budi Santoso',
            'nomor_telepon' => '081234567890',
            'email_pribadi' => 'budi@example.com',
            'linkedin_url' => 'https://www.linkedin.com/in/budisantoso',
        ]);

        EvaluasiAtasan::create([
            'biodata_id' => $this->alumni->id,
            'token' => 'test-token-123',
            'is_submitted' => true,
        ]);
    }

    public function test_admin_biro3_dapat_mengakses_halaman_daftar_alumni()
    {
        $response = $this->actingAs($this->adminBiro3)
            ->get('/biro3/alumni');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AdminBiroTiga/AlumniIndex')
            ->has('alumnis')
            ->has('daftarTahun')
            ->has('daftarTarget')
            ->has('prodis')
            ->has('stats')
            ->has('filters')
        );
    }

    public function test_alumni_termuat_dengan_evaluasi_atasan_dan_kelengkapan()
    {
        $response = $this->actingAs($this->adminBiro3)
            ->get('/biro3/alumni?tahun=2026');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AdminBiroTiga/AlumniIndex')
            ->where('alumnis.0.nim', '72200001')
            ->where('alumnis.0.linkedin_url', 'https://www.linkedin.com/in/budisantoso')
            ->where('alumnis.0.evaluasi_atasan.is_submitted', true)
            ->where('alumnis.0.evaluasi_atasan.status', 'submitted')
            ->has('alumnis.0.kelengkapan.profile')
            ->has('alumnis.0.kelengkapan.questionnaire')
        );
    }

    /**
     * Biro 3 tidak memiliki endpoint mutasi sinkronisasi LinkedIn.
     */
    public function test_admin_biro3_cannot_access_linkedin_mutation_endpoints(): void
    {
        $this->actingAs($this->adminBiro3)
            ->post('/biro3/linkedin-sync/batch', [])
            ->assertNotFound();

        $this->actingAs($this->adminBiro3)
            ->patch('/biro3/linkedin-sync/1/url', ['linkedin_url' => 'https://www.linkedin.com/in/test'])
            ->assertNotFound();

        $this->actingAs($this->adminBiro3)
            ->post('/biro3/linkedin-sync/1', [])
            ->assertNotFound();

        $this->actingAs($this->adminBiro3)
            ->post('/biro3/linkedin-sync/results/1/approve', [])
            ->assertNotFound();

        $this->actingAs($this->adminBiro3)
            ->post('/biro3/linkedin-sync/results/1/reject', [])
            ->assertNotFound();
    }
}
