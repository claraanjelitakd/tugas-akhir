<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProdiDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/prodi/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admin_prodi_can_render_dashboard(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $admin = User::create([
            'username' => 'admin_si',
            'name' => 'Admin Prodi SI',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);

        $section = KuesionerSection::create([
            'kode_section' => 'SEC_TEST_SI',
            'target' => 'prodi',
            'prodi_id' => $prodi->id,
            'judul' => 'Seksi Evaluasi Kurikulum',
            'order' => 1,
            'is_active' => true,
        ]);

        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'SI_01',
            'teks_pertanyaan' => 'Bagaimana relevansi kurikulum SI?',
            'tipe' => 'radio',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $section->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => true,
        ]);

        $alumniUser = User::create([
            'username' => '72200001',
            'name' => 'Alumni SI 1',
            'email' => 'alumni1@test.com',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200001',
            'nama' => 'Alumni SI 1',
            'email_pribadi' => 'alumni1@test.com',
            'tanggal_lahir' => '2001-08-15',
            'jenis_kelamin' => 'Laki-laki',
            'nomor_telepon' => '081234567890',
            'alamat_saat_ini' => 'Jl. Dr. Wahidin',
            'ip_kumulatif' => 3.80,
            'tahun_akademik_lulus' => 'Genap 2024/2025',
            'tahun_lulus' => now()->year,
        ]);

        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Dashboard Utama',
            'status_verifikasi' => 'Terverifikasi',
        ]);

        Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '72200001',
            'nama' => 'Alumni SI 1',
            'prodi_id' => $prodi->id,
            'perusahaan_id' => $company->id,
        ]);

        $response = $this->actingAs($admin)->get('/prodi/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('AdminProdi/Dashboard')
            ->has('stats')
            ->has('sectionsList')
            ->has('recentAlumnis')
            ->where('companySummary.top_company.name', 'PT Dashboard Utama')
            ->where('companySummary.top_company.alumni_count', 1)
            ->where('companySummary.total_companies', 1)
            ->where('stats.totalAlumni', 1)
            ->where('stats.totalSections', 1)
            ->where('stats.totalPertanyaan', 1)
        );
    }
}
