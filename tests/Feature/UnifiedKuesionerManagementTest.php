<?php

namespace Tests\Feature;

use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengujian Fitur Manajemen Kuesioner Terpadu (Unified Survey Engine):
 * - Toggle aktif/nonaktif butir pertanyaan
 * - Anti-duplikasi Bank Pertanyaan & Multi-Placement
 * - CRUD Opsi Jawaban Terpadu
 */
class UnifiedKuesionerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private Prodi $prodiA;

    private Prodi $prodiB;

    private KuesionerSection $sectionProdiA;

    private KuesionerSection $sectionProdiB;

    protected function setUp(): void
    {
        parent::setUp();

        $fakultas = RefFakultas::create([
            'kode_fakultas' => 'FTI',
            'nama_fakultas' => 'Fakultas Teknologi Informasi',
        ]);

        $this->prodiA = Prodi::create([
            'kode_prodi' => 'INF',
            'nama_prodi' => 'Informatika',
            'fakultas_id' => $fakultas->id,
        ]);

        $this->prodiB = Prodi::create([
            'kode_prodi' => 'SI',
            'nama_prodi' => 'Sistem Informasi',
            'fakultas_id' => $fakultas->id,
        ]);

        $this->superadmin = User::create([
            'username' => 'superadmin_test',
            'name' => 'Super Administrator Test',
            'password' => Hash::make('secret123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->sectionProdiA = KuesionerSection::create([
            'target' => 'prodi',
            'prodi_id' => $this->prodiA->id,
            'kode_section' => 'SEC_INF_1',
            'judul' => 'Kompetensi Lulusan Informatika',
            'order' => 1,
            'is_active' => true,
        ]);

        $this->sectionProdiB = KuesionerSection::create([
            'target' => 'prodi',
            'prodi_id' => $this->prodiB->id,
            'kode_section' => 'SEC_SI_1',
            'judul' => 'Kompetensi Lulusan Sistem Informasi',
            'order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Test Toggle Status Aktif / Non-aktif Butir Pertanyaan Secara Realtime.
     */
    public function test_toggle_status_aktif_pertanyaan(): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'U_TEST_01',
            'teks_pertanyaan' => 'Apakah kurikulum mempersiapkan Anda masuk industri?',
            'tipe' => 'single_choice',
            'pemilik_prodi_id' => $this->prodiA->id,
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionProdiA->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => true,
        ]);

        // Toggle pertama: aktif -> nonaktif
        $response1 = $this->actingAs($this->superadmin)
            ->post(route('superadmin.prodi-kuesioner.pertanyaan.toggle-active', $question->id));

        $response1->assertRedirect();
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'id' => $question->id,
            'is_active' => false,
        ]);

        // Toggle kedua: nonaktif -> aktif kembali
        $response2 = $this->actingAs($this->superadmin)
            ->post(route('superadmin.prodi-kuesioner.pertanyaan.toggle-active', $question->id));

        $response2->assertRedirect();
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'id' => $question->id,
            'is_active' => true,
        ]);
    }

    /**
     * Memastikan Superadmin tetap dapat mengubah pertanyaan lintas prodi yang sedang dikelola.
     */
    public function test_superadmin_can_edit_borrowed_question(): void
    {
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BORROWED_01',
            'teks_pertanyaan' => 'Pertanyaan awal dari Prodi B',
            'tipe' => 'text',
            'pemilik_prodi_id' => $this->prodiB->id,
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $this->sectionProdiA->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => true,
        ]);

        $this->actingAs($this->superadmin)
            ->put(route('superadmin.prodi-kuesioner.pertanyaan.update', $question->id), [
                'prodi_id' => $this->prodiA->id,
                'code' => 'BORROWED_01_EDITED',
                'question_text' => 'Pertanyaan diperbarui oleh Superadmin',
                'type' => 'text',
                'is_required' => true,
                'is_reusable' => true,
                'prodi_question_section_id' => $this->sectionProdiA->id,
                'order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'id' => $question->id,
            'kode_pertanyaan' => 'BORROWED_01_EDITED',
            'teks_pertanyaan' => 'Pertanyaan diperbarui oleh Superadmin',
        ]);
    }

    /**
     * Test Tambah Pertanyaan dengan Multi-Placement & Dynamic Options (Anti-Double).
     */
    public function test_tambah_pertanyaan_multi_placement_dan_opsi(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.prodi-kuesioner.pertanyaan.store'), [
                'prodi_id' => $this->prodiA->id,
                'prodi_question_section_id' => $this->sectionProdiA->id,
                'target_section_ids' => [$this->sectionProdiB->id], // Multi-placement di Prodi B
                'code' => 'KOMP_CLOUD',
                'question_text' => 'Tingkat penguasaan teknologi Cloud Computing:',
                'type' => 'single_choice',
                'is_required' => true,
                'is_reusable' => true,
                'order' => 1,
                'options' => [
                    ['label' => 'Pemula', 'score' => 1],
                    ['label' => 'Menengah', 'score' => 2],
                    ['label' => 'Mahir', 'score' => 3],
                ],
            ]);

        $response->assertRedirect();

        // Verifikasi pertanyaan master tersimpan
        $master = KuesionerPertanyaan::where('kode_pertanyaan', 'KOMP_CLOUD')->first();
        $this->assertNotNull($master);
        $this->assertEquals('Tingkat penguasaan teknologi Cloud Computing:', $master->teks_pertanyaan);

        // Verifikasi penempatan pertanyaan di kedua section (Prodi A dan Prodi B)
        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'section_id' => $this->sectionProdiA->id,
            'pertanyaan_id' => $master->id,
        ]);
        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'section_id' => $this->sectionProdiB->id,
            'pertanyaan_id' => $master->id,
        ]);

        // Verifikasi opsi jawaban tersimpan di kuesioner_opsi
        $this->assertDatabaseHas('kuesioner_opsi', [
            'pertanyaan_id' => $master->id,
            'label' => 'Pemula',
            'nilai_skor' => 1,
        ]);
        $this->assertDatabaseHas('kuesioner_opsi', [
            'pertanyaan_id' => $master->id,
            'label' => 'Mahir',
            'nilai_skor' => 3,
        ]);
    }

    /**
     * Test Sematkan Pertanyaan dari Bank Soal (Attach from Bank).
     */
    public function test_sematkan_pertanyaan_dari_bank_soal(): void
    {
        $bankQuestion = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BANK_ETIKA',
            'teks_pertanyaan' => 'Bagaimana penerapan etika profesi di tempat kerja Anda?',
            'tipe' => 'single_choice',
            'pemilik_prodi_id' => null, // Umum / Universitas
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerOpsi::create([
            'pertanyaan_id' => $bankQuestion->id,
            'label' => 'Sangat Baik',
            'nilai_skor' => 4,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.prodi-kuesioner.bank.attach'), [
                'prodi_id' => $this->prodiA->id,
                'section_id' => $this->sectionProdiA->id,
                'pertanyaan_ids' => [$bankQuestion->id],
            ]);

        $response->assertRedirect();

        // Pastikan penempatan terbentuk tanpa membuat master pertanyaan baru (No Double)
        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'section_id' => $this->sectionProdiA->id,
            'pertanyaan_id' => $bankQuestion->id,
        ]);
        $this->assertEquals(1, KuesionerPertanyaan::where('kode_pertanyaan', 'BANK_ETIKA')->count());
    }

    /**
     * Memastikan halaman superadmin.pertanyaan.index berhasil dibuka tanpa error 500.
     */
    public function test_superadmin_can_open_pertanyaan_index_page(): void
    {
        $univSection = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_01',
            'target' => 'univ',
            'judul' => 'Bagian 1: Identitas Alumni',
            'order' => 1,
            'is_active' => true,
        ]);

        $q = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'U_01',
            'teks_pertanyaan' => 'Apakah Anda sudah bekerja?',
            'tipe_pertanyaan' => 'radio',
            'is_wajib' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $univSection->id,
            'pertanyaan_id' => $q->id,
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.pertanyaan.index'));

        $response->assertStatus(200);
    }

    /**
     * Memastikan universitas dapat mengambil/menyematkan pertanyaan dari Bank Soal tanpa duplikasi master.
     */
    public function test_superadmin_can_attach_question_from_bank_to_university_section(): void
    {
        $univSection = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_02',
            'target' => 'univ',
            'judul' => 'Bagian 2: Pekerjaan',
            'order' => 2,
            'is_active' => true,
        ]);

        $bankQuestion = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'BANK_KOMPETENSI',
            'teks_pertanyaan' => 'Bagaimana kompetensi kepemimpinan Anda?',
            'tipe' => 'single_choice',
            'is_wajib' => true,
            'is_reusable' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.pertanyaan.bank.attach'), [
                'section_id' => $univSection->id,
                'pertanyaan_ids' => [$bankQuestion->id],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'section_id' => $univSection->id,
            'pertanyaan_id' => $bankQuestion->id,
        ]);
        $this->assertEquals(1, KuesionerPertanyaan::where('kode_pertanyaan', 'BANK_KOMPETENSI')->count());
    }

    /**
     * Memastikan halaman superadmin.evaluasi-atasan.index terbuka 200 OK tanpa error missing legacy table.
     */
    public function test_superadmin_can_open_evaluasi_atasan_index_page(): void
    {
        $atasanSection = KuesionerSection::create([
            'kode_section' => 'SEC_EVA_PENILAIAN',
            'target' => 'atasan',
            'judul' => 'Penilaian Kinerja Alumni',
            'order' => 1,
            'is_active' => true,
        ]);

        $q = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'EVA_ETIKA',
            'teks_pertanyaan' => 'Etika dan moral kerja',
            'tipe' => 'likert_5',
            'is_wajib' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $atasanSection->id,
            'pertanyaan_id' => $q->id,
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.evaluasi-atasan.index'));

        $response->assertStatus(200);
    }
}
