<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LinkedinSyncResult;
use App\Models\Prodi;
use App\Models\User;
use App\Services\LinkedIn\Contracts\LinkedInProfileProvider;
use App\Services\LinkedIn\Exceptions\LinkedInSyncException;
use App\Services\LinkedIn\Providers\ApifyLinkedInProvider;
use App\Services\LinkedIn\Providers\MockApifyLinkedInProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Memastikan mock Apify memiliki kontrak dataset actor dan tidak memanggil
 * jaringan ketika mode lokal digunakan.
 */
class MockApifyLinkedInProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixture lokal dapat dibaca sebagai payload actor Apify.
     */
    public function test_mock_apify_reads_actor_shaped_fixture(): void
    {
        $provider = new MockApifyLinkedInProvider;

        $payload = $provider->scrapeProfile('https://www.linkedin.com/in/eunike-alelang/');

        $this->assertSame('person', $payload[0]['entity_type']);
        $this->assertSame('people.enrich', $payload[0]['operation']);
        $this->assertSame('Eunike Larasati Zipora Alelang', $payload[0]['full_name']);
        Http::assertNothingSent();
    }

    /**
     * Fixture people.enrich diproyeksikan menjadi preview kanonik aplikasi.
     */
    public function test_people_enrich_fixture_maps_profile_fields(): void
    {
        $provider = new MockApifyLinkedInProvider;
        $payload = $provider->scrapeProfile('https://www.linkedin.com/in/eunike-alelang');

        $result = new LinkedinSyncResult(['scraped_data' => $payload]);
        $preview = $result->getPreviewData();

        $this->assertSame('Fullstack Developer', $preview['current_job']);
        $this->assertSame('Magna Solusi Indonesia', $preview['current_company']);
        $this->assertSame('Jakarta, Indonesia', $preview['location']);
        $this->assertSame('eunike-alelang', $preview['handle']);
        $this->assertSame(183, $preview['followers']);
        $this->assertSame('Universitas Kristen Duta Wacana', $preview['education'][0]['school']);
    }

    /**
     * Seluruh profil people.enrich yang dikirim pengguna tersedia sebagai
     * fixture terpisah berdasarkan handle URL.
     */
    public function test_all_validated_people_enrich_fixtures_are_readable(): void
    {
        $provider = new MockApifyLinkedInProvider;
        $handles = [
            'eunike-alelang',
            'argo-wibowo-99180935',
            'aydierahma',
            'evanhansefri',
            'rafif-hadyan',
            'andhikagaluh',
        ];

        foreach ($handles as $handle) {
            $payload = $provider->scrapeProfile("https://www.linkedin.com/in/{$handle}");

            $this->assertCount(1, $payload);
            $itemHandle = $payload[0]['li_profile_handle'] ?? $payload[0]['publicIdentifier'] ?? null;
            $this->assertSame($handle, $itemHandle);

            if (isset($payload[0]['entity_type'])) {
                $this->assertSame('person', $payload[0]['entity_type']);
            } else {
                $this->assertNotEmpty($payload[0]['id'] ?? $payload[0]['publicIdentifier'] ?? null);
            }
        }
    }

    /**
     * Respons actor berstatus BLOCKED harus menjadi exception dan tidak
     * diteruskan sebagai data profil yang valid.
     */
    public function test_apify_blocked_dataset_is_rejected(): void
    {
        Config::set('services.apify.api_token', 'test-token');
        Http::fake([
            'https://api.apify.com/v2/actors/*/run-sync-get-dataset-items' => Http::response([
                [
                    'entity_type' => 'error',
                    'error_label' => 'BLOCKED',
                    'message' => 'LinkedIn refused every attempt.',
                ],
            ], 200),
        ]);

        $this->expectException(LinkedInSyncException::class);
        $this->expectExceptionMessage('LinkedIn refused every attempt.');

        (new ApifyLinkedInProvider(apiToken: 'test-token'))->scrapeProfile('https://www.linkedin.com/in/eunike-alelang');
    }

    /**
     * Container memilih provider mock khusus ketika konfigurasi lokal aktif.
     */
    public function test_container_resolves_mock_apify_provider(): void
    {
        Config::set('services.linkedin.provider', 'mock_apify');

        $this->assertInstanceOf(MockApifyLinkedInProvider::class, app(LinkedInProfileProvider::class));
    }

    /**
     * Flow staging memakai fixture mock dan tidak mengubah profil utama alumni.
     */
    public function test_superadmin_can_stage_mock_apify_payload(): void
    {
        Config::set('services.linkedin.provider', 'mock_apify');
        $admin = User::create([
            'username' => 'mock-apify-admin',
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi']);
        $alumniUser = User::create([
            'username' => 'eunike-alelang',
            'name' => 'Eunike Larasati Zipora Alelang',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create(['nim' => '722026999', 'nama' => 'Eunike Larasati Zipora Alelang', 'tahun_lulus' => 2025]);
        $biodata = Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '722026999',
            'prodi_id' => $prodi->id,
            'linkedin_url' => 'https://www.linkedin.com/in/eunike-alelang/',
            'linkedin_username' => 'eunike-alelang',
        ]);

        $this->actingAs($admin)
            ->postJson('/superadmin/linkedin-sync/'.$biodata->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'PENDING');

        $this->assertDatabaseHas('linkedin_sync_results', [
            'biodata_id' => $biodata->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('biodata', [
            'id' => $biodata->id,
            'posisi_jabatan' => 'Fullstack Developer',
        ]);
    }
}
