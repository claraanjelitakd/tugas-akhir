<?php

namespace Tests\Feature;

use App\Models\KelompokPertanyaan;
use App\Models\Kuesioner;
use App\Models\RefSubpertanyaan2021;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminKelolaPertanyaanTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected Kuesioner $kuesioner;

    protected KelompokPertanyaan $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_pertanyaan_test',
            'name' => 'Super Administrator Test',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->kuesioner = Kuesioner::create([
            'title' => 'Tracer Study 2026',
            'year' => 2026,
            'is_active' => true,
        ]);

        $this->section = KelompokPertanyaan::create([
            'kuesioner_id' => $this->kuesioner->id,
            'title' => 'Bagian Identitas & Pekerjaan',
            'order' => 1,
        ]);
    }

    /**
     * Test superadmin dapat melihat halaman daftar pertanyaan.
     */
    public function test_superadmin_can_view_questions_page(): void
    {
        RefSubpertanyaan2021::create([
            'kelompok' => 'F1',
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'F301_TEST',
            'subpertanyaan' => 'Kapan Anda mulai mencari pekerjaan?',
            'type' => 'single_choice',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.pertanyaan.index'));

        $response->assertStatus(200);
    }

    /**
     * Test superadmin dapat menambahkan butir pertanyaan baru dengan konfigurasi tampil_di.
     */
    public function test_superadmin_can_create_new_question_with_tampil_di(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.pertanyaan.store'), [
                'kelompok_pertanyaan_id' => $this->section->id,
                'kode_pertanyaan' => 'F302_TEST',
                'subpertanyaan' => 'Berapa banyak perusahaan yang Anda lamar?',
                'type' => 'number',
                'wajib' => true,
                'tampil_di' => 'kuesioner',
                'order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ref_subpertanyaan2021', [
            'kode_pertanyaan' => 'F302_TEST',
            'subpertanyaan' => 'Berapa banyak perusahaan yang Anda lamar?',
            'tampil_di' => 'kuesioner',
            'keterangan' => 'Isi jawaban dalam angka.',
        ]);
    }

    /**
     * Test superadmin dapat memperbarui lokasi tampil pertanyaan (kuesioner -> profile).
     */
    public function test_superadmin_can_update_question_target(): void
    {
        $question = RefSubpertanyaan2021::create([
            'kelompok' => 'F1',
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'F303_TEST',
            'subpertanyaan' => 'Pertanyaan Awal',
            'type' => 'text',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.pertanyaan.update', $question->id), [
                'kelompok_pertanyaan_id' => $this->section->id,
                'kode_pertanyaan' => 'F303_TEST',
                'subpertanyaan' => 'Pertanyaan Diperbarui',
                'type' => 'text',
                'wajib' => true,
                'tampil_di' => 'profile',
                'order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ref_subpertanyaan2021', [
            'id' => $question->id,
            'tampil_di' => 'profile',
            'subpertanyaan' => 'Pertanyaan Diperbarui',
            'keterangan' => 'Isi jawaban singkat.',
        ]);
    }

    /**
     * Test superadmin dapat mengubah urutan pertanyaan naik dan turun.
     */
    public function test_superadmin_can_reorder_questions(): void
    {
        $q1 = RefSubpertanyaan2021::create([
            'kelompok' => 'F1',
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'Q1_TEST',
            'subpertanyaan' => 'Soal 1',
            'type' => 'text',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $q2 = RefSubpertanyaan2021::create([
            'kelompok' => 'F1',
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'Q2_TEST',
            'subpertanyaan' => 'Soal 2',
            'type' => 'text',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 2,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.pertanyaan.reorder'), [
                'id' => $q2->id,
                'direction' => 'up',
            ]);

        $response->assertRedirect();
        $this->assertEquals(1, $q2->fresh()->order);
        $this->assertEquals(2, $q1->fresh()->order);
    }

    /**
     * Test superadmin saat membuat pertanyaan baru langsung mendapatkan opsi default.
     */
    public function test_superadmin_creating_question_automatically_generates_default_options(): void
    {
        // 1. Tipe radio_text -> 3 opsi
        $this->actingAs($this->superadmin)->post(route('superadmin.pertanyaan.store'), [
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'F_TEST_RT',
            'subpertanyaan' => 'Pertanyaan Radio Teks Univ',
            'type' => 'radio_text',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ])->assertRedirect();

        $q = RefSubpertanyaan2021::where('kode_pertanyaan', 'F_TEST_RT')->first();
        $this->assertNotNull($q);
        $this->assertCount(3, $q->detils);
        $this->assertEquals('Pilihan 1', $q->detils[0]->option_text);
        $this->assertEquals('Pilihan 2', $q->detils[1]->option_text);
        $this->assertEquals('Lainnya (Tuliskan)', $q->detils[2]->option_text);

        // 2. Tipe rating_5 -> 5 opsi
        $this->actingAs($this->superadmin)->post(route('superadmin.pertanyaan.store'), [
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'F_TEST_R5',
            'subpertanyaan' => 'Pertanyaan Rating Univ',
            'type' => 'rating_5',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ])->assertRedirect();

        $q2 = RefSubpertanyaan2021::where('kode_pertanyaan', 'F_TEST_R5')->first();
        $this->assertNotNull($q2);
        $this->assertCount(5, $q2->detils);
        $this->assertEquals('1 - Sangat Rendah', $q2->detils[0]->option_text);
        $this->assertEquals('5 - Sangat Tinggi', $q2->detils[4]->option_text);
    }
}
