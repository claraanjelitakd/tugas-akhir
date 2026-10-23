<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LinkedinSyncResult;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use App\Services\LinkedIn\Contracts\LinkedInProfileProvider;
use App\Services\LinkedIn\Exceptions\LinkedInProfileNotFoundException;
use App\Services\LinkedIn\Exceptions\LinkedInSyncException;
use App\Services\LinkedIn\Providers\ApifyLinkedInProvider;
use App\Services\LinkedIn\Providers\MockLinkedInProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ApifyLinkedInProviderTest
 *
 * Pengujian komprehensif untuk penyedia pihak ketiga ApifyLinkedInProvider
 * dan alur staging/review (Approve & Reject) pada Super Admin.
 *
 * Sesuai spesifikasi, seluruh tes menggunakan HTTP Mock/Fake dan tidak memanggil
 * server Apify sungguhan, sehingga dapat berjalan tanpa API token asli.
 */
class ApifyLinkedInProviderTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_apify_test',
            'name' => 'Super Administrator Apify',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->prodi = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);

        Config::set('services.apify.api_token', 'mock_apify_token_test');
        Config::set('services.apify.linkedin_actor_id', 'data_forge_org~linkedin-scraper');
    }

    protected function createAlumni(
        string $nim,
        string $name,
        ?string $linkedinUrl = 'https://www.linkedin.com/in/test-alumni',
        ?string $linkedinUsername = 'test-alumni'
    ): Biodata {
        $user = User::create([
            'username' => $nim,
            'name' => $name,
            'email' => "{$nim}@students.ukdw.ac.id",
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => $nim,
            'nama' => $name,
            'prodi_id' => $this->prodi->id,
            'tahun_lulus' => 2024,
            'status_mahasiswa' => 'L',
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'prodi_id' => $this->prodi->id,
            'email_pribadi' => "{$nim}@personal.com",
            'linkedin_url' => $linkedinUrl,
            'linkedin_username' => $linkedinUsername,
            'posisi_jabatan' => 'Staff Pemula',
            'skills' => null,
            'experience' => null,
        ]);
    }

    protected function sampleApifyResponse(string $profileUrl = 'https://www.linkedin.com/in/test-alumni'): array
    {
        return [
            [
                'profileUrl' => $profileUrl,
                'name' => 'Test Alumni UKDW',
                'headline' => 'Lead Software Engineer at PT Bukalapak',
                'about' => 'Experienced software engineer focused on cloud technologies.',
                'location' => 'Yogyakarta, Indonesia',
                'experience' => [
                    [
                        'companyName' => 'PT Bukalapak.com Tbk',
                        'position' => 'Senior Backend Engineer',
                        'location' => 'Jakarta, Indonesia',
                        'startDate' => '2023-01',
                        'endDate' => null,
                        'isCurrent' => true,
                        'description' => 'Building scalable microservices.',
                    ],
                    [
                        'companyName' => 'PT Gameloft Indonesia',
                        'position' => 'Junior Programmer',
                        'location' => 'Yogyakarta, Indonesia',
                        'startDate' => '2021-06',
                        'endDate' => '2022-12',
                        'isCurrent' => false,
                    ],
                ],
                'education' => [
                    [
                        'schoolName' => 'Universitas Kristen Duta Wacana',
                        'degree' => 'Sarjana Komputer',
                        'fieldOfStudy' => 'Informatika',
                        'startDate' => '2019',
                        'endDate' => '2023',
                    ],
                ],
                'skills' => ['PHP', 'Laravel', 'Vue.js', 'PostgreSQL', 'Docker'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Test 1: Valid LinkedIn URL
    // -------------------------------------------------------------------------
    public function test_01_valid_linkedin_url_passes_validation(): void
    {
        $this->assertTrue(ApifyLinkedInProvider::isValidLinkedInUrl('https://www.linkedin.com/in/john-doe'));
        $this->assertTrue(ApifyLinkedInProvider::isValidLinkedInUrl('https://id.linkedin.com/in/johndoe/'));
        $this->assertTrue(ApifyLinkedInProvider::isValidLinkedInUrl('http://linkedin.com/in/jane-smith-123'));
        $this->assertFalse(ApifyLinkedInProvider::isValidLinkedInUrl('https://twitter.com/johndoe'));
        $this->assertFalse(ApifyLinkedInProvider::isValidLinkedInUrl(''));
        $this->assertFalse(ApifyLinkedInProvider::isValidLinkedInUrl(null));
        $this->assertFalse(ApifyLinkedInProvider::isValidLinkedInUrl('not-a-url'));
    }

    // -------------------------------------------------------------------------
    // Test 2: Successful Apify Response
    // -------------------------------------------------------------------------
    public function test_02_successful_apify_response(): void
    {
        $url = 'https://www.linkedin.com/in/test-alumni';
        Http::fake([
            'https://api.apify.com/v2/actors/*/run-sync-get-dataset-items' => Http::response($this->sampleApifyResponse($url), 200),
        ]);

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token', actorId: 'data_forge_org~linkedin-scraper');
        $raw = $provider->scrapeProfile($url);

        $this->assertIsArray($raw);
        $this->assertEquals($url, $raw[0]['profileUrl']);
        $this->assertEquals('Test Alumni UKDW', $raw[0]['name']);

        // Pastikan token dikirimkan melalui header Bearer, bukan query string
        Http::assertSent(function ($request) {
            return ! str_contains($request->url(), 'token=')
                && $request->hasHeader('Authorization', 'Bearer mock_token');
        });
    }

    // -------------------------------------------------------------------------
    // Test 3: Empty LinkedIn URL
    // -------------------------------------------------------------------------
    public function test_03_empty_linkedin_url_throws_exception(): void
    {
        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('URL LinkedIn alumni belum diisi');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('');
    }

    // -------------------------------------------------------------------------
    // Test 4: Invalid LinkedIn URL
    // -------------------------------------------------------------------------
    public function test_04_invalid_linkedin_url_throws_exception(): void
    {
        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Format URL LinkedIn tidak valid');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://facebook.com/bukan-linkedin');
    }

    // -------------------------------------------------------------------------
    // Test 5: Missing API Token
    // -------------------------------------------------------------------------
    public function test_05_missing_api_token_throws_exception(): void
    {
        Config::set('services.apify.api_token', null);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('APIFY_API_TOKEN belum dikonfigurasi');

        $provider = new ApifyLinkedInProvider(apiToken: null);
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 6: HTTP 400 Bad Request
    // -------------------------------------------------------------------------
    public function test_06_http_400_bad_request_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Invalid input'], 400),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Permintaan ke Apify tidak valid (Bad Request).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 7: HTTP 401 Unauthorized
    // -------------------------------------------------------------------------
    public function test_07_http_401_unauthorized_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Autentikasi Apify gagal. Periksa kembali konfigurasi API token.');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 8: HTTP 402 Payment Required
    // -------------------------------------------------------------------------
    public function test_08_http_402_payment_required_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Usage limit exceeded'], 402),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Batas kredit atau kuota Apify telah habis (Payment Required).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 9: HTTP 404 Not Found
    // -------------------------------------------------------------------------
    public function test_09_http_404_not_found_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Actor not found'], 404),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Actor Apify atau profil LinkedIn tidak ditemukan (404 Not Found).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 10: HTTP 429 Rate Limit
    // -------------------------------------------------------------------------
    public function test_10_http_429_rate_limit_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Too Many Requests'], 429),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Terlalu banyak permintaan ke Apify (Rate Limit Exceeded).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 11: HTTP 500 Server Error
    // -------------------------------------------------------------------------
    public function test_11_http_500_server_error_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Internal Error'], 500),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Server Apify mengalami gangguan atau batas waktu tercapai (HTTP 500).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 12: HTTP 502 Bad Gateway
    // -------------------------------------------------------------------------
    public function test_12_http_502_bad_gateway_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Bad Gateway'], 502),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Server Apify mengalami gangguan atau batas waktu tercapai (HTTP 502).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 13: HTTP 504 Gateway Timeout
    // -------------------------------------------------------------------------
    public function test_13_http_504_gateway_timeout_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response(['error' => 'Gateway Timeout'], 504),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Server Apify mengalami gangguan atau batas waktu tercapai (HTTP 504).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 14: Timeout (ConnectionException)
    // -------------------------------------------------------------------------
    public function test_14_timeout_connection_exception_throws_sync_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out');
            },
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Koneksi ke server Apify terputus atau melebihi batas waktu (timeout).');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 15: Network Error
    // -------------------------------------------------------------------------
    public function test_15_network_error_throws_sync_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => function () {
                throw new \RuntimeException('Network connection failed');
            },
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Terjadi kendala saat berkomunikasi dengan server Apify.');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 16: Malformed Response
    // -------------------------------------------------------------------------
    public function test_16_malformed_response_throws_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response('bukan-json-valid', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('Format data respons dari Apify tidak valid.');

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 17: Empty Dataset
    // -------------------------------------------------------------------------
    public function test_17_empty_dataset_throws_not_found_exception(): void
    {
        Http::fake([
            'https://api.apify.com/*' => Http::response([], 200),
        ]);

        $this->expectException(LinkedInProfileNotFoundException::class);

        $provider = new ApifyLinkedInProvider(apiToken: 'mock_token');
        $provider->scrapeProfile('https://www.linkedin.com/in/test-alumni');
    }

    // -------------------------------------------------------------------------
    // Test 18: Sync Single Saves As Pending Without Modifying Biodata
    // -------------------------------------------------------------------------
    public function test_18_sync_single_saves_to_linkedin_sync_results_as_pending_without_modifying_biodata(): void
    {
        Config::set('services.linkedin.provider', 'apify');

        $alumni = $this->createAlumni('71190001', 'Budi Santoso');
        $initialJob = $alumni->posisi_jabatan;

        Http::fake([
            'https://api.apify.com/*' => Http::response($this->sampleApifyResponse($alumni->linkedin_url), 200),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/{$alumni->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'PENDING',
        ]);

        // Verifikasi tabel staging
        $this->assertDatabaseHas('linkedin_sync_results', [
            'biodata_id' => $alumni->id,
            'status' => 'pending',
            'linkedin_url' => $alumni->linkedin_url,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        // KRITIKAL: Verifikasi data utama alumni TIDAK berubah sama sekali
        $fresh = $alumni->fresh();
        $this->assertEquals($initialJob, $fresh->posisi_jabatan);
        $this->assertNull($fresh->perusahaan_id);
    }

    // -------------------------------------------------------------------------
    // Test 19: Approve Succeeded for Pending Record
    // -------------------------------------------------------------------------
    public function test_19_approve_succeeds_for_pending_record(): void
    {
        $alumni = $this->createAlumni('71190002', 'Dewi Lestari');

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => $this->sampleApifyResponse(),
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$result->id}/approve");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $result->refresh();
        $this->assertEquals('approved', $result->status);
        $this->assertEquals($this->superadmin->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);
    }

    // -------------------------------------------------------------------------
    // Test 20: Approve Updates Main Data According To Mapping
    // -------------------------------------------------------------------------
    public function test_20_approve_updates_main_data_according_to_mapping(): void
    {
        $alumni = $this->createAlumni('71190003', 'Eko Prasetyo');

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => $this->sampleApifyResponse(),
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$result->id}/approve");

        $fresh = $alumni->fresh(['perusahaan']);

        // Jabatan diupdate dari current experience Apify
        $this->assertEquals('Senior Backend Engineer', $fresh->posisi_jabatan);

        // Perusahaan dibuat dan dihubungkan dengan status 'Menunggu Verifikasi'
        $this->assertNotNull($fresh->perusahaan);
        $this->assertEquals('PT Bukalapak.com Tbk', $fresh->perusahaan->nama_perusahaan);
        $this->assertEquals('Menunggu Verifikasi', $fresh->perusahaan->status_verifikasi);

        // Skills dipetakan ke field skills jika sebelumnya kosong
        $this->assertStringContainsString('PHP', $fresh->skills);
        $this->assertNotNull($fresh->experience);
    }

    // -------------------------------------------------------------------------
    // Test 21: Reject Does Not Modify Main Data
    // -------------------------------------------------------------------------
    public function test_21_reject_does_not_modify_main_data(): void
    {
        $alumni = $this->createAlumni('71190004', 'Fajar Ramadhan');
        $originalJob = $alumni->posisi_jabatan;

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => $this->sampleApifyResponse(),
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$result->id}/reject");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $result->refresh();
        $this->assertEquals('rejected', $result->status);
        $this->assertEquals($this->superadmin->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);

        // KRITIKAL: Data utama alumni TIDAK berubah
        $fresh = $alumni->fresh();
        $this->assertEquals($originalJob, $fresh->posisi_jabatan);
        $this->assertNull($fresh->perusahaan_id);
    }

    // -------------------------------------------------------------------------
    // Test 22: Re-sync Creates New History Record Preserving Past Records
    // -------------------------------------------------------------------------
    public function test_22_re_sync_creates_new_history_record_preserving_past_records(): void
    {
        Config::set('services.linkedin.provider', 'apify');

        $alumni = $this->createAlumni('71190005', 'Gita Gutawa');

        Http::fake([
            'https://api.apify.com/*' => Http::response($this->sampleApifyResponse(), 200),
        ]);

        // Sync Pertama
        $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/{$alumni->id}");

        $firstResult = LinkedinSyncResult::where('biodata_id', $alumni->id)->first();
        $this->assertNotNull($firstResult);
        $firstId = $firstResult->id;

        // Super Admin approve sync pertama
        $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$firstId}/approve");

        // Sync Kedua (Re-sync)
        $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/{$alumni->id}");

        // Verifikasi ada 2 record untuk alumni ini di staging
        $records = LinkedinSyncResult::where('biodata_id', $alumni->id)->orderBy('id')->get();
        $this->assertCount(2, $records);

        // Record pertama tetap approved
        $this->assertEquals('approved', $records[0]->status);
        $this->assertEquals($firstId, $records[0]->id);

        // Record kedua pending
        $this->assertEquals('pending', $records[1]->status);
        $this->assertNotEquals($firstId, $records[1]->id);
    }

    // -------------------------------------------------------------------------
    // Test 23: Official LinkedIn Provider Existing Still Functions Properly
    // -------------------------------------------------------------------------
    public function test_23_official_linkedin_provider_existing_still_functions_properly(): void
    {
        Config::set('services.linkedin.provider', 'official');
        Config::set('services.linkedin.driver', 'mock');

        $alumni = $this->createAlumni('71190006', 'Hendra Setiawan', 'https://www.linkedin.com/in/hendrasetiawan', 'hendrasetiawan');

        // Instance provider yang di-resolve oleh container adalah MockLinkedInProvider
        $resolvedProvider = app(LinkedInProfileProvider::class);
        $this->assertInstanceOf(MockLinkedInProvider::class, $resolvedProvider);

        // Jalankan sync single melalui official provider flow
        $response = $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/{$alumni->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'SUCCESS',
        ]);

        // Verifikasi data utama langsung disinkronkan oleh Mock Provider resmi
        $fresh = $alumni->fresh(['perusahaan']);
        $this->assertNotNull($fresh->posisi_jabatan);
        $this->assertNotNull($fresh->perusahaan);
    }

    // -------------------------------------------------------------------------
    // Test 24: Approve With Custom Skills and Experience Saves to Profile
    // -------------------------------------------------------------------------
    public function test_24_approve_with_custom_skills_saves_to_alumni_profile(): void
    {
        $alumni = $this->createAlumni('71190007', 'Benedictus Daniel');

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [
                'first_name' => 'Benedictus',
                'last_name' => 'Daniel',
                'headline' => 'Software Engineer',
                'summary' => 'Information Systems graduate with interest in IT Audit.',
                'skills' => [
                    ['name' => 'IT Audit'],
                    ['name' => 'Business Process Management'],
                ],
                'position' => [
                    [
                        'title' => 'System Analyst Intern',
                        'company_name' => 'PT K-24 INDONESIA OFFICIAL',
                    ],
                ],
            ],
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        // Verifikasi skills diekstrak murni dari field yang disediakan LinkedIn
        $preview = $result->preview;
        $this->assertNotEmpty($preview['skills']);
        $this->assertContains('IT Audit', $preview['skills']);
        $this->assertContains('Business Process Management', $preview['skills']);

        // Admin melakukan penyesuaian kustom pada modal review sebelum approve
        $response = $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$result->id}/approve", [
                'posisi_jabatan' => 'System Analyst Intern',
                'perusahaan_nama' => 'PT K-24 INDONESIA OFFICIAL',
                'jenis_pekerjaan' => 'swasta',
                'skills' => 'IT Audit, Business Process Management, Laravel, Vue.js',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $fresh = $alumni->fresh();
        $this->assertEquals('IT Audit, Business Process Management, Laravel, Vue.js', $fresh->skills);
        $this->assertEquals('System Analyst Intern', $fresh->posisi_jabatan);
    }

    public function test_approve_appends_all_scraped_experiences_without_duplicates(): void
    {
        $alumni = $this->createAlumni('71190008', 'Argo Wibowo');
        $alumni->update(['experience' => 'Programmer di RSUP Dr Kariadi']);

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [[
                'full_name' => 'Argo Wibowo',
                'experiences' => [
                    ['title' => 'Lecturer', 'company_name' => 'Universitas Kristen Duta Wacana', 'date' => 'Mar 2016 - Present', 'location' => 'Yogyakarta, Indonesia'],
                    ['title' => 'Developer', 'company_name' => 'Skyshi Digital Indonesia', 'date' => 'May 2015 - Feb 2016', 'location' => 'Yogyakarta'],
                    ['title' => 'Developer', 'company_name' => 'PT Finexus Jakarta', 'date' => 'Mar 2014 - Apr 2015', 'location' => 'Jakarta'],
                    ['title' => 'Programmer', 'company_name' => 'RSUP Dr Kariadi', 'date' => 'May 2011 - May 2012', 'location' => 'Semarang, Central Java, Indonesia'],
                ],
            ]],
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$result->id}/approve");

        $response->assertOk();
        $experienceLines = preg_split('/\r?\n/', (string) $alumni->fresh()->experience);

        $this->assertCount(5, $experienceLines);
        $this->assertSame('Programmer di RSUP Dr Kariadi', $experienceLines[0]);
        $this->assertStringContainsString('Lecturer di Universitas Kristen Duta Wacana', $experienceLines[1]);
        $this->assertStringContainsString('Developer di Skyshi Digital Indonesia', $experienceLines[2]);
        $this->assertStringContainsString('Developer di PT Finexus Jakarta', $experienceLines[3]);
        $this->assertStringContainsString('Programmer di RSUP Dr Kariadi', $experienceLines[4]);
    }

    public function test_approved_snapshot_can_be_plotted_again_without_overwriting_existing_experience(): void
    {
        $alumni = $this->createAlumni('71190009', 'Alumni Plot Ulang');
        $alumni->update(['experience' => 'Pengalaman manual sebelum LinkedIn']);

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [[
                'full_name' => 'Alumni Plot Ulang',
                'experiences' => [
                    ['title' => 'Lecturer', 'company_name' => 'Universitas Kristen Duta Wacana', 'date' => 'Mar 2016 - Present', 'location' => 'Yogyakarta'],
                    ['title' => 'Developer', 'company_name' => 'Skyshi Digital Indonesia', 'date' => 'May 2015 - Feb 2016', 'location' => 'Yogyakarta'],
                ],
            ]],
            'status' => 'approved',
            'reviewed_by' => $this->superadmin->id,
            'reviewed_at' => now(),
            'scraped_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$result->id}/plot-again");

        $response->assertOk()->assertJson([
            'success' => true,
        ]);

        $experienceLines = preg_split('/\r?\n/', (string) $alumni->fresh()->experience);

        $this->assertCount(3, $experienceLines);
        $this->assertSame('Pengalaman manual sebelum LinkedIn', $experienceLines[0]);
        $this->assertStringContainsString('Lecturer di Universitas Kristen Duta Wacana', $experienceLines[1]);
        $this->assertStringContainsString('Developer di Skyshi Digital Indonesia', $experienceLines[2]);
        $this->assertSame('approved', $result->fresh()->status);

        $this->actingAs($this->superadmin)
            ->postJson("/superadmin/linkedin-sync/results/{$result->id}/plot-again")
            ->assertOk();

        $this->assertCount(3, preg_split('/\r?\n/', (string) $alumni->fresh()->experience));
    }

    public function test_approve_dengan_request_inertia_mengembalikan_redirect(): void
    {
        $alumni = $this->createAlumni('71190010', 'Alumni Inertia');

        $result = LinkedinSyncResult::create([
            'biodata_id' => $alumni->id,
            'linkedin_url' => $alumni->linkedin_url,
            'scraped_data' => [[
                'full_name' => 'Alumni Inertia',
                'job_title' => 'Software Engineer',
            ]],
            'status' => 'pending',
            'scraped_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html, application/xhtml+xml',
            ])
            ->post("/superadmin/linkedin-sync/results/{$result->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
