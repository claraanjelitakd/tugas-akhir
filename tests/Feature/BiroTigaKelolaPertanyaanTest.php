<?php

namespace Tests\Feature;

use App\Models\Kuesioner;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BiroTigaKelolaPertanyaanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected KuesionerSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'superadmin',
            'name' => 'Super Administrator UKDW',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        Kuesioner::create([
            'title' => 'Tracer Study',
            'year' => 2026,
            'is_active' => true,
        ]);

        $this->section = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_1',
            'target' => 'univ',
            'judul' => 'Bagian I',
            'order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Memverifikasi halaman kelola pertanyaan memuat data pertanyaan & jump targets
     */
    public function test_can_render_kelola_pertanyaan_page(): void
    {
        $q = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F1',
            'teks_pertanyaan' => 'Nomor Induk Mahasiswa',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $q->id,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get('/superadmin/pertanyaan');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('SuperAdmin/Pertanyaan/Index')
            ->has('subpertanyaans')
            ->has('availableJumpTargets')
            ->has('targetQuestionMap')
        );
    }

    /**
     * Memverifikasi fitur pemindahan urutan pertanyaan (reorder) ala GForm
     */
    public function test_can_reorder_questions_direction(): void
    {
        $q1 = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F1',
            'teks_pertanyaan' => 'Pertanyaan 1',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $q1->id,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $q2 = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F2',
            'teks_pertanyaan' => 'Pertanyaan 2',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $q2->id,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 2,
        ]);

        // Pindahkan Q2 ke atas
        $response = $this->actingAs($this->admin)->post('/superadmin/pertanyaan/reorder', [
            'id' => $q2->id,
            'direction' => 'up',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, KuesionerSectionPertanyaan::where('pertanyaan_id', $q2->id)->first()->order);
        $this->assertEquals(2, KuesionerSectionPertanyaan::where('pertanyaan_id', $q1->id)->first()->order);
    }

    /**
     * Memverifikasi penambahan opsi tanpa input manual urutan
     */
    public function test_can_store_option_without_manual_order(): void
    {
        $q = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F3',
            'teks_pertanyaan' => 'Kapan mulai mencari kerja?',
            'tipe' => 'single_choice',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $q->id,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post("/superadmin/pertanyaan/{$q->id}/options", [
            'option_text' => 'Sebelum lulus',
            'jump_to' => null,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kuesioner_opsi', [
            'pertanyaan_id' => $q->id,
            'label' => 'Sebelum lulus',
            'order' => 1,
        ]);
    }

    /**
     * Memverifikasi pembuatan pertanyaan bertipe rating_5 secara otomatis mengenerate opsi default 1-5.
     */
    public function test_storing_rating_question_generates_default_options(): void
    {
        $response = $this->actingAs($this->admin)->post('/superadmin/pertanyaan', [
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'F99',
            'subpertanyaan' => 'Penilaian Kualitas Fasilitas Belajar',
            'type' => 'rating_5',
            'wajib' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $q = KuesionerPertanyaan::where('kode_pertanyaan', 'F99')->first();
        $this->assertNotNull($q);
        $this->assertCount(5, $q->opsi);
    }
}
