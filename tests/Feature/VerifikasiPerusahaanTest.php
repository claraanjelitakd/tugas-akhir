<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Kabupaten;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\RefFakultas;
use App\Models\User;
use App\Services\Perusahaan\PerusahaanVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VerifikasiPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    protected RefFakultas $fakultas;

    protected Prodi $prodi;

    protected User $adminProdi;

    protected User $adminFakultas;

    protected Propinsi $propinsi;

    protected Kabupaten $kabupaten;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakultas = RefFakultas::create([
            'kode_fakultas' => 'FTI',
            'nama_fakultas' => 'Fakultas Teknologi Informasi',
        ]);

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
            'fakultas_id' => $this->fakultas->id,
        ]);

        $this->adminProdi = User::create([
            'username' => 'admin_si',
            'name' => 'Admin SI',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);

        $this->adminFakultas = User::create([
            'username' => 'admin_fti',
            'name' => 'Admin FTI',
            'password' => Hash::make('password123'),
            'role' => 'admin_fakultas',
            'fakultas_id' => $this->fakultas->id,
            'must_change_password' => false,
        ]);

        $this->propinsi = Propinsi::create([
            'kode_provinsi' => '34',
            'nama_provinsi' => 'D.I. Yogyakarta',
        ]);

        $this->kabupaten = Kabupaten::create([
            'propinsi_id' => $this->propinsi->id,
            'kode_kabupaten' => '3404',
            'nama_kabupaten' => 'Kabupaten Sleman',
        ]);
    }

    public function test_admin_prodi_dan_fakultas_dapat_mengakses_halaman_verifikasi_perusahaan(): void
    {
        $responseProdi = $this->actingAs($this->adminProdi)->get('/prodi/perusahaan');
        $responseProdi->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Perusahaan/Index')
            ->where('role', 'admin_prodi')
            ->where('basePath', '/prodi/perusahaan')
            ->where('canCreate', false)
        );

        $responseFakultas = $this->actingAs($this->adminFakultas)->get('/fakultas/perusahaan');
        $responseFakultas->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Perusahaan/Index')
            ->where('role', 'admin_fakultas')
            ->where('basePath', '/fakultas/perusahaan')
            ->where('canCreate', false)
        );
    }

    public function test_sistem_dapat_mendeteksi_rekomendasi_kemiripan_perusahaan(): void
    {
        // Buat master terverifikasi
        $verifiedBCA = Perusahaan::create([
            'nama_perusahaan' => 'PT Bank Central Asia Tbk',
            'propinsi_id' => $this->propinsi->id,
            'kabupaten_id' => $this->kabupaten->id,
            'status_verifikasi' => 'Terverifikasi',
        ]);

        // Inputan alumni
        $pendingBCA = Perusahaan::create([
            'nama_perusahaan' => 'BCA',
            'propinsi_id' => $this->propinsi->id,
            'kabupaten_id' => $this->kabupaten->id,
            'status_verifikasi' => 'Menunggu Verifikasi',
        ]);

        $service = app(PerusahaanVerificationService::class);
        $recommendations = $service->getSimilarRecommendations($pendingBCA);

        $this->assertNotEmpty($recommendations);
        $this->assertEquals($verifiedBCA->id, $recommendations[0]['id']);
        $this->assertTrue($recommendations[0]['is_same_city']);
        $this->assertGreaterThanOrEqual(70, $recommendations[0]['similarity_score']);
    }

    public function test_alur_pembersihan_string_dapat_merekomendasikan_akronim_nama_perusahaan_dengan_keterangan_cabang(): void
    {
        // Master terverifikasi memiliki imbuhan keterangan cabang/kantor pusat dalam tanda kurung
        $verifiedBCA = Perusahaan::create([
            'nama_perusahaan' => 'PT Bank Central Asia Tbk (Kantor Pusat)',
            'status_verifikasi' => 'Terverifikasi',
        ]);

        // Input pengajuan alumni mengandung kata stopword negara dan singkatan akronim
        $pendingBCA = Perusahaan::create([
            'nama_perusahaan' => 'PT BCA INDONESIA',
            'status_verifikasi' => 'Menunggu Verifikasi',
        ]);

        $service = app(PerusahaanVerificationService::class);
        $recommendations = $service->getSimilarRecommendations($pendingBCA);

        $this->assertNotEmpty($recommendations);
        $this->assertEquals($verifiedBCA->id, $recommendations[0]['id']);
        $this->assertEquals(88, $recommendations[0]['similarity_score']);
        $this->assertContains('Akronim / Singkatan Cocok', $recommendations[0]['match_reasons']);
    }

    public function test_admin_dapat_melakukan_auto_replace_perusahaan_ke_master_terverifikasi(): void
    {
        $verifiedCompany = Perusahaan::create([
            'nama_perusahaan' => 'PT Gojek Indonesia',
            'status_verifikasi' => 'Terverifikasi',
        ]);

        $pendingCompany = Perusahaan::create([
            'nama_perusahaan' => 'gojek',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_prodi_id' => $this->prodi->id,
        ]);

        $alumniUser = User::create([
            'username' => '72200001',
            'name' => 'Alumni Joshua',
            'password' => Hash::make('secret'),
            'role' => 'alumni',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200001',
            'nama' => 'Alumni Joshua',
        ]);

        $biodata = Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '72200001',
            'nama' => 'Alumni Joshua',
            'prodi_id' => $this->prodi->id,
            'perusahaan_id' => $pendingCompany->id,
        ]);

        $response = $this->actingAs($this->adminProdi)->post("/prodi/perusahaan/{$pendingCompany->id}/replace", [
            'target_company_id' => $verifiedCompany->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Biodata alumni harus otomatis terhubung ke verified company
        $biodata->refresh();
        $this->assertEquals($verifiedCompany->id, $biodata->perusahaan_id);

        // Record pending duplikat telah dibersihkan
        $this->assertDatabaseMissing('perusahaan', [
            'id' => $pendingCompany->id,
        ]);
    }

    public function test_admin_dapat_verifikasi_langsung_perusahaan(): void
    {
        $pendingCompany = Perusahaan::create([
            'nama_perusahaan' => 'Studio Kreatif Mandiri',
            'status_verifikasi' => 'Menunggu Verifikasi',
        ]);
        $alumni = User::create([
            'username' => '72200006',
            'name' => 'Alumni Studio Kreatif',
            'password' => Hash::make('secret'),
            'role' => 'alumni',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => '72200006',
            'nama' => 'Alumni Studio Kreatif',
        ]);
        Biodata::create([
            'user_id' => $alumni->id,
            'nim' => '72200006',
            'prodi_id' => $this->prodi->id,
            'perusahaan_id' => $pendingCompany->id,
        ]);

        $response = $this->actingAs($this->adminProdi)->post("/prodi/perusahaan/{$pendingCompany->id}/verify");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $pendingCompany->refresh();
        $this->assertEquals('Terverifikasi', $pendingCompany->status_verifikasi);
    }

    public function test_admin_prodi_cannot_verify_company_from_another_program(): void
    {
        $otherProgram = Prodi::create([
            'kode_prodi' => '99',
            'nama_prodi' => 'Program Studi Lain',
            'fakultas_id' => $this->fakultas->id,
        ]);
        $company = Perusahaan::create([
            'nama_perusahaan' => 'Perusahaan Program Lain',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_prodi_id' => $otherProgram->id,
        ]);

        $this->actingAs($this->adminProdi)
            ->post("/prodi/perusahaan/{$company->id}/verify")
            ->assertNotFound();

        $this->assertSame('Menunggu Verifikasi', $company->fresh()->status_verifikasi);
    }

    public function test_admin_dapat_edit_dan_verifikasi_perusahaan(): void
    {
        $pendingCompany = Perusahaan::create([
            'nama_perusahaan' => 'pt tokopedia lama',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_prodi_id' => $this->prodi->id,
        ]);

        $response = $this->actingAs($this->adminFakultas)->put("/fakultas/perusahaan/{$pendingCompany->id}", [
            'nama_perusahaan' => 'PT Tokopedia',
            'alamat' => 'Jl. Prof Dr Satrio',
            'propinsi_id' => $this->propinsi->id,
            'kabupaten_id' => $this->kabupaten->id,
            'skala' => 'Nasional',
            'jenis_lokasi' => 'Dalam Negeri',
            'negara' => 'Indonesia',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $pendingCompany->refresh();
        $this->assertEquals('PT Tokopedia', $pendingCompany->nama_perusahaan);
        $this->assertEquals('Terverifikasi', $pendingCompany->status_verifikasi);
    }

    public function test_alumni_input_perusahaan_langsung_muncul_di_admin_prodi(): void
    {
        $alumniUser = User::create([
            'username' => '72200005',
            'name' => 'Vanessa Alumni',
            'password' => Hash::make('secret'),
            'role' => 'alumni',
            'prodi_id' => $this->prodi->id,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200005',
            'nama' => 'Vanessa Alumni',
        ]);

        Biodata::create([
            'user_id' => $alumniUser->id,
            'nim' => '72200005',
            'nama' => 'Vanessa Alumni',
            'prodi_id' => $this->prodi->id,
        ]);

        // 1. Alumni login dan input perusahaan via modal /alumni/company
        $response = $this->actingAs($alumniUser)->postJson('/alumni/company', [
            'nama_perusahaan' => 'PT Gameloft Indonesia Jogja',
            'jenis_lokasi' => 'Dalam Negeri',
            'negara' => 'Indonesia',
            'province_id' => $this->propinsi->id,
            'kabupaten_id' => $this->kabupaten->id,
            'skala' => 'Nasional',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Perusahaan harus tercatat dengan created_by_user_id & created_by_prodi_id
        $company = Perusahaan::where('nama_perusahaan', 'PT Gameloft Indonesia Jogja')->first();
        $this->assertNotNull($company);
        $this->assertEquals('Menunggu Verifikasi', $company->status_verifikasi);
        $this->assertEquals($alumniUser->id, $company->created_by_user_id);
        $this->assertEquals($this->prodi->id, $company->created_by_prodi_id);

        // 2. Admin Prodi mengakses halaman /prodi/perusahaan dan perusahaan langsung muncul
        $adminResponse = $this->actingAs($this->adminProdi)->get('/prodi/perusahaan');
        $adminResponse->assertOk();
        $adminResponse->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Perusahaan/Index')
            ->where('role', 'admin_prodi')
            ->where('basePath', '/prodi/perusahaan')
            ->has('companies.data', 1)
            ->where('companies.data.0.nama_perusahaan', 'PT Gameloft Indonesia Jogja')
        );
    }

    public function test_admin_prodi_can_add_company_via_modal_endpoint(): void
    {
        $response = $this->actingAs($this->adminProdi)->postJson('/alumni/company', [
            'nama_perusahaan' => 'PT Duta Wacana Kreatif',
            'jenis_lokasi' => 'Dalam Negeri',
            'negara' => 'Indonesia',
            'province_id' => $this->propinsi->id,
            'kabupaten_id' => $this->kabupaten->id,
            'skala' => 'Nasional',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $company = Perusahaan::where('nama_perusahaan', 'PT Duta Wacana Kreatif')->first();
        $this->assertNotNull($company);
        $this->assertEquals('Menunggu Verifikasi', $company->status_verifikasi);
        $this->assertEquals($this->adminProdi->id, $company->created_by_user_id);
        $this->assertEquals($this->adminProdi->prodi_id, $company->created_by_prodi_id);
    }

    public function test_async_recommendations_endpoint_returns_json(): void
    {
        $verifiedCompany = Perusahaan::create([
            'nama_perusahaan' => 'PT Tokopedia Jaya',
            'status_verifikasi' => 'Terverifikasi',
            'propinsi_id' => $this->propinsi->id,
            'kabupaten_id' => $this->kabupaten->id,
        ]);

        $pendingCompany = Perusahaan::create([
            'nama_perusahaan' => 'PT Tokopedia',
            'status_verifikasi' => 'Menunggu Verifikasi',
            'created_by_prodi_id' => $this->prodi->id,
            'propinsi_id' => $this->propinsi->id,
            'kabupaten_id' => $this->kabupaten->id,
        ]);

        $response = $this->actingAs($this->adminProdi)->getJson("/prodi/perusahaan/{$pendingCompany->id}/recommendations");

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['id', 'nama_perusahaan', 'similarity_score'],
        ]);
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertEquals($verifiedCompany->id, $data[0]['id']);
    }

    public function test_cascading_kabupaten_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->adminProdi)->getJson("/api/wilayah/kabupaten/{$this->propinsi->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['id', 'propinsi_id', 'nama_kabupaten'],
        ]);
        $this->assertCount(1, $response->json());
        $this->assertEquals($this->kabupaten->nama_kabupaten, $response->json()[0]['nama_kabupaten']);
    }
}
