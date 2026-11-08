<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kuesioner;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\Ump;
use App\Models\User;
use App\Services\Analytics\TracerAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Memastikan route dan kontrak view analitik Superadmin tetap dapat dibaca.
 */
class SuperAdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function insertUnifiedQuestion(string $code, string $text, string $type = 'radio', bool $wajib = true): int
    {
        $section = KuesionerSection::firstOrCreate(
            ['kode_section' => 'SEC_UNIV_MAIN'],
            ['target' => 'univ', 'judul' => 'Kuesioner Universitas', 'order' => 1, 'is_active' => true]
        );

        $pertanyaan = KuesionerPertanyaan::firstOrCreate(
            ['kode_pertanyaan' => $code],
            ['teks_pertanyaan' => $text, 'tipe' => $type, 'is_active' => true]
        );

        KuesionerSectionPertanyaan::firstOrCreate(
            ['section_id' => $section->id, 'pertanyaan_id' => $pertanyaan->id],
            ['wajib' => $wajib, 'order' => 1]
        );

        return $pertanyaan->id;
    }

    private function insertUnifiedAnswer(int $biodataId, string $code, string $text, ?string $answer, ?array $answerJson = null, ?int $year = null): void
    {
        $qId = $this->insertUnifiedQuestion($code, $text);
        $section = KuesionerSection::where('target', 'univ')->first();

        KuesionerJawaban::updateOrCreate(
            ['biodata_id' => $biodataId, 'pertanyaan_id' => $qId],
            [
                'section_id' => $section->id,
                'jawaban' => $answer,
                'jawaban_teks' => $answer,
                'jawaban_json' => $answerJson ? json_encode($answerJson) : null,
                'tahun_lulus' => (string) ($year ?? now()->year),
            ]
        );
    }

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
     * Response rate hanya mengagregasi jawaban dari kohort dan prodi yang
     * diminta tanpa memasukkan respons dari populasi lain.
     */
    public function test_response_rate_scopes_respondents_to_the_selected_cohort_and_study_program(): void
    {
        $year = (int) now()->year;
        $includedProdi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
        $excludedProdi = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);
        $includedUser = User::create([
            'username' => 'response-rate-included',
            'name' => 'Alumni Terpilih',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        $excludedUser = User::create([
            'username' => 'response-rate-excluded',
            'name' => 'Alumni Tidak Terpilih',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '72200011',
            'nama' => 'Alumni Terpilih',
            'tanggal_lahir' => '2001-08-15',
            'tahun_lulus' => $year,
        ]);
        DataAkademik::create([
            'nim' => '71200011',
            'nama' => 'Alumni Tidak Terpilih',
            'tanggal_lahir' => '2001-08-15',
            'tahun_lulus' => $year,
        ]);
        $includedBiodata = Biodata::create([
            'user_id' => $includedUser->id,
            'nim' => '72200011',
            'prodi_id' => $includedProdi->id,
            'nomor_telepon' => '081234567811',
            'email_pribadi' => 'included@example.test',
            'linkedin_url' => 'https://www.linkedin.com/in/alumni-terpilih',
        ]);
        $excludedBiodata = Biodata::create([
            'user_id' => $excludedUser->id,
            'nim' => '71200011',
            'prodi_id' => $excludedProdi->id,
            'nomor_telepon' => '081234567812',
            'email_pribadi' => 'excluded@example.test',
        ]);
        $this->insertUnifiedAnswer($includedBiodata->id, 'F3', 'Kapan mulai mencari pekerjaan?', 'Sebelum lulus', null, $year);
        $this->insertUnifiedAnswer($excludedBiodata->id, 'F3', 'Kapan mulai mencari pekerjaan?', 'Setelah lulus', null, $year);

        $analytics = app(TracerAnalyticsService::class)
            ->buildUniversityChart('response_rate', (string) $year, null, $includedProdi->id);

        $this->assertSame(1, $analytics['chart']['summary']['population']);
        $this->assertSame(1, $analytics['chart']['summary']['respondents']);
        $this->assertSame('72200011', $analytics['chart']['records'][0]['nim']);
        $this->assertTrue($analytics['chart']['records'][0]['answered']);
        $this->assertSame('https://www.linkedin.com/in/alumni-terpilih', $analytics['chart']['records'][0]['linkedin_url']);
        $this->assertArrayHasKey('nama_perusahaan', $analytics['chart']['records'][0]);
        $this->assertArrayHasKey('head_office_lokasi', $analytics['chart']['records'][0]);
        $this->assertArrayHasKey('domisili_saat_ini', $analytics['chart']['records'][0]);
        $this->assertArrayHasKey('bidang_pekerjaan', $analytics['chart']['records'][0]);
        $this->assertArrayHasKey('divisi', $analytics['chart']['records'][0]);
        $this->assertArrayHasKey('posisi_jabatan', $analytics['chart']['records'][0]);
        $this->assertArrayHasKey('skills', $analytics['chart']['records'][0]);
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
            'kode_provinsi' => $province->kode_provinsi,
            'jenis_lokasi' => 'Dalam Negeri',
            'kode_negara' => 'ID',
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
            $this->assertArrayHasKey('methodology', $result['chart']);
            $this->assertNotEmpty($result['chart']['methodology']['formula'] ?? '');
            $this->assertNotEmpty($result['chart']['methodology']['denominator'] ?? '');
            $this->assertNotEmpty($result['chart']['methodology']['source'] ?? '');
            $this->assertNotEmpty($result['chart']['methodology']['tables'] ?? []);
            $this->assertNotEmpty($result['chart']['methodology']['views'] ?? []);
            $this->assertNotEmpty($result['chart']['methodology']['charts'] ?? []);

            if ($chartKey === 'competency_gap') {
                $this->assertSame(
                    'ROUND ke bilangan bulat terdekat untuk skor A, skor B, dan gap visual.',
                    $result['chart']['methodology']['rounding'],
                );
            }
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
        $this->insertUnifiedAnswer($biodata->id, 'F3', 'Kapan mulai mencari pekerjaan?', 'Sebelum lulus');

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
        $this->insertUnifiedAnswer($biodata->id, 'F3', 'Kapan mulai mencari pekerjaan?', 'Sebelum lulus');
        $this->insertUnifiedAnswer($biodata->id, 'F18b', 'Perguruan tinggi studi lanjut', 'Universitas Gadjah Mada', ['degree' => 'S2']);

        $chart = app(TracerAnalyticsService::class)
            ->buildUniversityChart('job_search_start', (string) now()->year);

        $this->assertSame(1, $chart['chart']['summary']['answered_count']);
        $this->assertSame('Sebelum lulus', $chart['chart']['series'][0]['label']);
        $this->assertSame('Sebelum lulus', $chart['chart']['records'][0]['answer']);

        $furtherStudy = app(TracerAnalyticsService::class)
            ->buildUniversityChart('further_study', (string) now()->year);

        $this->assertSame(1, $furtherStudy['chart']['summary']['answered_count']);
        $this->assertSame('S2', $furtherStudy['chart']['series'][0]['label']);
        $this->assertSame('Universitas Gadjah Mada', $furtherStudy['chart']['university_by_degree']['S2'][0]['university']);
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
            $this->insertUnifiedAnswer($biodata->id, $code, 'Pertanyaan '.$code, $answer);
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
        $this->insertUnifiedAnswer($secondBiodata->id, 'F14', 'Hubungan bidang studi', 'Kurang erat');

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

    /**
     * Memastikan payload response rate menyediakan data populasi, responden, belum mengisi, dan target.
     */
    public function test_response_rate_chart_returns_enhanced_payload(): void
    {
        $service = app(TracerAnalyticsService::class);
        $result = $service->buildUniversityChart('response_rate', (string) now()->year);

        $this->assertArrayHasKey('summary', $result['chart']);
        $this->assertArrayHasKey('population', $result['chart']['summary']);
        $this->assertArrayHasKey('respondents', $result['chart']['summary']);
        $this->assertArrayHasKey('unanswered', $result['chart']['summary']);
        $this->assertArrayHasKey('response_rate', $result['chart']['summary']);
        $this->assertArrayHasKey('unanswered_rate', $result['chart']['summary']);
        $this->assertArrayHasKey('target', $result['chart']['summary']);
        $this->assertArrayHasKey('series', $result['chart']);
        $this->assertIsArray($result['chart']['series']);

        if ($result['chart']['series'] !== []) {
            $firstSeriesItem = $result['chart']['series'][0];
            $this->assertContains('population', array_keys($firstSeriesItem));
            $this->assertContains('respondents', array_keys($firstSeriesItem));
            $this->assertContains('response_rate', array_keys($firstSeriesItem));
            $this->assertContains('target', array_keys($firstSeriesItem));
        }
    }

    /**
     * Superadmin dapat mengunduh berkas Excel response rate secara streaming.
     */
    public function test_superadmin_can_export_response_rate_excel(): void
    {
        $superadmin = User::create([
            'username' => 'superadmin-export-rr',
            'name' => 'Super Administrator Export',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($superadmin)->get('/superadmin/analitik/export-response-rate?tahun='.now()->year.'&status=all');

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Tracer_ResponseRate_', (string) $response->headers->get('content-disposition'));
    }

    /**
     * Target response rate membaca konfigurasi kuesioner dinamis dari database.
     */
    public function test_response_rate_uses_configured_target_from_kuesioner(): void
    {
        $targetYear = (int) now()->year;
        Kuesioner::create([
            'title' => 'Tracer Study Custom Target',
            'year' => $targetYear,
            'is_active' => true,
            'target_response_rate' => 75.0,
        ]);

        $result = app(TracerAnalyticsService::class)->buildUniversityChart('response_rate', (string) $targetYear);

        $this->assertSame(75.0, (float) $result['chart']['summary']['target']);
        $this->assertArrayHasKey('target_gap', $result['chart']['summary']);
    }

    /**
     * Filter semua tahun tersedia, sedangkan tanpa parameter tetap memakai tahun berjalan.
     */
    public function test_all_year_filter_is_available_and_current_year_remains_default(): void
    {
        $service = app(TracerAnalyticsService::class);

        $defaultResult = $service->buildUniversityChart('response_rate');
        $allYearsResult = $service->buildUniversityChart('response_rate', 'ALL');

        $this->assertSame((string) now()->year, $defaultResult['filters']['selected_year']);
        $this->assertSame('ALL', $allYearsResult['filters']['selected_year']);
        $this->assertSame('ALL', $allYearsResult['filters']['available_years'][0]);
    }

    /**
     * Penekanan metode pembelajaran F21-F27 menghitung skor rata-rata integer dalam rentang skala 1-5.
     */
    public function test_learning_methods_calculates_integer_average_scores_within_one_to_five_range(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);
        $user = User::create([
            'username' => 'alumni-f2-tester',
            'name' => 'Alumni F2 Tester',
            'email' => 'alumni-f2-tester@example.test',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '71200001',
            'nama' => 'Alumni F2 Tester',
            'tanggal_lahir' => '2001-01-01',
            'tahun_lulus' => 2024,
        ]);
        $biodata = Biodata::create([
            'user_id' => $user->id,
            'nim' => '71200001',
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567891',
            'email_pribadi' => 'alumni-f2-tester@example.test',
        ]);

        $this->insertUnifiedAnswer($biodata->id, 'F21', 'Perkuliahan', 'Besar', null, 2024);

        $result = app(TracerAnalyticsService::class)->buildUniversityChart('learning_methods', '2024');

        $this->assertSame('learning_methods', $result['selected_chart']);
        $this->assertArrayHasKey('scale_reference', $result['chart']);
        $this->assertSame(1, $result['chart']['scale_reference']['min']);
        $this->assertSame(5, $result['chart']['scale_reference']['max']);
        $this->assertArrayHasKey('summary', $result['chart']);
        $this->assertIsInt($result['chart']['summary']['overall_score_int']);

        $f21 = collect($result['chart']['series'])->firstWhere('key', 'F21');
        $this->assertNotNull($f21);
        $this->assertIsInt($f21['score']);
        $this->assertSame(4, $f21['score']);
        $this->assertSame('Besar', $f21['score_label']);
        $this->assertGreaterThanOrEqual(1, $f21['score']);
        $this->assertLessThanOrEqual(5, $f21['score']);
        $this->assertArrayHasKey('distribution', $f21);
    }

    /**
     * Indikator proses lamaran (application_pipeline) menyediakan 2 scatter plot dengan korelasi Spearman dan diagram konversi 3 tahap.
     */
    public function test_application_pipeline_provides_scatter_plots_and_conversion_stages(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $testData = [
            ['nim' => '72200001', 'name' => 'Alumni A', 'ipk' => 3.75, 'f6' => '10', 'f7' => '5', 'f7a' => '3'],
            ['nim' => '72200002', 'name' => 'Alumni B', 'ipk' => 3.50, 'f6' => '8', 'f7' => '4', 'f7a' => '2'],
            ['nim' => '72200003', 'name' => 'Alumni C', 'ipk' => 3.20, 'f6' => '5', 'f7' => '2', 'f7a' => '1'],
        ];

        foreach ($testData as $idx => $d) {
            $user = User::create([
                'username' => 'pipeline-tester-'.$idx,
                'name' => $d['name'],
                'email' => 'pipeline-'.$idx.'@example.test',
                'password' => Hash::make('password123'),
                'role' => 'alumni',
                'must_change_password' => false,
            ]);
            DataAkademik::create([
                'nim' => $d['nim'],
                'nama' => $d['name'],
                'tanggal_lahir' => '2001-01-01',
                'tahun_lulus' => 2024,
                'ip_kumulatif' => $d['ipk'],
            ]);
            $biodata = Biodata::create([
                'user_id' => $user->id,
                'nim' => $d['nim'],
                'prodi_id' => $prodi->id,
                'nomor_telepon' => '08123456789'.$idx,
                'email_pribadi' => 'pipeline-'.$idx.'@example.test',
            ]);
            $this->insertUnifiedAnswer($biodata->id, 'F6', 'Jumlah Perusahaan Dilamar', $d['f6'], null, 2024);
            $this->insertUnifiedAnswer($biodata->id, 'F7', 'Jumlah Perusahaan Merespons', $d['f7'], null, 2024);
            $this->insertUnifiedAnswer($biodata->id, 'F7A', 'Jumlah Panggilan Wawancara', $d['f7a'], null, 2024);
        }

        $service = app(TracerAnalyticsService::class);
        $result = $service->buildUniversityChart('application_pipeline', '2024');

        $this->assertSame('application_pipeline', $result['selected_chart']);
        $chart = $result['chart'];

        // 1. Verifikasi Scatter Plot 1: Lamaran vs Respons
        $this->assertArrayHasKey('scatter_applications_responses', $chart);
        $s1 = $chart['scatter_applications_responses'];
        $this->assertSame('Hubungan Jumlah Lamaran dengan Jumlah Respons Perusahaan', $s1['title']);
        $this->assertArrayHasKey('data', $s1);
        $this->assertCount(3, $s1['data']);
        $this->assertArrayHasKey('stats', $s1);
        $this->assertArrayHasKey('trendline', $s1);
        $this->assertArrayHasKey('summary', $s1);
        $this->assertNotNull($s1['stats']['rho']);
        $this->assertNotNull($s1['stats']['p_value']);
        $this->assertSame(3, $s1['stats']['n']);

        // 2. Verifikasi Scatter Plot 2: Respons vs Wawancara
        $this->assertArrayHasKey('scatter_responses_interviews', $chart);
        $s2 = $chart['scatter_responses_interviews'];
        $this->assertSame('Hubungan Jumlah Respons Perusahaan dengan Undangan Wawancara', $s2['title']);
        $this->assertArrayHasKey('data', $s2);
        $this->assertCount(3, $s2['data']);
        $this->assertArrayHasKey('stats', $s2);
        $this->assertArrayHasKey('trendline', $s2);
        $this->assertNotNull($s2['stats']['rho']);
        $this->assertNotNull($s2['stats']['p_value']);
        $this->assertSame(3, $s2['stats']['n']);

        // 3. Verifikasi Konversi 3 Tahap Berbobot
        $this->assertArrayHasKey('conversion_stages', $chart);
        $conv = $chart['conversion_stages'];
        $this->assertCount(3, $conv['stages']);
        $this->assertSame('Lamaran → Respons', $conv['stages'][0]['label']);
        $this->assertSame('Respons → Wawancara', $conv['stages'][1]['label']);
        $this->assertSame('Lamaran → Wawancara', $conv['stages'][2]['label']);
        $this->assertGreaterThan(0, $conv['stages'][0]['percentage']);
        $this->assertGreaterThan(0, $conv['stages'][1]['percentage']);
        $this->assertGreaterThan(0, $conv['stages'][2]['percentage']);
    }
}
