<?php

namespace Tests\Feature;

use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\User;
use Database\Seeders\UnifiedKuesionerSeeder;
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

        $this->seed(UnifiedKuesionerSeeder::class);
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
        $this->assertDatabaseHas('kuesioner_section', [
            'target' => 'atasan',
            'judul' => 'Kemampuan Khusus Bidang',
            'order' => 4,
        ]);

        $section = KuesionerSection::where('target', 'atasan')->where('judul', 'Kemampuan Khusus Bidang')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)->put("/superadmin/evaluasi-atasan/sections/{$section->id}", [
            'title' => 'Kemampuan Spesifik Industri',
            'description' => 'Diperbarui',
            'order' => 4,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kuesioner_section', [
            'id' => $section->id,
            'judul' => 'Kemampuan Spesifik Industri',
        ]);
    }

    /**
     * Memastikan superadmin dapat mengatur urutan naik/turun section.
     */
    public function test_superadmin_can_reorder_sections(): void
    {
        $sec1 = KuesionerSection::where('target', 'atasan')->where('order', 1)->firstOrFail();
        $sec2 = KuesionerSection::where('target', 'atasan')->where('order', 2)->firstOrFail();

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
        $section = KuesionerSection::where('target', 'atasan')->where('order', 3)->firstOrFail();

        $response = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/pertanyaan', [
            'section_id' => $section->id,
            'kode' => 'P99_kecepatan_belajar',
            'aspek' => 'Kecepatan Mempelajari Hal Baru',
            'deskripsi' => 'Kemampuan adaptasi teknologi dan metode kerja baru',
            'tipe' => 'likert_5',
            'order' => 25,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'kode_pertanyaan' => 'EVA_P99_KECEPATAN_BELAJAR',
            'teks_pertanyaan' => 'Kecepatan Mempelajari Hal Baru',
        ]);

        $newQ = KuesionerPertanyaan::where('kode_pertanyaan', 'EVA_P99_KECEPATAN_BELAJAR')->firstOrFail();

        $reorderResponse = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/pertanyaan/reorder', [
            'id' => $newQ->id,
            'direction' => 'up',
        ]);

        $reorderResponse->assertSessionHasNoErrors();
    }
}
