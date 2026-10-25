<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\Ump;
use App\Models\User;
use App\Services\Analytics\TracerAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memastikan route dan kontrak view analitik Superadmin tetap dapat dibaca.
 */
class SuperAdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tamu harus diarahkan ke login dan tidak boleh membuka analitik.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/superadmin/analitik');

        $response->assertRedirect('/login');
    }

    /**
     * Endpoint JSON analitik tetap dilindungi middleware autentikasi.
     */
    public function test_guest_cannot_request_analytics_json_data(): void
    {
        $this->getJson('/superadmin/analitik/data')
            ->assertUnauthorized();
    }

    /**
     * Superadmin dapat merender halaman analitik tanpa data responden.
     */
    public function test_superadmin_can_render_analytics_page(): void
    {
        $superadmin = User::create([
            'username' => 'superadmin-analytics',
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($superadmin)->get('/superadmin/analitik');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Analytics/GrafikUniv')
            ->has('analytics.filters')
            ->has('analytics.chart_options')
            ->has('analytics.chart')
        );
    }

    /**
     * Endpoint data mandiri mengembalikan JSON agar filter grafik tidak
     * melakukan reload halaman Inertia.
     */
    public function test_superadmin_can_request_analytics_data_as_json(): void
    {
        $superadmin = User::create([
            'username' => 'superadmin-analytics-json',
            'name' => 'Super Administrator Analitik',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($superadmin)->getJson('/superadmin/analitik/data?grafik=wait_time');

        $response
            ->assertOk()
            ->assertJsonPath('selected_chart', 'wait_time')
            ->assertJsonStructure([
                'filters',
                'chart_options',
                'selected_chart',
                'chart' => ['records', 'data_availability'],
            ]);
    }

    /**
     * Agregasi gaji memakai array hasil normalisasi tanpa error object access.
     */
    public function test_analytics_can_aggregate_salary_rows_by_prodi(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
        $user = User::create([
            'username' => 'analytics-alumni',
            'name' => 'Alumni Analitik',
            'email' => 'analytics@example.test',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        $province = Propinsi::create([
            'kode_provinsi' => '34',
            'nama_provinsi' => 'DI Yogyakarta',
        ]);
        $company = Perusahaan::create([
            'nama_perusahaan' => 'Perusahaan Analitik',
            'propinsi_id' => $province->id,
            'jenis_lokasi' => 'Dalam Negeri',
            'negara' => 'Indonesia',
            'status_verifikasi' => 'Terverifikasi',
        ]);
        Ump::create([
            'kode_provinsi' => '34',
            'tahun' => (int) now()->year,
            'besaran' => 2400000,
        ]);
        DataAkademik::create([
            'nim' => '72200001',
            'nama' => 'Alumni Analitik',
            'email_pribadi' => 'analytics@example.test',
            'tanggal_lahir' => '2001-08-15',
            'tahun_lulus' => now()->year,
        ]);
        $biodata = Biodata::create([
            'user_id' => $user->id,
            'nim' => '72200001',
            'nama' => 'Alumni Analitik',
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567890',
            'email_pribadi' => 'analytics@example.test',
            'perusahaan_id' => $company->id,
            'gaji' => 3000000,
        ]);

        $analytics = app(TracerAnalyticsService::class)
            ->build((string) now()->year);

        $this->assertCount(1, $analytics['salary_vs_ump']['by_prodi']);
        $this->assertSame(1, $analytics['salary_vs_ump']['by_prodi'][0]['meeting_count']);
        $this->assertSame($biodata->prodi_id, (int) $analytics['salary_vs_ump']['by_prodi'][0]['key']);

        $chart = app(TracerAnalyticsService::class)
            ->buildUniversityChart('salary_vs_ump', (string) now()->year);

        $this->assertSame(1, $chart['chart']['summary']['meeting_count']);
        $this->assertSame(2880000.0, (float) $chart['chart']['records'][0]['threshold']);
    }

    /**
     * Setiap indikator aktif harus dapat membaca view terpilah tanpa 500.
     */
    public function test_each_university_chart_can_read_its_database_view(): void
    {
        $service = app(TracerAnalyticsService::class);

        foreach ([
            'wait_time',
            'salary_vs_ump',
            'work_location',
            'employment_status',
            'field_alignment',
            'education_alignment',
            'education_alignment_university',
            'response_rate',
            'profile_completion',
            'learning_methods',
            'job_search_start',
            'job_search_methods',
            'application_pipeline',
            'job_search_activity',
            'workplace_type',
            'further_study',
            'mismatch_reasons',
            'competency_gap',
        ] as $chartKey) {
            $result = $service->buildUniversityChart($chartKey, (string) now()->year);

            $this->assertSame($chartKey, $result['selected_chart']);
            $this->assertArrayHasKey('records', $result['chart']);
            $this->assertArrayHasKey('data_availability', $result['chart']);
        }
    }

    /**
     * Navigasi analitik mempertahankan seluruh 16 indikator evaluasi wajib.
     */
    public function test_university_analytics_exposes_all_sixteen_required_indicators(): void
    {
        $required = [
            'response_rate', 'learning_methods', 'job_search_start', 'job_search_methods',
            'wait_time', 'application_pipeline', 'employment_status', 'job_search_activity',
            'workplace_type', 'further_study', 'salary_vs_ump', 'field_alignment',
            'education_alignment', 'education_alignment_university', 'mismatch_reasons',
            'competency_gap',
        ];

        $result = app(TracerAnalyticsService::class)->buildUniversityChart(null, (string) now()->year);
        $available = array_column($result['chart_options'], 'key');

        $this->assertSame('response_rate', $result['selected_chart']);
        $this->assertSame([], array_values(array_diff($required, $available)));
        $this->assertArrayHasKey('metadata', $result['chart']);
        $this->assertArrayHasKey('missing_count', $result['chart']['metadata']);
    }

    /**
     * Response rate harus menghitung jawaban wajib dari view indikator khusus.
     */
    public function test_response_rate_uses_mandatory_answer_projection(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '74',
            'nama_prodi' => 'Arsitektur',
        ]);
        $user = User::create([
            'username' => 'analytics-response-rate',
            'name' => 'Alumni Response Rate',
            'email' => 'analytics-response-rate@example.test',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '74200001',
            'nama' => 'Alumni Response Rate',
            'tanggal_lahir' => '2001-08-15',
            'tahun_lulus' => now()->year,
        ]);
        $biodata = Biodata::create([
            'user_id' => $user->id,
            'nim' => '74200001',
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567899',
            'email_pribadi' => 'analytics-response-rate@example.test',
        ]);
        $questionId = DB::table('ref_subpertanyaan2021')->insertGetId([
            'kelompok' => 'F3',
            'kode_pertanyaan' => 'F3',
            'subpertanyaan' => 'Kapan mulai mencari pekerjaan?',
            'type' => 'radio',
            'wajib' => true,
            'order' => 1,
        ]);
        DB::table('tracer')->insert([
            'biodata_id' => $biodata->id,
            'question_id' => $questionId,
            'nim' => $biodata->nim,
            'kelompok' => 'F3',
            'kode_pertanyaan' => 'F3',
            'subpertanyaan' => 'Kapan mulai mencari pekerjaan?',
            'answer' => 'Sebelum lulus',
            'tahun_lulus' => now()->year,
        ]);

        $chart = app(TracerAnalyticsService::class)
            ->buildUniversityChart('response_rate', (string) now()->year);

        $this->assertSame(1, $chart['chart']['summary']['population']);
        $this->assertSame(1, $chart['chart']['summary']['respondents']);
        $this->assertSame(100.0, (float) $chart['chart']['summary']['response_rate']);
        $this->assertTrue($chart['chart']['records'][0]['answered']);
    }

    /**
     * Jawaban tracer kanonik harus masuk ke chart generik melalui database view.
     */
    public function test_generic_tracer_chart_reads_canonical_answer_view(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
        $user = User::create([
            'username' => 'analytics-tracer-answer',
            'name' => 'Alumni Jawaban',
            'email' => 'analytics-tracer-answer@example.test',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '72200002',
            'nama' => 'Alumni Jawaban',
            'email_pribadi' => 'analytics-tracer-answer@example.test',
            'tanggal_lahir' => '2001-08-15',
            'tahun_lulus' => now()->year,
        ]);
        $biodata = Biodata::create([
            'user_id' => $user->id,
            'nim' => '72200002',
            'nama' => 'Alumni Jawaban',
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567891',
            'email_pribadi' => 'analytics-tracer-answer@example.test',
        ]);
        DB::table('tracer')->insert([
            'biodata_id' => $biodata->id,
            'nim' => '72200002',
            'kelompok' => 'F3',
            'kode_pertanyaan' => 'F3',
            'subpertanyaan' => 'Kapan mulai mencari pekerjaan?',
            'answer' => 'Sebelum lulus',
            'tahun_lulus' => now()->year,
        ]);
        DB::table('tracer')->insert([
            'biodata_id' => $biodata->id,
            'nim' => '72200002',
            'kelompok' => 'F18',
            'kode_pertanyaan' => 'F18b',
            'subpertanyaan' => 'Perguruan tinggi studi lanjut',
            'answer' => 'Universitas Gadjah Mada',
            'tahun_lulus' => now()->year,
        ]);

        $chart = app(TracerAnalyticsService::class)
            ->buildUniversityChart('job_search_start', (string) now()->year);

        $this->assertSame(1, $chart['chart']['summary']['answered_count']);
        $this->assertSame('Sebelum lulus', $chart['chart']['series'][0]['label']);
        $this->assertSame('Sebelum lulus', $chart['chart']['records'][0]['answer']);

        $furtherStudy = app(TracerAnalyticsService::class)
            ->buildUniversityChart('further_study', (string) now()->year);

        $this->assertSame(1, $furtherStudy['chart']['summary']['answered_count']);
        $this->assertSame('Perguruan tinggi: Universitas Gadjah Mada', $furtherStudy['chart']['series'][0]['label']);
    }

    /**
     * Grafik dengan view khusus harus membaca kolom normalisasinya sendiri.
     */
    public function test_dedicated_tracer_charts_do_not_fall_back_to_generic_answer_columns(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);
        $user = User::create([
            'username' => 'analytics-dedicated-views',
            'name' => 'Alumni View Khusus',
            'email' => 'analytics-dedicated@example.test',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '71200003',
            'nama' => 'Alumni View Khusus',
            'email_pribadi' => 'analytics-dedicated@example.test',
            'tanggal_lahir' => '2001-08-15',
            'tahun_lulus' => now()->year,
        ]);
        $biodata = Biodata::create([
            'user_id' => $user->id,
            'nim' => '71200003',
            'nama' => 'Alumni View Khusus',
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567892',
            'email_pribadi' => 'analytics-dedicated@example.test',
        ]);

        foreach ([
            'F502' => '2',
            'F504' => 'Ya',
            'F8' => 'Bekerja (full time/part time)',
            'F14' => 'Erat',
            'F15' => 'Tingkat yang sama',
        ] as $code => $answer) {
            DB::table('tracer')->insert([
                'biodata_id' => $biodata->id,
                'nim' => $biodata->nim,
                'kelompok' => $code,
                'kode_pertanyaan' => $code,
                'subpertanyaan' => 'Pertanyaan '.$code,
                'answer' => $answer,
                'tahun_lulus' => now()->year,
            ]);
        }

        $secondUser = User::create([
            'username' => 'analytics-alignment-distinct',
            'name' => 'Alumni Keselarasan Berbeda',
            'email' => 'analytics-alignment-distinct@example.test',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '71200004',
            'nama' => 'Alumni Keselarasan Berbeda',
            'email_pribadi' => 'analytics-alignment-distinct@example.test',
            'tanggal_lahir' => '2001-08-16',
            'tahun_lulus' => now()->year,
        ]);
        $secondBiodata = Biodata::create([
            'user_id' => $secondUser->id,
            'nim' => '71200004',
            'nama' => 'Alumni Keselarasan Berbeda',
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567893',
            'email_pribadi' => 'analytics-alignment-distinct@example.test',
        ]);
        DB::table('tracer')->insert([
            'biodata_id' => $secondBiodata->id,
            'nim' => $secondBiodata->nim,
            'kelompok' => 'F14',
            'kode_pertanyaan' => 'F14',
            'subpertanyaan' => 'Hubungan bidang studi',
            'answer' => 'Kurang erat',
            'tahun_lulus' => now()->year,
        ]);

        $service = app(TracerAnalyticsService::class);
        $waitTime = $service->buildUniversityChart('wait_time', (string) now()->year);
        $employment = $service->buildUniversityChart('employment_status', (string) now()->year);
        $fieldAlignment = $service->buildUniversityChart('field_alignment', (string) now()->year);
        $educationAlignment = $service->buildUniversityChart('education_alignment', (string) now()->year);

        $this->assertSame(1, $waitTime['chart']['summary']['response_count']);
        $this->assertSame(2.0, (float) $waitTime['chart']['summary']['median_months']);
        $this->assertSame(1, $employment['chart']['summary']['answered_count']);
        $this->assertSame('Bekerja', $employment['chart']['series'][0]['label']);
        $this->assertSame(2, $fieldAlignment['chart']['summary']['answered_count']);
        $this->assertEqualsCanonicalizing(['Erat', 'Kurang erat'], array_column($fieldAlignment['chart']['series'], 'label'));
        $this->assertSame(1, $educationAlignment['chart']['summary']['answered_count']);
        $this->assertSame('Tingkat yang sama', $educationAlignment['chart']['series'][0]['label']);
    }
}
