<?php

namespace Tests\Feature;

use App\Models\KuesionerOpsi;
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

    /**
     * Memastikan Radio + Angka (Bulan) memakai opsi singkat, dapat diisi ulang,
     * dan tidak menyimpan skor seperti pertanyaan Likert.
     */
    public function test_superadmin_can_store_month_question_with_default_options_without_scores(): void
    {
        $section = KuesionerSection::where('target', 'atasan')->where('order', 3)->firstOrFail();

        $response = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/pertanyaan', [
            'section_id' => $section->id,
            'kode' => 'P100_waktu_mulai_kerja',
            'aspek' => 'Kapan mulai mencari pekerjaan?',
            'deskripsi' => 'Isi jumlah bulan jika memilih opsi sesudah lulus.',
            'tipe' => 'radio_input',
            'order' => 26,
        ]);

        $response->assertSessionHasNoErrors();

        $question = KuesionerPertanyaan::with('opsi')
            ->where('kode_pertanyaan', 'EVA_P100_WAKTU_MULAI_KERJA')
            ->firstOrFail();

        $this->assertSame([
            'Sebelum lulus',
            'Sesudah lulus ... bulan',
            'Tidak mencari kerja',
        ], $question->opsi->pluck('label')->all());
        $this->assertTrue($question->opsi->every(fn ($option): bool => $option->nilai_skor === null));
    }

    /**
     * Memastikan opsi Evaluasi Atasan memiliki CRUD yang sama dengan instrumen lain
     * tanpa menerima field score/value dari client.
     */
    public function test_superadmin_can_manage_supervisor_question_options_without_score_value_contract(): void
    {
        $section = KuesionerSection::where('target', 'atasan')->where('order', 3)->firstOrFail();

        $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/pertanyaan', [
            'section_id' => $section->id,
            'kode' => 'P101_STATUS',
            'aspek' => 'Status kerja terbaru',
            'tipe' => 'pilihan_ganda',
            'order' => 27,
        ])->assertSessionHasNoErrors();

        $question = KuesionerPertanyaan::where('kode_pertanyaan', 'EVA_P101_STATUS')->firstOrFail();

        $createResponse = $this->actingAs($this->admin)->post('/superadmin/evaluasi-atasan/opsi', [
            'question_id' => $question->id,
            'code' => 'EVA_P101_STATUS-03',
            'option_text' => 'Lainnya',
            'order' => 3,
        ]);

        $createResponse->assertSessionHasNoErrors();
        $option = KuesionerOpsi::where('pertanyaan_id', $question->id)
            ->where('kode_opsi', 'EVA_P101_STATUS-03')
            ->firstOrFail();
        $this->assertNull($option->nilai_skor);

        $updateResponse = $this->actingAs($this->admin)->put("/superadmin/evaluasi-atasan/opsi/{$option->id}", [
            'code' => 'EVA_P101_STATUS-03',
            'option_text' => 'Status lainnya',
            'order' => 3,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kuesioner_opsi', [
            'id' => $option->id,
            'label' => 'Status lainnya',
            'nilai_skor' => null,
        ]);

        $deleteResponse = $this->actingAs($this->admin)->delete("/superadmin/evaluasi-atasan/opsi/{$option->id}");

        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('kuesioner_opsi', ['id' => $option->id]);
    }
}
