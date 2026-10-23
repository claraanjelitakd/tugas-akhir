<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\DataOrangTua;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlumniDashboardPercentageTest extends TestCase
{
    use RefreshDatabase;

    protected User $alumniUser;

    protected Biodata $alumni;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $this->alumniUser = User::create([
            'username' => '721220001',
            'name' => 'Budi Santoso',
            'email' => 'budi@students.ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '721220001',
            'nama' => 'Budi Santoso',
            'email_pribadi' => 'budi.pribadi@gmail.com',
            'tempat_lahir' => 'Yogyakarta',
            'tanggal_lahir' => '2000-01-01',
            'agama' => 'Kristen',
            'jenis_kelamin' => 'Laki-laki',
            'nomor_telepon' => '08123456789',
            'alamat_saat_ini' => 'Jl. Solo Km 10',
            'nik' => '3471012345670001',
            'ip_kumulatif' => 3.75,
            'tahun_akademik_lulus' => 'Gasal 2024/2025',
        ]);

        $this->alumni = Biodata::create([
            'user_id' => $this->alumniUser->id,
            'nim' => '721220001',
            'nama' => 'Budi Santoso',
            'prodi_id' => $this->prodi->id,
            'posisi_jabatan' => 'Software Engineer',
            'skills' => 'Fullstack Web',
            'experience' => 'Cloud Computing',
            'linkedin_url' => 'https://linkedin.com/in/budi',
            'linkedin_username' => 'budi',
            'instagram_url' => 'https://instagram.com/budi',
            'facebook_url' => 'https://facebook.com/budi',
            'zipcode' => '55281',
        ]);

        DataOrangTua::create([
            'nim' => '721220001',
            'nama_orang_tua' => 'Santoso',
            'pekerjaan' => 'Wiraswasta',
            'alamat' => 'Jl. Solo Km 10',
            'nomor_telepon' => '08129876543',
        ]);

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Teknologi Nusantara',
            'alamat' => 'Jl. Gejayan No. 1',
            'skala' => 'Nasional',
        ]);

        $this->alumni->update(['perusahaan_id' => $perusahaan->id]);
    }

    public function test_dashboard_renders_percentages_correctly(): void
    {
        $response = $this->actingAs($this->alumniUser)
            ->get('/alumni/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Alumni/Dashboard')
            ->has('profilePercentage')
            ->has('questionnairePercentage')
            ->has('profileCompleted')
            ->has('questionnaireCompleted')
            ->has('profileFilledCount')
            ->has('profileTotalCount')
            ->has('prodiQuestionsCount')
            ->has('prodiAnsweredCount')
            ->has('prodiPercentage')
            ->has('overallPercentage')
            ->has('isOverallComplete')
        );

        $this->assertSame(0, $response->viewData('page')['props']['prodiQuestionsCount']);
        $this->assertNull($response->viewData('page')['props']['prodiPercentage']);
    }

    public function test_profile_percentage_updates_when_field_is_deleted(): void
    {
        // Initial percentage
        $responseBefore = $this->actingAs($this->alumniUser)->get('/alumni/dashboard');
        $initialPercentage = $responseBefore->viewData('page')['props']['profilePercentage'];

        // Submit profile update with emptied company and alamat
        $this->actingAs($this->alumniUser)->post('/alumni/profile', [
            'nama_perusahaan' => '', // cleared
            'company_alamat' => '',
            'posisi_jabatan' => '', // cleared
            'nama_atasan' => '',
        ]);

        $this->alumni->refresh();
        $this->assertNull($this->alumni->company_id);
        $this->assertNull($this->alumni->posisi_jabatan);

        $responseAfter = $this->actingAs($this->alumniUser)->get('/alumni/dashboard');
        $updatedPercentage = $responseAfter->viewData('page')['props']['profilePercentage'];

        $this->assertLessThan($initialPercentage, $updatedPercentage);
    }
}
