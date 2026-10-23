<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProdiKuesionerTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodiSI;

    protected Prodi $prodiTI;

    protected User $adminSI;

    protected User $adminTI;

    protected User $alumniUser;

    protected Biodata $alumni;

    protected ProdiQuestionSection $sectionSI;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodiSI = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi']);
        $this->prodiTI = Prodi::create(['kode_prodi' => '71', 'nama_prodi' => 'Informatika']);

        $this->adminSI = User::create([
            'username' => 'admin_si',
            'name' => 'Admin Prodi SI',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $this->prodiSI->id,
            'must_change_password' => false,
        ]);

        $this->adminTI = User::create([
            'username' => 'admin_ti',
            'name' => 'Admin Prodi TI',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $this->prodiTI->id,
            'must_change_password' => false,
        ]);

        $this->alumniUser = User::create([
            'username' => '72200001',
            'name' => 'Alumni SI',
            'password' => Hash::make('15082001'),
            'role' => 'alumni',
            'prodi_id' => $this->prodiSI->id,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200001',
            'nama' => 'Alumni SI',
            'email_pribadi' => 'alumni.si@gmail.com',
            'tempat_lahir' => 'Yogyakarta',
            'tanggal_lahir' => '2001-08-15',
            'agama' => 'Kristen',
            'jenis_kelamin' => 'Laki-laki',
            'nomor_telepon' => '081234567890',
            'alamat_saat_ini' => 'Jl. Dr. Wahidin',
            'ip_kumulatif' => 3.80,
            'tahun_akademik_lulus' => 'Genap 2024/2025',
        ]);

        $this->alumni = Biodata::create([
            'user_id' => $this->alumniUser->id,
            'nim' => '72200001',
            'nama' => 'Alumni SI',
            'prodi_id' => $this->prodiSI->id,
        ]);

        $this->sectionSI = ProdiQuestionSection::create([
            'prodi_id' => $this->prodiSI->id,
            'title' => 'Evaluasi Kurikulum Prodi SI',
            'description' => 'Umpan balik relevansi materi dan kompetensi lulusan',
            'order' => 1,
        ]);
    }

    public function test_admin_prodi_can_access_dashboard_and_pertanyaan(): void
    {
        $response = $this->actingAs($this->adminSI)->get('/prodi/dashboard');
        $response->assertStatus(200);

        $responsePertanyaan = $this->actingAs($this->adminSI)->get('/prodi/pertanyaan');
        $responsePertanyaan->assertStatus(200);

        $responseAlumni = $this->actingAs($this->adminSI)->get('/prodi/alumni');
        $responseAlumni->assertStatus(200);
    }

    public function test_admin_prodi_can_create_section_for_own_prodi(): void
    {
        $response = $this->actingAs($this->adminSI)->post('/prodi/sections', [
            'title' => 'Fasilitas & Lab Komputer SI',
            'description' => 'Evaluasi kelayakan fasilitas laboratorium',
            'order' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('prodi_question_section', [
            'title' => 'Fasilitas & Lab Komputer SI',
            'prodi_id' => $this->prodiSI->id,
        ]);
    }

    public function test_admin_prodi_can_create_question_and_options_for_own_prodi(): void
    {
        $response = $this->actingAs($this->adminSI)->post('/prodi/pertanyaan', [
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-01',
            'question_text' => 'Bagaimana kepuasan terhadap kurikulum SI?',
            'type' => 'single_choice',
            'is_required' => true,
            'options' => ['Sangat Puas', 'Cukup Puas', 'Kurang Puas'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('prodi_question', [
            'code' => 'PSI-01',
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
        ]);

        $this->assertDatabaseHas('prodi_question_option', [
            'option_text' => 'Sangat Puas',
        ]);
    }

    public function test_admin_prodi_cannot_modify_other_prodi_question(): void
    {
        $tiSection = ProdiQuestionSection::create([
            'prodi_id' => $this->prodiTI->id,
            'title' => 'Section TI',
            'order' => 1,
        ]);

        $tiQuestion = ProdiQuestion::create([
            'prodi_id' => $this->prodiTI->id,
            'prodi_question_section_id' => $tiSection->id,
            'code' => 'PTI-01',
            'question_text' => 'Pertanyaan Khusus TI',
            'type' => 'text',
            'is_required' => false,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->adminSI)->put("/prodi/pertanyaan/{$tiQuestion->id}", [
            'code' => 'PTI-01',
            'question_text' => 'Mencoba meretas pertanyaan TI',
            'type' => 'text',
            'is_required' => false,
        ]);

        $response->assertStatus(404);
    }

    public function test_alumni_can_view_and_submit_prodi_questionnaire(): void
    {
        $qSI = ProdiQuestion::create([
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-TEST',
            'question_text' => 'Relevansi materi SI',
            'type' => 'single_choice',
            'is_required' => true,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->alumniUser)->get('/alumni/kuesioner-prodi');
        $response->assertStatus(200);

        $submitResponse = $this->actingAs($this->alumniUser)->post('/alumni/kuesioner-prodi', [
            'answers' => [
                $qSI->id => 'Sangat Relevan',
            ],
        ]);

        $submitResponse->assertRedirect('/alumni/dashboard');
        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $this->alumni->id,
            'prodi_question_id' => $qSI->id,
            'answer_text' => 'Sangat Relevan',
        ]);
    }

    public function test_admin_prodi_can_view_sections_and_reorder(): void
    {
        $sec2 = ProdiQuestionSection::create([
            'prodi_id' => $this->prodiSI->id,
            'title' => 'Section 2 SI',
            'order' => 2,
        ]);

        $response = $this->actingAs($this->adminSI)->get('/prodi/sections');
        $response->assertStatus(200);

        $reorderResponse = $this->actingAs($this->adminSI)->post('/prodi/sections/reorder', [
            'id' => $sec2->id,
            'direction' => 'up',
        ]);

        $reorderResponse->assertRedirect();
        $this->assertEquals(1, $sec2->fresh()->order);
        $this->assertEquals(2, $this->sectionSI->fresh()->order);
    }

    public function test_admin_prodi_can_view_alumni_detail_with_both_questionnaires(): void
    {
        $response = $this->actingAs($this->adminSI)->get("/prodi/alumni/{$this->alumni->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('AdminProdi/Alumni/Show')
            ->has('alumni')
            ->has('evaluasi')
            ->has('univSections')
            ->has('prodiSections')
            ->has('prodiEvaluasi')
            ->has('formData')
        );
    }

    public function test_admin_prodi_cannot_view_other_prodi_alumni_detail(): void
    {
        // Admin TI cannot view alumni SI
        $response = $this->actingAs($this->adminTI)->get("/prodi/alumni/{$this->alumni->id}");
        $response->assertStatus(404);
    }

    public function test_superadmin_can_view_alumni_detail_with_prodi_questionnaire(): void
    {
        $superadmin = User::create([
            'username' => 'superadmin_test',
            'name' => 'Super Admin Test',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($superadmin)->get("/superadmin/alumni/{$this->alumni->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('SuperAdmin/Alumni/Show')
            ->has('prodiSections')
            ->has('prodiEvaluasi')
        );
    }

    public function test_academic_questions_are_automatically_synced_for_prodi_questionnaire(): void
    {
        $qNama = ProdiQuestion::create([
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-1-01',
            'question_text' => 'Nama',
            'type' => 'text',
            'is_required' => true,
            'order' => 1,
        ]);

        $qNim = ProdiQuestion::create([
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-1-02',
            'question_text' => 'NIM',
            'type' => 'text',
            'is_required' => true,
            'order' => 2,
        ]);

        // Access alumni detail from Admin Prodi, which triggers KuesionerSyncService
        $response = $this->actingAs($this->adminSI)->get("/prodi/alumni/{$this->alumni->id}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $this->alumni->id,
            'prodi_question_id' => $qNama->id,
            'answer_text' => $this->alumni->dataAkademik->nama,
        ]);

        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $this->alumni->id,
            'prodi_question_id' => $qNim->id,
            'answer_text' => $this->alumni->nim,
        ]);
    }

    public function test_emptying_prodi_question_deletes_response_and_updates_dashboard_percentage(): void
    {
        $q1 = ProdiQuestion::create([
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-6-01',
            'question_text' => 'Materi perkuliahan sesuai dengan minat',
            'type' => 'text',
            'is_required' => true,
            'order' => 1,
        ]);

        $q2 = ProdiQuestion::create([
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-6-02',
            'question_text' => 'Mata kuliah pilihan fleksibel',
            'type' => 'text',
            'is_required' => true,
            'order' => 2,
        ]);

        // Simpan 2 jawaban
        $this->actingAs($this->alumniUser)->post('/alumni/kuesioner-prodi', [
            'answers' => [
                $q1->id => 'Sangat setuju',
                $q2->id => 'Setuju',
            ],
            'stay_on_page' => true,
        ]);

        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $this->alumni->id,
            'prodi_question_id' => $q1->id,
            'answer_text' => 'Sangat setuju',
        ]);

        // Sekarang kosongkan q2
        $this->actingAs($this->alumniUser)->post('/alumni/kuesioner-prodi', [
            'answers' => [
                $q1->id => 'Sangat setuju',
                $q2->id => '',
            ],
            'stay_on_page' => true,
        ]);

        $this->assertDatabaseMissing('prodi_response', [
            'biodata_id' => $this->alumni->id,
            'prodi_question_id' => $q2->id,
        ]);

        // Periksa data di Dashboard Alumni
        $dashResponse = $this->actingAs($this->alumniUser)->get('/alumni/dashboard');
        $dashResponse->assertStatus(200);
        $dashResponse->assertInertia(fn ($page) => $page
            ->component('Alumni/Dashboard')
            ->where('prodiAnsweredCount', 1)
            ->where('prodiQuestionsCount', 2)
            ->where('prodiCompleted', false)
        );
    }

    public function test_creating_question_automatically_generates_default_options(): void
    {
        // 1. Tipe radio_text (Radio + Teks) -> otomatis dibuat 2 pilihan + 1 Lainnya
        $response1 = $this->actingAs($this->adminSI)->post('/prodi/pertanyaan', [
            'code' => 'PSI-TEST-RT',
            'question_text' => 'Bentuk kontribusi yang ingin diberikan?',
            'type' => 'radio_text',
            'prodi_question_section_id' => $this->sectionSI->id,
            'is_required' => true,
        ]);
        $response1->assertRedirect();

        $qRadioText = ProdiQuestion::where('code', 'PSI-TEST-RT')->first();
        $this->assertNotNull($qRadioText);
        $this->assertCount(3, $qRadioText->options);
        $this->assertEquals('Pilihan 1', $qRadioText->options[0]->option_text);
        $this->assertEquals('Pilihan 2', $qRadioText->options[1]->option_text);
        $this->assertEquals('Lainnya (Tuliskan)', $qRadioText->options[2]->option_text);

        // 2. Tipe rating_5 (Skala Rating 1-5) -> otomatis dibuat 5 opsi skala penilaian
        $response2 = $this->actingAs($this->adminSI)->post('/prodi/pertanyaan', [
            'code' => 'PSI-TEST-R5',
            'question_text' => 'Bagaimana kepuasan Anda terhadap fasilitas laboratorium?',
            'type' => 'rating_5',
            'prodi_question_section_id' => $this->sectionSI->id,
            'is_required' => true,
        ]);
        $response2->assertRedirect();

        $qRating = ProdiQuestion::where('code', 'PSI-TEST-R5')->first();
        $this->assertNotNull($qRating);
        $this->assertCount(5, $qRating->options);
        $this->assertEquals('1 - Sangat Rendah', $qRating->options[0]->option_text);
        $this->assertEquals('5 - Sangat Tinggi', $qRating->options[4]->option_text);

        // 3. Tipe radio_input (Radio + Angka) -> otomatis dibuat opsi angka dan opsi 'tidak'
        $response3 = $this->actingAs($this->adminSI)->post('/prodi/pertanyaan', [
            'code' => 'PSI-TEST-RI',
            'question_text' => 'Berapa bulan Anda menyelesaikan tugas akhir?',
            'type' => 'radio_input',
            'prodi_question_section_id' => $this->sectionSI->id,
            'is_required' => true,
        ]);
        $response3->assertRedirect();

        $qRadioInput = ProdiQuestion::where('code', 'PSI-TEST-RI')->first();
        $this->assertNotNull($qRadioInput);
        $this->assertCount(3, $qRadioInput->options);
        $this->assertStringContainsString('Bulan', $qRadioInput->options[0]->option_text);
        $this->assertStringContainsString('Tidak', $qRadioInput->options[2]->option_text);
    }

    public function test_alumni_can_submit_radio_text_and_radio_input_in_prodi_questionnaire(): void
    {
        $qRadioText = ProdiQuestion::create([
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-SUB-RT',
            'question_text' => 'Kontribusi alumni?',
            'type' => 'radio_text',
            'is_required' => true,
        ]);

        $qRadioInput = ProdiQuestion::create([
            'prodi_id' => $this->prodiSI->id,
            'prodi_question_section_id' => $this->sectionSI->id,
            'code' => 'PSI-SUB-RI',
            'question_text' => 'Waktu pengerjaan skripsi?',
            'type' => 'radio_input',
            'is_required' => true,
        ]);

        $response = $this->actingAs($this->alumniUser)->post('/alumni/kuesioner-prodi', [
            'answers' => [
                $qRadioText->id => [
                    'selected' => 'Lainnya (Tuliskan)',
                    'input' => 'Menjadi pembicara seminar',
                ],
                $qRadioInput->id => [
                    'selected' => 'Bulan sebelum lulus',
                    'input' => '6',
                ],
            ],
            'stay_on_page' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $this->alumni->id,
            'prodi_question_id' => $qRadioText->id,
            'answer_text' => 'Lainnya (Tuliskan): Menjadi pembicara seminar',
        ]);

        $this->assertDatabaseHas('prodi_response', [
            'biodata_id' => $this->alumni->id,
            'prodi_question_id' => $qRadioInput->id,
            'answer_text' => 'Bulan sebelum lulus: 6',
        ]);
    }
}
