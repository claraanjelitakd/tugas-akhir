<?php

namespace Tests\Feature;

use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\ProdiQuestionSection;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminPerusahaanDanKuesionerProdiTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected RefFakultas $fakultas;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_feature_test',
            'name' => 'Superadmin Feature Test',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->fakultas = RefFakultas::create([
            'kode_fakultas' => 'FTI',
            'nama_fakultas' => 'Fakultas Teknologi Informasi',
        ]);

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
            'fakultas_id' => $this->fakultas->id,
        ]);
    }

    /**
     * Test superadmin dapat melihat halaman verifikasi perusahaan (ACC Perusahaan) keseluruhan dan per prodi.
     */
    public function test_superadmin_dapat_mengakses_halaman_verifikasi_perusahaan(): void
    {
        // Sample pending company
        Perusahaan::create([
            'nama_perusahaan' => 'PT Teknologi Masa Depan Pending',
            'status_verifikasi' => 'Menunggu Verifikasi',
        ]);

        $responseAll = $this->actingAs($this->superadmin)
            ->get(route('superadmin.perusahaan.index'));

        $responseAll->assertStatus(200);

        $responsePerProdi = $this->actingAs($this->superadmin)
            ->get(route('superadmin.perusahaan.index', ['prodi_id' => $this->prodi->id]));

        $responsePerProdi->assertStatus(200);
    }

    public function test_halaman_perusahaan_mengirimkan_seluruh_field_master_ke_modal_detail(): void
    {
        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Detail Master',
            'propinsi_id' => null,
            'kabupaten_id' => null,
            'alamat' => 'Jl. Detail Master No. 1',
            'homepage' => 'https://detail-master.example',
            'no_telp_fax' => '0274123456',
            'kode_pos' => '55281',
            'sektor' => 'Teknologi Informasi',
            'skala' => 'Nasional',
            'bentuk_perusahaan' => 'Perusahaan Terbatas',
            'jumlah_pegawai' => '51 - 100 Orang',
            'jenis_perusahaan' => 'Perusahaan swasta',
            'jenis_perusahaan_lainnya' => 'Unit teknologi',
            'jenis_lokasi' => 'Dalam Negeri',
            'negara' => 'Indonesia',
            'status_verifikasi' => 'Terverifikasi',
            'created_by_user_id' => $this->superadmin->id,
            'created_by_prodi_id' => $this->prodi->id,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.perusahaan.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('companies.data.0.id', $company->id)
            ->where('companies.data.0.homepage', 'https://detail-master.example')
            ->where('companies.data.0.no_telp_fax', '0274123456')
            ->where('companies.data.0.bentuk_perusahaan', 'Perusahaan Terbatas')
            ->where('companies.data.0.jumlah_pegawai', '51 - 100 Orang')
            ->where('companies.data.0.jenis_perusahaan_lainnya', 'Unit teknologi')
            ->where('companies.data.0.created_by_user_id', $this->superadmin->id)
            ->where('companies.data.0.created_by_prodi_id', $this->prodi->id)
        );
    }

    /**
     * Test superadmin dapat menyetujui (verify) perusahaan secara langsung.
     */
    public function test_superadmin_dapat_menyetujui_perusahaan_langsung(): void
    {
        $pendingCompany = Perusahaan::create([
            'nama_perusahaan' => 'PT Inovasi UKDW',
            'status_verifikasi' => 'Menunggu Verifikasi',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.perusahaan.verify', $pendingCompany->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('perusahaan', [
            'id' => $pendingCompany->id,
            'status_verifikasi' => 'Terverifikasi',
        ]);
    }

    /**
     * Test superadmin dapat mengganti (auto replace) perusahaan dengan master terverifikasi.
     */
    public function test_superadmin_dapat_mengganti_perusahaan_auto_replace(): void
    {
        $verifiedCompany = Perusahaan::create([
            'nama_perusahaan' => 'PT Tokopedia Resmi',
            'status_verifikasi' => 'Terverifikasi',
        ]);

        $pendingCompany = Perusahaan::create([
            'nama_perusahaan' => 'Tokopedia',
            'status_verifikasi' => 'Menunggu Verifikasi',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.perusahaan.replace', $pendingCompany->id), [
                'target_company_id' => $verifiedCompany->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('perusahaan', [
            'id' => $pendingCompany->id,
        ]);
    }

    /**
     * Test superadmin dapat mengelola section kuesioner prodi (Pengaturan Kuesioner Prodi).
     */
    public function test_superadmin_dapat_mengelola_section_kuesioner_prodi(): void
    {
        $responseIndex = $this->actingAs($this->superadmin)
            ->get(route('superadmin.prodi-kuesioner.index', ['prodi_id' => $this->prodi->id]));

        $responseIndex->assertStatus(200);

        // Tambah section
        $responseStore = $this->actingAs($this->superadmin)
            ->post(route('superadmin.prodi-kuesioner.sections.store'), [
                'prodi_id' => $this->prodi->id,
                'title' => 'Evaluasi Kurikulum Prodi SI',
                'description' => 'Petunjuk pengisian untuk alumni SI',
                'order' => 1,
            ]);

        $responseStore->assertRedirect();
        $this->assertDatabaseHas('prodi_question_section', [
            'prodi_id' => $this->prodi->id,
            'title' => 'Evaluasi Kurikulum Prodi SI',
        ]);
    }

    /**
     * Test superadmin dapat mengelola butir pertanyaan kuesioner prodi.
     */
    public function test_superadmin_dapat_mengelola_pertanyaan_kuesioner_prodi(): void
    {
        $section = ProdiQuestionSection::create([
            'prodi_id' => $this->prodi->id,
            'title' => 'Bagian Spesifik SI',
            'order' => 1,
        ]);

        $responseStore = $this->actingAs($this->superadmin)
            ->post(route('superadmin.prodi-kuesioner.pertanyaan.store'), [
                'prodi_id' => $this->prodi->id,
                'prodi_question_section_id' => $section->id,
                'code' => 'PSI_01',
                'question_text' => 'Seberapa relevan mata kuliah basis data di dunia kerja?',
                'type' => 'single_choice',
                'is_required' => true,
                'order' => 1,
            ]);

        $responseStore->assertRedirect();
        $this->assertDatabaseHas('prodi_question', [
            'prodi_id' => $this->prodi->id,
            'code' => 'PSI_01',
            'question_text' => 'Seberapa relevan mata kuliah basis data di dunia kerja?',
        ]);
    }
}
