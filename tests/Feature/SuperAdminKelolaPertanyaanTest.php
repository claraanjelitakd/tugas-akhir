<?php

namespace Tests\Feature;

use App\Models\Kuesioner;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminKelolaPertanyaanTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected Kuesioner $kuesioner;

    protected KuesionerSection $section;

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

        $this->section = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_1',
            'target' => 'univ',
            'judul' => 'Bagian Identitas & Pekerjaan',
            'order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Test superadmin dapat melihat halaman daftar pertanyaan.
     */
    public function test_superadmin_can_view_questions_page(): void
    {
        $q = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F301_TEST',
            'teks_pertanyaan' => 'Kapan Anda mulai mencari pekerjaan?',
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
                'section_id' => $this->section->id,
                'kode_pertanyaan' => 'F302_TEST',
                'label_pertanyaan' => 'Jumlah lamaran kerja',
                'subpertanyaan' => 'Berapa banyak perusahaan yang Anda lamar?',
                'type' => 'number',
                'wajib' => true,
                'tampil_di' => 'kuesioner',
                'order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'kode_pertanyaan' => 'F302_TEST',
            'label_pertanyaan' => 'Jumlah lamaran kerja',
            'teks_pertanyaan' => 'Berapa banyak perusahaan yang Anda lamar?',
            'keterangan' => 'Isi jawaban dalam angka.',
        ]);

        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'section_id' => $this->section->id,
            'tampil_di' => 'kuesioner',
        ]);
    }

    /**
     * Test superadmin dapat memperbarui lokasi tampil pertanyaan (kuesioner -> profile).
     */
    public function test_superadmin_can_update_question_target(): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F303_TEST',
            'teks_pertanyaan' => 'Pertanyaan Awal',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $question->id,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.pertanyaan.update', $question->id), [
                'section_id' => $this->section->id,
                'kode_pertanyaan' => 'F303_TEST',
                'subpertanyaan' => 'Pertanyaan Diperbarui',
                'type' => 'text',
                'keterangan' => 'Isi jawaban singkat.',
                'wajib' => true,
                'tampil_di' => 'profile',
                'order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'id' => $question->id,
            'teks_pertanyaan' => 'Pertanyaan Diperbarui',
            'keterangan' => 'Isi jawaban singkat.',
        ]);

        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'pertanyaan_id' => $question->id,
            'tampil_di' => 'profile',
        ]);
    }

    /**
     * Test status aktif master dapat diubah tanpa menghapus relasi section.
     */
    public function test_superadmin_can_toggle_question_active_without_removing_placement(): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F_ACTIVE_TEST',
            'teks_pertanyaan' => 'Pertanyaan status aktif',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $question->id,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->patch(route('superadmin.pertanyaan.toggle-active', $question->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'id' => $question->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'pertanyaan_id' => $question->id,
            'section_id' => $this->section->id,
        ]);
    }

    /**
     * Test superadmin dapat mengubah urutan pertanyaan naik dan turun.
     */
    public function test_superadmin_can_reorder_questions(): void
    {
        $q1 = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'Q1_TEST',
            'teks_pertanyaan' => 'Soal 1',
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
            'kode_pertanyaan' => 'Q2_TEST',
            'teks_pertanyaan' => 'Soal 2',
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

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.pertanyaan.reorder'), [
                'id' => $q2->id,
                'direction' => 'up',
            ]);

        $response->assertRedirect();
        $this->assertEquals(1, KuesionerSectionPertanyaan::where('pertanyaan_id', $q2->id)->first()->order);
        $this->assertEquals(2, KuesionerSectionPertanyaan::where('pertanyaan_id', $q1->id)->first()->order);
    }

    /**
     * Test superadmin saat membuat pertanyaan baru langsung mendapatkan opsi default.
     */
    public function test_superadmin_creating_question_automatically_generates_default_options(): void
    {
        // 1. Tipe radio_text -> 2 opsi master; Lainnya virtual di form alumni
        $this->actingAs($this->superadmin)->post(route('superadmin.pertanyaan.store'), [
            'section_id' => $this->section->id,
            'kode_pertanyaan' => 'F_TEST_RT',
            'subpertanyaan' => 'Pertanyaan Radio Teks Univ',
            'type' => 'radio_text',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ])->assertRedirect();

        $q = KuesionerPertanyaan::where('kode_pertanyaan', 'F_TEST_RT')->first();
        $this->assertNotNull($q);
        $this->assertCount(2, $q->opsi);
        $this->assertEquals('Pilihan 1', $q->opsi[0]->label);
        $this->assertEquals('Pilihan 2', $q->opsi[1]->label);

        // 2. Tipe rating_5 -> 5 opsi
        $this->actingAs($this->superadmin)->post(route('superadmin.pertanyaan.store'), [
            'section_id' => $this->section->id,
            'kode_pertanyaan' => 'F_TEST_R5',
            'subpertanyaan' => 'Pertanyaan Rating Univ',
            'type' => 'rating_5',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ])->assertRedirect();

        $q2 = KuesionerPertanyaan::where('kode_pertanyaan', 'F_TEST_R5')->first();
        $this->assertNotNull($q2);
        $this->assertCount(5, $q2->opsi);
        $this->assertEquals('1 - Sangat Rendah', $q2->opsi[0]->label);
        $this->assertEquals('5 - Sangat Tinggi', $q2->opsi[4]->label);
        $this->assertEquals('F_TEST_R5-01', $q2->opsi[0]->kode_opsi);

        // 3. Tipe radio_input -> opsi bulan singkat tanpa skor.
        $this->actingAs($this->superadmin)->post(route('superadmin.pertanyaan.store'), [
            'section_id' => $this->section->id,
            'kode_pertanyaan' => 'F_TEST_RI',
            'subpertanyaan' => 'Kapan mulai mencari pekerjaan?',
            'type' => 'radio_input',
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ])->assertRedirect();

        $q3 = KuesionerPertanyaan::with('opsi')->where('kode_pertanyaan', 'F_TEST_RI')->first();
        $this->assertNotNull($q3);
        $this->assertSame([
            'Sebelum lulus',
            'Sesudah lulus ... bulan',
            'Tidak mencari kerja',
        ], $q3->opsi->pluck('label')->all());
        $this->assertTrue($q3->opsi->every(fn ($option): bool => $option->nilai_skor === null));
    }

    /**
     * Memastikan opsi pertanyaan universitas dapat dibuat bersama Jump To sejak create.
     */
    public function test_superadmin_can_create_question_with_option_jump_to(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('superadmin.pertanyaan.store'), [
                'section_id' => $this->section->id,
                'kode_pertanyaan' => 'F_TEST_CREATE_JUMP',
                'label_pertanyaan' => 'Status pekerjaan ringkas',
                'subpertanyaan' => 'Apa status pekerjaan Anda?',
                'type' => 'radio',
                'wajib' => true,
                'tampil_di' => 'kuesioner',
                'options' => [
                    ['code' => 'F_TEST_CREATE_JUMP-01', 'label' => 'Bekerja', 'jump_to' => 'F_TEST_TARGET'],
                    ['code' => 'F_TEST_CREATE_JUMP-02', 'label' => 'Belum bekerja', 'jump_to' => null],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'kode_pertanyaan' => 'F_TEST_CREATE_JUMP',
            'label_pertanyaan' => 'Status pekerjaan ringkas',
        ]);
        $this->assertDatabaseHas('kuesioner_opsi', [
            'kode_opsi' => 'F_TEST_CREATE_JUMP-01',
            'label' => 'Bekerja',
            'lompat_ke_pertanyaan_id' => 'F_TEST_TARGET',
        ]);
    }

    /**
     * Memastikan CRUD opsi universitas memakai kontrak code, label, urutan, dan jump_to tanpa score/value.
     */
    public function test_superadmin_can_manage_university_question_options_without_score_value_contract(): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F_TEST_OPTION',
            'teks_pertanyaan' => 'Pilihan status pekerjaan',
            'tipe' => 'single_choice',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $question->id,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
            'order' => 1,
        ]);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.pertanyaan.options.store', ['questionId' => $question->id]), [
                'code' => 'F_TEST_OPTION-01',
                'option_text' => 'Bekerja',
                'order' => 1,
            ])
            ->assertSessionHasNoErrors();

        $option = KuesionerOpsi::where('pertanyaan_id', $question->id)->firstOrFail();
        $this->assertSame('F_TEST_OPTION-01', $option->kode_opsi);
        $this->assertNull($option->nilai_skor);

        $this->actingAs($this->superadmin)
            ->put(route('superadmin.pertanyaan.options.update', ['optionId' => $option->id]), [
                'code' => 'F_TEST_OPTION-01',
                'option_text' => 'Bekerja penuh waktu',
                'order' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kuesioner_opsi', [
            'id' => $option->id,
            'label' => 'Bekerja penuh waktu',
            'nilai_skor' => null,
        ]);

        $this->actingAs($this->superadmin)
            ->delete(route('superadmin.pertanyaan.options.destroy', ['optionId' => $option->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('kuesioner_opsi', ['id' => $option->id]);
    }

    /**
     * Test superadmin dapat mengubah visibilitas tampilnya butir pertanyaan di profil alumni.
     */
    public function test_superadmin_can_toggle_question_visibility_for_profile(): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F_TEST_TOGGLE',
            'teks_pertanyaan' => 'Pertanyaan Toggle Visibilitas',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $question->id,
            'wajib' => true,
            'tampil_di' => 'profil',
            'order' => 1,
        ]);

        // Toggle pertama: dari profil -> hidden (sembunyikan)
        $response = $this->actingAs($this->superadmin)
            ->patch(route('superadmin.pertanyaan.toggle-visibility', $question->id), [
                'target' => 'profil',
            ]);

        $response->assertRedirect();
        $this->assertEquals('hidden', KuesionerSectionPertanyaan::where('pertanyaan_id', $question->id)->first()->tampil_di);

        // Toggle kedua: dari hidden -> profil (tampilkan kembali)
        $response2 = $this->actingAs($this->superadmin)
            ->patch(route('superadmin.pertanyaan.toggle-visibility', $question->id), [
                'target' => 'profil',
            ]);

        $response2->assertRedirect();
        $this->assertEquals('profil', KuesionerSectionPertanyaan::where('pertanyaan_id', $question->id)->first()->tampil_di);
    }
}
