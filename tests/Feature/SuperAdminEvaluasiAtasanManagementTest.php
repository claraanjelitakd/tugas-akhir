<?php

namespace Tests\Feature;

use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\SectionEvaluasiAtasan;
use App\Models\User;
use Database\Seeders\PertanyaanEvaluasiAtasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Manajemen Kuesioner Evaluasi Atasan (Section & Pertanyaan) oleh Superadmin.
 */
class SuperAdminEvaluasiAtasanManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'superadmin_eval_mgmt',
            'name' => 'Super Administrator',
            'email' => 'superadmin_eval_mgmt@example.com',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        $this->seed(PertanyaanEvaluasiAtasanSeeder::class);
    }

    /**
     * Memastikan superadmin dapat menambah bagian / section baru.
     */
    public function test_superadmin_can_store_and_update_section(): void
    {
        $response = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/sections', [
            'title' => 'Kemampuan Khusus Bidang',
            'description' => 'Evaluasi kompetensi khusus alumni sesuai bidang pekerjaan',
            'order' => 4,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('section_evaluasi_atasan', [
            'title' => 'Kemampuan Khusus Bidang',
            'order' => 4,
        ]);

        $section = SectionEvaluasiAtasan::where('title', 'Kemampuan Khusus Bidang')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)->put("/superadmin/evaluasi-atasan/sections/{$section->id}", [
            'title' => 'Kemampuan Spesifik Industri',
            'description' => 'Diperbarui',
            'order' => 4,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('section_evaluasi_atasan', [
            'id' => $section->id,
            'title' => 'Kemampuan Spesifik Industri',
        ]);
    }

    /**
     * Memastikan superadmin dapat mengatur urutan naik/turun section.
     */
    public function test_superadmin_can_reorder_sections(): void
    {
        $sec1 = SectionEvaluasiAtasan::where('key', 'instansi')->firstOrFail();
        $sec2 = SectionEvaluasiAtasan::where('key', 'karakteristik')->firstOrFail();

        $this->assertEquals(1, $sec1->order);
        $this->assertEquals(2, $sec2->order);

        $response = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/sections/reorder', [
            'id' => $sec2->id,
            'direction' => 'up',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(2, $sec1->fresh()->order);
        $this->assertEquals(1, $sec2->fresh()->order);
    }

    /**
     * Memastikan superadmin dapat menambah dan mengatur urutan pertanyaan evaluasi atasan.
     */
    public function test_superadmin_can_store_and_reorder_question(): void
    {
        $section = SectionEvaluasiAtasan::where('key', 'penilaian')->firstOrFail();

        $response = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/pertanyaan', [
            'section_id' => $section->id,
            'kode' => 'P99_kecepatan_belajar',
            'aspek' => 'Kecepatan Mempelajari Hal Baru',
            'deskripsi' => 'Kemampuan adaptasi teknologi dan metode kerja baru',
            'tipe' => 'likert_5',
            'order' => 25,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pertanyaan_evaluasi_atasan', [
            'kode' => 'p99_kecepatan_belajar',
            'aspek' => 'Kecepatan Mempelajari Hal Baru',
            'section_id' => $section->id,
        ]);

        $newQ = PertanyaanEvaluasiAtasan::where('kode', 'p99_kecepatan_belajar')->firstOrFail();
        $integritas = PertanyaanEvaluasiAtasan::where('kode', 'integritas')->firstOrFail();

        $reorderResponse = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/pertanyaan/reorder', [
            'id' => $newQ->id,
            'direction' => 'up',
        ]);

        $reorderResponse->assertSessionHasNoErrors();
    }
}
