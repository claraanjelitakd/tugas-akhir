<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
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
            'tanggal_lahir' => '2001-08-15',
            'jenis_kelamin' => 'Laki-laki',
            'nomor_telepon' => '081234567890',
            'alamat_saat_ini' => 'Jl. Dr. Wahidin',
            'ip_kumulatif' => 3.80,
            'tahun_lulus' => now()->year,
            'tahun_akademik_lulus' => 'Genap 2024/2025',
        ]);

        $biodata = Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '72200001',
            'nama' => 'Alumni SI 1',
            'prodi_id' => $prodi->id,
        ]);

        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Dashboard Operasional',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_user_id' => $alumniUser->id,
            'created_by_prodi_id' => $prodi->id,
        ]);

        $atasan = Atasan::create([
            'perusahaan_id' => $company->id,
            'nama' => 'Atasan Dashboard',
            'email' => 'atasan.dashboard@example.com',
        ]);

        $biodata->update(['atasan_id' => $atasan->id]);

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
            ->where('dashboardSummary.pending_perusahaan', 1)
            ->where('dashboardSummary.total_perusahaan', 1)
            ->where('dashboardSummary.total_atasan', 1)
            ->where('companyRequests.0.applicant', 'Alumni SI 1')
            ->where('companyRequests.0.name', 'PT Dashboard Operasional')
            ->where('supervisorDirectory.0.name', 'Atasan Dashboard')
            ->where('supervisorDirectory.0.alumni_name', 'Alumni SI 1')
        );
    }

    /**
     * Memastikan Dashboard menampilkan satu baris relasi untuk setiap alumni,
     * bukan menggabungkan jumlah alumni di bawah satu nama atasan.
     */
    public function test_dashboard_lists_one_supervisor_relation_per_alumni(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $superadmin = User::create([
            'username' => 'superadmin-supervisor-relations',
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $sharedAtasan = Atasan::create([
            'nama' => 'Atasan yang Sama',
            'email' => 'atasan-sama@example.test',
        ]);

        foreach ([
            ['nim' => '72200021', 'nama' => 'Alumni Relasi Satu'],
            ['nim' => '72200022', 'nama' => 'Alumni Relasi Dua'],
        ] as $alumniData) {
            $user = User::create([
                'username' => $alumniData['nim'],
                'name' => $alumniData['nama'],
                'password' => Hash::make('password123'),
                'role' => 'alumni',
                'prodi_id' => $prodi->id,
                'must_change_password' => false,
            ]);

            DataAkademik::create([
                'nim' => $alumniData['nim'],
                'nama' => $alumniData['nama'],
                'tahun_lulus' => now()->year,
            ]);

            Biodata::create([
                'user_id' => $user->id,
                'nim' => $alumniData['nim'],
                'prodi_id' => $prodi->id,
                'atasan_id' => $sharedAtasan->id,
            ]);
        }

        $response = $this->actingAs($superadmin)->get('/superadmin/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('dashboardSummary.total_atasan', 2)
            ->where('supervisorDirectory', function ($supervisors): bool {
                $records = collect($supervisors);

                return $records->count() === 2
                    && $records->every(fn (array $record): bool => ! array_key_exists('alumni_count', $record))
                    && $records->pluck('alumni_name')->sort()->values()->all() === ['Alumni Relasi Dua', 'Alumni Relasi Satu'];
            })
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

        $currentYear = (string) now()->year;
        $previousYear = (string) (now()->year - 1);

        $currentYearUser = User::create([
            'username' => '72200011',
            'name' => 'Alumni Tahun Berjalan',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '72200011',
            'nama' => 'Alumni Tahun Berjalan',
            'tahun_lulus' => (int) $currentYear,
        ]);
        Biodata::create([
            'user_id' => $currentYearUser->id,
            'nim' => '72200011',
            'nama' => 'Alumni Tahun Berjalan',
            'prodi_id' => $prodi->id,
        ]);

        $previousYearUser = User::create([
            'username' => '72200012',
            'name' => 'Alumni Tahun Lalu',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '72200012',
            'nama' => 'Alumni Tahun Lalu',
            'tahun_lulus' => (int) $previousYear,
        ]);
        Biodata::create([
            'user_id' => $previousYearUser->id,
            'nim' => '72200012',
            'nama' => 'Alumni Tahun Lalu',
            'prodi_id' => $prodi->id,
        ]);

        $response = $this->actingAs($superadmin)->get('/superadmin/dashboard?tahun='.$previousYear);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('selectedYear', $previousYear)
            ->where('availableYears', fn ($years) => collect($years)->contains($previousYear) && collect($years)->contains($currentYear))
            ->where('stats.total_alumni', 1)
            ->where('completionMetrics.population', 1)
            ->where('prodiSummaries.0.total_alumni', 1)
            ->where('recentAlumni.0.tahun_lulus', (int) $previousYear)
        );
    }

    public function test_dashboard_classifies_response_rate_by_three_thresholds(): void
    {
        $univSection = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_DASH',
            'target' => 'univ',
            'judul' => 'Bagian Universitas',
            'order' => 1,
            'is_active' => true,
        ]);

        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F8',
            'teks_pertanyaan' => 'Status utama setelah lulus',
            'tipe' => 'radio',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $univSection->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
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
                    KuesionerJawaban::create([
                        'responden_type' => 'alumni',
                        'biodata_id' => $biodata->id,
                        'section_id' => $univSection->id,
                        'pertanyaan_id' => $question->id,
                        'kode_pertanyaan' => 'F8',
                        'jawaban_teks' => 'Bekerja',
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
        $univSection = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_MANDATORY',
            'target' => 'univ',
            'judul' => 'Bagian Universitas Wajib',
            'order' => 1,
            'is_active' => true,
        ]);

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

        foreach (['F8', 'F504', 'F14', 'F15', 'F502'] as $index => $code) {
            $question = KuesionerPertanyaan::create([
                'kode_pertanyaan' => $code,
                'teks_pertanyaan' => 'Butir '.$code,
                'tipe' => 'radio',
                'is_active' => true,
            ]);

            KuesionerSectionPertanyaan::create([
                'section_id' => $univSection->id,
                'pertanyaan_id' => $question->id,
                'order' => $index + 1,
                'wajib' => true,
                'tampil_di' => 'kuesioner',
            ]);

            KuesionerJawaban::create([
                'responden_type' => 'alumni',
                'biodata_id' => $biodata->id,
                'section_id' => $univSection->id,
                'pertanyaan_id' => $question->id,
                'kode_pertanyaan' => $code,
                'jawaban_teks' => 'Terisi',
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
