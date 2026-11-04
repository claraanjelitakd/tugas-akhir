<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Prodi;
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

    protected KuesionerSection $sectionSI;

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
            'tanggal_lahir' => '2001-08-15',
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

        $this->sectionSI = KuesionerSection::create([
            'target' => 'prodi',
            'prodi_id' => $this->prodiSI->id,
            'kode_section' => 'SEC-PSI-1',
            'judul' => 'Evaluasi Kurikulum Prodi SI',
            'deskripsi' => 'Umpan balik relevansi materi dan kompetensi lulusan',
            'order' => 1,
            'is_active' => true,
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
        $this->assertDatabaseHas('kuesioner_section', [
            'target' => 'prodi',
            'judul' => 'Fasilitas & Lab Komputer SI',
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
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'kode_pertanyaan' => 'PSI-01',
            'pemilik_prodi_id' => $this->prodiSI->id,
        ]);

        $this->assertDatabaseHas('kuesioner_opsi', [
            'label' => 'Sangat Puas',
        ]);
    }

    public function test_admin_prodi_cannot_modify_other_prodi_question(): void
    {
        $tiSection = KuesionerSection::create([
            'target' => 'prodi',
            'prodi_id' => $this->prodiTI->id,
            'kode_section' => 'SEC-PTI-1',
            'judul' => 'Section TI',
            'order' => 1,
            'is_active' => true,
        ]);

        $tiQuestion = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiTI->id,
            'kode_pertanyaan' => 'PTI-01',
            'teks_pertanyaan' => 'Pertanyaan Khusus TI',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $tiSection->id,
            'pertanyaan_id' => $tiQuestion->id,
            'order' => 1,
            'wajib' => false,
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
        $qSI = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiSI->id,
            'kode_pertanyaan' => 'PSI-TEST',
            'teks_pertanyaan' => 'Relevansi materi SI',
            'tipe' => 'single_choice',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionSI->id,
            'pertanyaan_id' => $qSI->id,
            'order' => 1,
            'wajib' => true,
        ]);

        $response = $this->actingAs($this->alumniUser)->get('/alumni/kuesioner-prodi');
        $response->assertStatus(200);

        $submitResponse = $this->actingAs($this->alumniUser)->post('/alumni/kuesioner-prodi', [
            'answers' => [
                $qSI->id => 'Sangat Relevan',
            ],
        ]);

        $submitResponse->assertRedirect('/alumni/dashboard');
        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $qSI->id,
            'jawaban_teks' => 'Sangat Relevan',
        ]);
    }

    public function test_admin_prodi_can_view_sections_and_reorder(): void
    {
        $sec2 = KuesionerSection::create([
            'target' => 'prodi',
            'prodi_id' => $this->prodiSI->id,
            'kode_section' => 'SEC-PSI-2',
            'judul' => 'Section 2 SI',
            'order' => 2,
            'is_active' => true,
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
        $qNama = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiSI->id,
            'kode_pertanyaan' => 'PSI-1-01',
            'teks_pertanyaan' => 'Nama',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        $qNim = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiSI->id,
            'kode_pertanyaan' => 'PSI-1-02',
            'teks_pertanyaan' => 'NIM',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionSI->id,
            'pertanyaan_id' => $qNama->id,
            'order' => 1,
            'wajib' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionSI->id,
            'pertanyaan_id' => $qNim->id,
            'order' => 2,
            'wajib' => true,
        ]);

        // Access alumni detail from Admin Prodi, which triggers KuesionerSyncService
        $response = $this->actingAs($this->adminSI)->get("/prodi/alumni/{$this->alumni->id}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $qNama->id,
            'jawaban_teks' => $this->alumni->dataAkademik->nama,
        ]);

        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $qNim->id,
            'jawaban_teks' => $this->alumni->nim,
        ]);
    }

    public function test_emptying_prodi_question_deletes_response_and_updates_dashboard_percentage(): void
    {
        $q1 = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiSI->id,
            'kode_pertanyaan' => 'PSI-6-01',
            'teks_pertanyaan' => 'Materi perkuliahan sesuai dengan minat',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        $q2 = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiSI->id,
            'kode_pertanyaan' => 'PSI-6-02',
            'teks_pertanyaan' => 'Mata kuliah pilihan fleksibel',
            'tipe' => 'text',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionSI->id,
            'pertanyaan_id' => $q1->id,
            'order' => 1,
            'wajib' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionSI->id,
            'pertanyaan_id' => $q2->id,
            'order' => 2,
            'wajib' => true,
        ]);

        // Simpan 2 jawaban
        $this->actingAs($this->alumniUser)->post('/alumni/kuesioner-prodi', [
            'answers' => [
                $q1->id => 'Sangat setuju',
                $q2->id => 'Setuju',
            ],
            'stay_on_page' => true,
        ]);

        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $q1->id,
            'jawaban_teks' => 'Sangat setuju',
        ]);

        // Sekarang kosongkan q2
        $this->actingAs($this->alumniUser)->post('/alumni/kuesioner-prodi', [
            'answers' => [
                $q1->id => 'Sangat setuju',
                $q2->id => '',
            ],
            'stay_on_page' => true,
        ]);

        $this->assertDatabaseMissing('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $q2->id,
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

        $qRadioText = KuesionerPertanyaan::where('kode_pertanyaan', 'PSI-TEST-RT')->first();
        $this->assertNotNull($qRadioText);
        $this->assertCount(3, $qRadioText->opsi);
        $this->assertEquals('Pilihan 1', $qRadioText->opsi[0]->label);
        $this->assertEquals('Pilihan 2', $qRadioText->opsi[1]->label);
        $this->assertEquals('Lainnya (Tuliskan)', $qRadioText->opsi[2]->label);

        // 2. Tipe rating_5 (Skala Rating 1-5) -> otomatis dibuat 5 opsi skala penilaian
        $response2 = $this->actingAs($this->adminSI)->post('/prodi/pertanyaan', [
            'code' => 'PSI-TEST-R5',
            'question_text' => 'Bagaimana kepuasan Anda terhadap fasilitas laboratorium?',
            'type' => 'rating_5',
            'prodi_question_section_id' => $this->sectionSI->id,
            'is_required' => true,
        ]);
        $response2->assertRedirect();

        $qRating = KuesionerPertanyaan::where('kode_pertanyaan', 'PSI-TEST-R5')->first();
        $this->assertNotNull($qRating);
        $this->assertCount(5, $qRating->opsi);
        $this->assertEquals('1 - Sangat Rendah', $qRating->opsi[0]->label);
        $this->assertEquals('5 - Sangat Tinggi', $qRating->opsi[4]->label);

        // 3. Tipe radio_input (Radio + Angka) -> otomatis dibuat opsi angka dan opsi 'tidak'
        $response3 = $this->actingAs($this->adminSI)->post('/prodi/pertanyaan', [
            'code' => 'PSI-TEST-RI',
            'question_text' => 'Berapa bulan Anda menyelesaikan tugas akhir?',
            'type' => 'radio_input',
            'prodi_question_section_id' => $this->sectionSI->id,
            'is_required' => true,
        ]);
        $response3->assertRedirect();

        $qRadioInput = KuesionerPertanyaan::where('kode_pertanyaan', 'PSI-TEST-RI')->first();
        $this->assertNotNull($qRadioInput);
        $this->assertCount(3, $qRadioInput->opsi);
        $this->assertStringContainsString('Bulan', $qRadioInput->opsi[0]->label);
        $this->assertStringContainsString('Tidak', $qRadioInput->opsi[2]->label);
    }

    public function test_alumni_can_submit_radio_text_and_radio_input_in_prodi_questionnaire(): void
    {
        $qRadioText = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiSI->id,
            'kode_pertanyaan' => 'PSI-SUB-RT',
            'teks_pertanyaan' => 'Kontribusi alumni?',
            'tipe' => 'radio_text',
            'is_active' => true,
        ]);

        $qRadioInput = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $this->prodiSI->id,
            'kode_pertanyaan' => 'PSI-SUB-RI',
            'teks_pertanyaan' => 'Waktu pengerjaan skripsi?',
            'tipe' => 'radio_input',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionSI->id,
            'pertanyaan_id' => $qRadioText->id,
            'order' => 1,
            'wajib' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionSI->id,
            'pertanyaan_id' => $qRadioInput->id,
            'order' => 2,
            'wajib' => true,
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

        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $qRadioText->id,
            'jawaban_teks' => 'Lainnya (Tuliskan): Menjadi pembicara seminar',
        ]);

        $this->assertDatabaseHas('kuesioner_jawaban', [
            'biodata_id' => $this->alumni->id,
            'pertanyaan_id' => $qRadioInput->id,
            'jawaban_teks' => 'Bulan sebelum lulus: 6',
        ]);
    }
}
