<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\RefSubpertanyaan2021;
use App\Models\Tracer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/superadmin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_superadmin_can_render_dashboard(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $superadmin = User::create([
            'username' => 'superadmin',
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
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
            'tempat_lahir' => 'Yogyakarta',
            'tanggal_lahir' => '2001-08-15',
            'jenis_kelamin' => 'Laki-laki',
            'nomor_telepon' => '081234567890',
            'alamat_saat_ini' => 'Jl. Dr. Wahidin',
            'ip_kumulatif' => 3.80,
            'tahun_lulus' => now()->year,
            'tahun_akademik_lulus' => 'Genap 2024/2025',
        ]);

        Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '72200001',
            'nama' => 'Alumni SI 1',
            'prodi_id' => $prodi->id,
        ]);

        $response = $this->actingAs($superadmin)->get('/superadmin/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Dashboard')
            ->has('stats')
            ->has('completionMetrics')
            ->has('prodiSummaries')
            ->has('recentAlumni')
            ->has('recentLogs')
            ->where('stats.total_alumni', 1)
            ->where('stats.total_prodi', 1)
            ->where('selectedYear', (string) now()->year)
            ->where('stats.response_rate_status.code', 'red')
            ->where('completionMetrics.population', 1)
            ->where('completionMetrics.profile.percentage', 0)
            ->where('completionMetrics.university_questionnaire.percentage', 0)
        );
    }

    public function test_dashboard_filters_alumni_metrics_by_selected_graduation_year(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $superadmin = User::create([
            'username' => 'superadmin-year-filter',
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        foreach ([$currentYear, $previousYear] as $index => $graduationYear) {
            $nim = '7220'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $alumniUser = User::create([
                'username' => $nim,
                'name' => 'Alumni '.$graduationYear,
                'password' => Hash::make('password123'),
                'role' => 'alumni',
                'prodi_id' => $prodi->id,
                'must_change_password' => false,
            ]);

            DataAkademik::create([
                'nim' => $nim,
                'nama' => 'Alumni '.$graduationYear,
                'tahun_lulus' => $graduationYear,
            ]);

            Biodata::create([
                'user_id' => $alumniUser->id,
                'nim' => $nim,
                'nama' => 'Alumni '.$graduationYear,
                'prodi_id' => $prodi->id,
            ]);
        }

        $response = $this->actingAs($superadmin)->get('/superadmin/dashboard?tahun='.$previousYear);

        $response->assertInertia(fn ($page) => $page
            ->where('selectedYear', (string) $previousYear)
            ->where('stats.total_alumni', 1)
            ->where('completionMetrics.population', 1)
            ->where('prodiSummaries.0.total_alumni', 1)
            ->where('recentAlumni.0.tahun_lulus', $previousYear)
        );
    }

    public function test_dashboard_classifies_response_rate_by_three_thresholds(): void
    {
        $question = RefSubpertanyaan2021::create([
            'kelompok' => 'F8',
            'kode_pertanyaan' => 'F8',
            'subpertanyaan' => 'Status utama setelah lulus',
            'type' => 'radio',
            'wajib' => true,
            'order' => 1,
        ]);

        $superadmin = User::create([
            'username' => 'superadmin-threshold',
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $prodiDefinitions = [
            ['kode' => '71', 'total' => 4, 'responded' => 3],
            ['kode' => '72', 'total' => 2, 'responded' => 1],
            ['kode' => '73', 'total' => 3, 'responded' => 1],
        ];

        foreach ($prodiDefinitions as $prodiDefinition) {
            $prodi = Prodi::create([
                'kode_prodi' => $prodiDefinition['kode'],
                'nama_prodi' => 'Program Studi '.$prodiDefinition['kode'],
            ]);

            for ($index = 1; $index <= $prodiDefinition['total']; $index++) {
                $nim = $prodiDefinition['kode'].'20'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
                $alumniUser = User::create([
                    'username' => $nim,
                    'name' => 'Alumni '.$nim,
                    'password' => Hash::make('password123'),
                    'role' => 'alumni',
                    'prodi_id' => $prodi->id,
                    'must_change_password' => false,
                ]);

                DataAkademik::create([
                    'nim' => $nim,
                    'nama' => 'Alumni '.$nim,
                    'tahun_lulus' => now()->year,
                ]);

                $biodata = Biodata::create([
                    'user_id' => $alumniUser->id,
                    'nim' => $nim,
                    'nama' => 'Alumni '.$nim,
                    'prodi_id' => $prodi->id,
                ]);

                if ($index <= $prodiDefinition['responded']) {
                    Tracer::create([
                        'biodata_id' => $biodata->id,
                        'question_id' => $question->id,
                        'nim' => $nim,
                        'kelompok' => 'F8',
                        'kode_pertanyaan' => 'F8',
                        'subpertanyaan' => $question->subpertanyaan,
                        'answer' => 'Bekerja',
                    ]);
                }
            }
        }

        $response = $this->actingAs($superadmin)->get('/superadmin/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('stats.response_rate', 55.6)
            ->where('stats.response_rate_status.code', 'yellow')
            ->where('prodiSummaries', function ($summaries): bool {
                $byCode = collect($summaries)->keyBy('kode_prodi');

                return $byCode->get('71')['response_rate_status']['code'] === 'green'
                    && $byCode->get('72')['response_rate_status']['code'] === 'yellow'
                    && $byCode->get('73')['response_rate_status']['code'] === 'red';
            })
        );
    }

    public function test_audit_view_counts_active_mandatory_questions_instead_of_fixed_twenty(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $alumniUser = User::create([
            'username' => '72200009',
            'name' => 'Alumni Dynamic Count',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200009',
            'nama' => 'Alumni Dynamic Count',
            'tahun_lulus' => now()->year,
        ]);

        $biodata = Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '72200009',
            'nama' => 'Alumni Dynamic Count',
            'prodi_id' => $prodi->id,
        ]);

        foreach (['F8', 'F504', 'F14', 'F15', 'F502'] as $code) {
            $question = RefSubpertanyaan2021::create([
                'kelompok' => $code,
                'kode_pertanyaan' => $code,
                'subpertanyaan' => 'Butir '.$code,
                'type' => 'radio',
                'wajib' => true,
                'order' => 1,
            ]);

            Tracer::create([
                'biodata_id' => $biodata->id,
                'question_id' => $question->id,
                'nim' => $biodata->nim,
                'kelompok' => $question->kelompok,
                'kode_pertanyaan' => $question->kode_pertanyaan,
                'subpertanyaan' => $question->subpertanyaan,
                'answer' => 'Terisi',
            ]);
        }

        $audit = DB::table('v_alumni_audit_rekap')
            ->where('biodata_id', $biodata->id)
            ->first();

        $this->assertSame(5, (int) $audit->total_mandatory_univ);
        $this->assertSame(5, (int) $audit->answered_mandatory_univ);
        $this->assertSame(1, (int) $audit->is_complete_univ);
    }
}
