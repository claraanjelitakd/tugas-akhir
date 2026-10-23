<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use App\Services\LinkedIn\Contracts\LinkedInProfileProvider;
use App\Services\LinkedIn\DTOs\LinkedInPosition;
use App\Services\LinkedIn\DTOs\LinkedInProfile;
use App\Services\LinkedIn\Exceptions\LinkedInProfileNotFoundException;
use App\Services\LinkedIn\Exceptions\LinkedInSyncException;
use App\Services\LinkedIn\Mappers\LinkedInProfileMapper;
use App\Services\LinkedIn\Providers\ApiLinkedInProvider;
use App\Services\LinkedIn\Providers\MockLinkedInProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SuperAdminLinkedInSyncTest
 *
 * Pengujian komprehensif untuk fitur Sinkronisasi LinkedIn Super Admin:
 * - Pengujian Mock Provider (Success, Not Found, Skipped/Empty)
 * - Pengujian Pemetaan Posisi Aktif & Perusahaan (Status Verifikasi Menunggu Verifikasi)
 * - Pengujian Integritas Data Otoritatif
 * - Pengujian Rute & Transaksi Controller
 */
class SuperAdminLinkedInSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_sync_test',
            'name' => 'Super Administrator Test',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->prodi = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
        ]);

        Config::set('services.linkedin.provider', 'official');
    }

    /**
     * Helper untuk membuat data alumni uji.
     */
    protected function createAlumni(string $nim, string $name, ?string $linkedinUsername = null, int $tahunLulus = 2024): Biodata
    {
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
            'tahun_lulus' => $tahunLulus,
            'tahun_akademik_lulus' => 'Gasal 2024/2025',
            'status_mahasiswa' => 'L',
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'prodi_id' => $this->prodi->id,
            'email_pribadi' => "{$nim}@personal.com",
            'nomor_telepon' => '081234567890',
            'linkedin_username' => $linkedinUsername,
            'posisi_jabatan' => 'Staff Awal',
        ]);
    }

    /**
     * Test 1: Mock Provider - Skenario SUCCESS
     */
    public function test_mock_provider_returns_profile_dto_on_success(): void
    {
        $provider = new MockLinkedInProvider;
        $profile = $provider->findByUsername('johndoe');

        $this->assertInstanceOf(LinkedInProfile::class, $profile);
        $this->assertNotEmpty($profile->id);
        $this->assertEquals('johndoe', $profile->vanityName);
        $this->assertNotEmpty($profile->localizedFirstName);
        $this->assertNotEmpty($profile->localizedHeadline);
        $this->assertNotEmpty($profile->positions);

        $position = $profile->positions[0];
        $this->assertInstanceOf(LinkedInPosition::class, $position);
        $this->assertTrue($position->isCurrent());
        $this->assertNotEmpty($position->localizedCompanyName);
        $this->assertNotEmpty($position->localizedTitle);
    }

    /**
     * Test 2: Mock Provider - Skenario NOT FOUND
     */
    public function test_mock_provider_throws_exception_on_not_found(): void
    {
        $this->expectException(LinkedInProfileNotFoundException::class);

        $provider = new MockLinkedInProvider;
        $provider->findByUsername('notfound_user_test');
    }

    /**
     * Test 3: Mock Provider - Skenario EMPTY USERNAME
     */
    public function test_mock_provider_returns_null_on_empty_username(): void
    {
        $provider = new MockLinkedInProvider;
        $result = $provider->findByUsername('');

        $this->assertNull($result);
    }

    /**
     * Test 4: Mapper - Current Position Selection Rules
     */
    public function test_mapper_selects_active_position_correctly(): void
    {
        $mapper = new LinkedInProfileMapper;

        // 1. Skenario: 1 posisi aktif dan 1 posisi masa lalu
        $posActive = new LinkedInPosition(
            id: 1,
            companyName: ['localized' => ['en_US' => 'PT Aktif']],
            localizedCompanyName: 'PT Aktif',
            title: ['localized' => ['en_US' => 'Senior Developer']],
            localizedTitle: 'Senior Developer',
            startMonthYear: ['year' => 2024, 'month' => 1],
            endMonthYear: null // Aktif
        );

        $posPast = new LinkedInPosition(
            id: 2,
            companyName: ['localized' => ['en_US' => 'PT Lama']],
            localizedCompanyName: 'PT Lama',
            title: ['localized' => ['en_US' => 'Junior Developer']],
            localizedTitle: 'Junior Developer',
            startMonthYear: ['year' => 2022, 'month' => 1],
            endMonthYear: ['year' => 2023, 'month' => 12] // Selesai
        );

        $profile = new LinkedInProfile(
            id: 'mock-1',
            firstName: ['localized' => ['en_US' => 'Test']],
            localizedFirstName: 'Test',
            lastName: ['localized' => ['en_US' => 'User']],
            localizedLastName: 'User',
            headline: ['localized' => ['en_US' => 'Headline']],
            localizedHeadline: 'Headline',
            vanityName: 'testuser',
            positions: [$posPast, $posActive]
        );

        $selected = $mapper->determineCurrentPosition($profile);
        $this->assertNotNull($selected);
        $this->assertEquals('PT Aktif', $selected->localizedCompanyName);
        $this->assertEquals('Senior Developer', $selected->localizedTitle);
    }

    /**
     * Test 5: Perusahaan baru WAJIB berstatus 'Menunggu Verifikasi' meskipun data lengkap
     */
    public function test_newly_created_company_status_is_always_menunggu_verifikasi(): void
    {
        $this->actingAs($this->superadmin);

        $mapper = new LinkedInProfileMapper;
        $company = $mapper->findOrCreateCompany('PT Inovasi Teknologi Baru', 'Daerah Istimewa Yogyakarta, Indonesia');

        $this->assertInstanceOf(Perusahaan::class, $company);
        $this->assertEquals('PT Inovasi Teknologi Baru', $company->nama_perusahaan);
        $this->assertEquals('Menunggu Verifikasi', $company->status_verifikasi);
    }

    /**
     * Test 6: Integritas Data Otoritatif Alumni Tidak Berubah
     */
    public function test_authoritative_personal_fields_are_preserved(): void
    {
        $this->actingAs($this->superadmin);

        $alumni = $this->createAlumni('71200001', 'Alumni Asli', 'alumniasli');

        $originalNim = $alumni->nim;
        $originalPhone = $alumni->nomor_telepon;
        $originalEmail = $alumni->email_pribadi;

        $response = $this->postJson("/superadmin/linkedin-sync/{$alumni->id}");
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $fresh = $alumni->fresh();
        $this->assertEquals($originalNim, $fresh->nim);
        $this->assertEquals($originalPhone, $fresh->nomor_telepon);
        $this->assertEquals($originalEmail, $fresh->email_pribadi);

        // Namun posisi jabatan dan perusahaan harus terisi
        $this->assertNotEmpty($fresh->posisi_jabatan);
        $this->assertNotNull($fresh->perusahaan_id);
    }

    /**
     * Test 7: Audit LogActivity Dicatat dengan Action linkedin_sync
     */
    public function test_sync_records_log_activity(): void
    {
        $this->actingAs($this->superadmin);

        $alumni = $this->createAlumni('71200002', 'Budi Santoso', 'budisantoso');

        $response = $this->postJson("/superadmin/linkedin-sync/{$alumni->id}");
        $response->assertOk();

        $log = LogActivity::where('action', 'linkedin_sync')
            ->where('model_id', $alumni->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->superadmin->id, $log->user_id);
        $this->assertEquals('Berhasil', $log->new_values['status'] ?? null);
    }

    /**
     * Test 8: Skenario Alumni Tanpa Username (SKIPPED)
     */
    public function test_sync_skips_when_username_is_empty(): void
    {
        $this->actingAs($this->superadmin);

        $alumni = $this->createAlumni('71200003', 'Citra Tanpa Akun', null);

        $response = $this->postJson("/superadmin/linkedin-sync/{$alumni->id}");
        $response->assertOk();
        $response->assertJson([
            'status' => 'SKIPPED',
            'message' => 'LinkedIn username belum tersedia.',
        ]);
    }

    /**
     * Test 9: Skenario NOT FOUND pada Endpoint
     */
    public function test_sync_handles_not_found_gracefully(): void
    {
        $this->actingAs($this->superadmin);

        $alumni = $this->createAlumni('71200004', 'Deni Not Found', 'notfound_deni');

        $response = $this->postJson("/superadmin/linkedin-sync/{$alumni->id}");
        $response->assertOk();
        $response->assertJson([
            'status' => 'NOT_FOUND',
            'message' => 'Profil tidak ditemukan.',
        ]);
    }

    /**
     * Test 10: Bulk Sync Berjalan Independen Per Alumni
     */
    public function test_bulk_sync_processes_each_alumni_independently(): void
    {
        $this->actingAs($this->superadmin);

        $this->createAlumni('71200005', 'Alumni Sukses 1', 'sukses1', 2024);
        $this->createAlumni('71200006', 'Alumni Sukses 2', 'sukses2', 2024);
        $this->createAlumni('71200007', 'Alumni Gagal', 'notfound_gagal', 2024);
        $this->createAlumni('71200008', 'Alumni Kosong', null, 2024);

        $response = $this->postJson('/superadmin/linkedin-sync/batch', [
            'tahun' => '2024',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'summary' => [
                'total' => 4,
                'berhasil' => 2,
                'gagal' => 1,
                'dilewati' => 1,
            ],
        ]);
    }

    /**
     * Test 11: Superadmin Index Halaman LinkedIn Sync
     */
    public function test_superadmin_can_view_linkedin_sync_index(): void
    {
        $this->actingAs($this->superadmin);

        $this->createAlumni('71200009', 'Alumni Index', 'alumniindex', 2024);

        $response = $this->get('/superadmin/linkedin-sync?tahun=2024');
        $response->assertOk();
    }

    /**
     * Test 12: Mock Provider membaca berkas Mock JSON statis (johndoe.json)
     */
    public function test_mock_provider_loads_from_mock_json_file(): void
    {
        $provider = new MockLinkedInProvider;
        $profile = $provider->findByUsername('johndoe');

        $this->assertInstanceOf(LinkedInProfile::class, $profile);
        $this->assertEquals('johndoe', $profile->vanityName);
        $this->assertEquals('John Doe', $profile->getFullName());
        $this->assertEquals('ABC Technology', $profile->positions[0]->localizedCompanyName);
        $this->assertEquals('Software Engineer', $profile->positions[0]->localizedTitle);
    }

    /**
     * Test 13: Container me-resolve MockLinkedInProvider saat LINKEDIN_DRIVER='mock'
     */
    public function test_container_resolves_mock_provider_when_driver_is_mock(): void
    {
        config(['services.linkedin.driver' => 'mock']);

        $resolved = app(LinkedInProfileProvider::class);
        $this->assertInstanceOf(MockLinkedInProvider::class, $resolved);
    }

    /**
     * Test 14: Container me-resolve ApiLinkedInProvider saat LINKEDIN_DRIVER='api'
     */
    public function test_container_resolves_api_provider_when_driver_is_api(): void
    {
        config(['services.linkedin.driver' => 'api']);

        $resolved = app(LinkedInProfileProvider::class);
        $this->assertInstanceOf(ApiLinkedInProvider::class, $resolved);
    }

    /**
     * Test 15: ApiLinkedInProvider melempar exception aman jika kredensial belum diatur
     */
    public function test_api_provider_throws_safe_exception_when_credentials_empty(): void
    {
        $this->expectException(LinkedInSyncException::class);

        $provider = new ApiLinkedInProvider(apiKey: null, apiBaseUrl: null);
        $provider->findByUsername('anyuser');
    }

    /**
     * Test 16: ApiLinkedInProvider menormalisasi respons HTTP eksternal ke LinkedInProfile DTO
     */
    public function test_api_provider_normalizes_http_payload_to_dto(): void
    {
        Http::fake([
            'https://api.linkedin.com/v2/profiles/budi-santoso' => Http::response([
                'id' => 'api-person-99',
                'localizedFirstName' => 'Budi',
                'localizedLastName' => 'Santoso',
                'localizedHeadline' => 'Lead Engineer at PT Teknologi Maju',
                'vanityName' => 'budi-santoso',
                'positions' => [
                    [
                        'id' => 888,
                        'localizedCompanyName' => 'PT Teknologi Maju',
                        'localizedTitle' => 'Lead Engineer',
                        'startMonthYear' => ['year' => 2024, 'month' => 1],
                        'endMonthYear' => null,
                        'geoPositionLocation' => [
                            'localizedDisplayLocationName' => 'Jakarta, Indonesia',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $provider = new ApiLinkedInProvider(
            apiKey: 'mock-secret-token-key',
            apiBaseUrl: 'https://api.linkedin.com/v2'
        );

        $profile = $provider->findByUsername('budi-santoso');
        $this->assertInstanceOf(LinkedInProfile::class, $profile);
        $this->assertEquals('Budi Santoso', $profile->getFullName());
        $this->assertEquals('Lead Engineer', $profile->positions[0]->localizedTitle);
        $this->assertEquals('PT Teknologi Maju', $profile->positions[0]->localizedCompanyName);
    }

    /**
     * Test 17: Container melempar InvalidArgumentException jika driver tidak dikenali
     */
    public function test_container_throws_exception_on_unsupported_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        config(['services.linkedin.driver' => 'unsupported_driver_xyz']);
        app(LinkedInProfileProvider::class);
    }
}
