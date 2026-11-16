<?php

namespace Tests\Feature;

use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\Kabupaten;
use App\Models\Kuesioner;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminDaftarAlumniTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $alumniUser;

    protected Biodata $alumni;

    protected Prodi $prodi;

    protected Kuesioner $kuesioner;

    protected KuesionerSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_test',
            'name' => 'Super Administrator Test',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        Propinsi::create([
            'kode_provinsi' => '34',
            'nama_provinsi' => 'DI Yogyakarta',
        ]);
        Kabupaten::create([
            'kode_kabupaten' => '3404',
            'kode_provinsi' => '34',
            'nama_kabupaten' => 'Sleman',
        ]);

        $this->alumniUser = User::create([
            'username' => '72200001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200001',
            'nama' => 'Budi Santoso',
            'angkatan_masuk' => '2020',
            'tahun_akademik_lulus' => 'Gasal 2024/2025',
            'tahun_lulus' => '2024',
            'ip_kumulatif' => '3.85',
        ]);

        $this->alumni = Biodata::create([
            'user_id' => $this->alumniUser->id,
            'nim' => '72200001',
            'nama' => 'Budi Santoso',
            'prodi_id' => $this->prodi->id,
            'kode_provinsi' => '34',
            'kode_kabupaten' => '3404',
            'posisi_jabatan' => 'Software Engineer',
        ]);

        $this->kuesioner = Kuesioner::create([
            'title' => 'Tracer Study 2026',
            'year' => 2026,
            'is_active' => true,
        ]);

        $this->section = KuesionerSection::create([
            'kode_section' => 'SEC_UNIV_9',
            'target' => 'univ',
            'judul' => 'Kurikulum, Fasilitas & Nilai Kedutawacanaan',
            'order' => 9,
            'is_active' => true,
        ]);

        $q1 = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F24A',
            'teks_pertanyaan' => 'Sebutkan sumberdana dalam pembiayaan kuliah S1 di UKDW :',
            'tipe' => 'single_choice',
            'is_active' => true,
        ]);
        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $q1->id,
            'order' => 1,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ]);

        $q2 = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'F24B',
            'teks_pertanyaan' => 'Jika Anda melanjutkan ke jenjang pascasarjana (S2), sebutkan sumberdana dalam pembiayaan kuliah S2 Anda :',
            'tipe' => 'single_choice',
            'is_active' => true,
        ]);
        KuesionerSectionPertanyaan::create([
            'section_id' => $this->section->id,
            'pertanyaan_id' => $q2->id,
            'order' => 2,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ]);
    }

    /**
     * Test superadmin dapat membuka halaman direktori alumni.
     */
    public function test_superadmin_can_view_alumni_index_page(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', ['per_page' => 5]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Index')
            ->has('alumnis', 1)
            ->where('pagination.per_page', 5)
            ->where('pagination.current_page', 1)
            ->where('pagination.total', 1)
            ->has('daftarTahun')
            ->has('prodis')
            ->has('filters')
            ->has('stats')
        );
    }

    /** Memastikan direktori mengirim nama prodi, domisili, karier, divisi, dan skills terstruktur. */
    public function test_alumni_directory_exposes_canonical_career_fields(): void
    {
        $this->alumni->update([
            'foto' => '/uploads/profile/budi.jpg',
            'alamat_saat_ini' => 'Jalan Kaliurang',
            'bidang_pekerjaan' => 'Teknologi Informasi',
            'divisi' => 'Product Development',
            'skills' => 'Project Management, Python; Project Management | SQL',
            'experience' => 'Software Engineer di PT Teknologi Unggul',
            'linkedin_url' => 'https://www.linkedin.com/in/budi-santoso',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', ['tahun' => '2024']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('alumnis.0.nama_prodi', 'Sistem Informasi')
            ->where('alumnis.0.prodi', 'Sistem Informasi')
            ->where('alumnis.0.foto', '/uploads/profile/budi.jpg')
            ->where('alumnis.0.bidang_pekerjaan', 'Teknologi Informasi')
            ->where('alumnis.0.divisi', 'Product Development')
            ->where('alumnis.0.domisili', 'Sleman, DI Yogyakarta')
            ->where('alumnis.0.experience', 'Software Engineer di PT Teknologi Unggul')
            ->where('alumnis.0.linkedin_url', 'https://www.linkedin.com/in/budi-santoso')
            ->where('alumnis.0.skills_list', ['Project Management', 'Python', 'SQL'])
            ->has('filterOptions.wilayah.provinsi')
            ->has('filterOptions.wilayah.kabupaten')
            ->has('filterOptions.head_office')
            ->has('filterOptions.skills')
            ->has('filterOptions.bidang')
            ->has('filterOptions.unit')
            ->where('filters.kode_provinsi', 'all')
            ->where('filters.kode_kabupaten', 'all')
        );

        $filteredResponse = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', [
                'tahun' => '2024',
                'bidang' => 'Teknologi Informasi',
                'unit' => 'Product Development',
                'skills' => 'Python',
                'kode_provinsi' => '34',
                'kode_kabupaten' => '3404',
            ]));

        $filteredResponse->assertInertia(fn (Assert $page) => $page
            ->has('alumnis', 1)
            ->where('filters.bidang', 'Teknologi Informasi')
            ->where('filters.unit', 'Product Development')
            ->where('filters.skills', 'Python')
            ->where('filters.kode_provinsi', '34')
            ->where('filters.kode_kabupaten', '3404')
        );
    }

    /**
     * Test filter tahun dan semester kelulusan.
     */
    public function test_superadmin_can_filter_alumni_by_year_and_semester(): void
    {
        // Filter tahun yang cocok
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', ['tahun' => '2024/2025', 'semester' => 'Gasal']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Index')
            ->has('alumnis', 1)
        );

        // Filter tahun yang tidak cocok
        $responseMismatch = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', ['tahun' => '2019/2020']));

        $responseMismatch->assertStatus(200);
        $responseMismatch->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Index')
            ->has('alumnis', 0)
        );
    }

    /**
     * Test filter target kelulusan pada direktori alumni.
     */
    public function test_superadmin_can_filter_alumni_by_target(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', ['target' => 'Gasal 2024/2025|2024']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Index')
            ->has('daftarTarget')
            ->where('filters.target', 'Gasal 2024/2025|2024')
        );
    }

    /**
     * Test superadmin dapat melihat halaman detail mahasiswa dengan F24.
     */
    public function test_superadmin_can_view_alumni_detail_page_with_questionnaire(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.show', $this->alumni->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Show')
            ->has('alumni')
            ->has('evaluasi')
            ->has('sections')
            ->where('sections.0.subpertanyaans.0.kode_pertanyaan', 'F24A')
            ->where('sections.0.subpertanyaans.0.is_mandatory', true)
            ->where('sections.0.subpertanyaans.1.kode_pertanyaan', 'F24B')
            ->where('sections.0.subpertanyaans.1.is_mandatory', true)
        );
    }

    /**
     * Test pengguna tanpa role superadmin diblokir.
     */
    public function test_non_superadmin_cannot_access_alumni_pages(): void
    {
        $response = $this->actingAs($this->alumniUser)
            ->get(route('superadmin.alumni.index'));

        $response->assertStatus(403);
    }

    /**
     * Test superadmin dapat memperbarui data profil alumni.
     */
    public function test_superadmin_can_update_alumni_profile(): void
    {
        $payload = [
            'nama' => 'Budi Santoso Diperbarui',
            'nomor_telepon' => '081234567890',
            'posisi_jabatan' => 'Lead Engineer',
            'nama_perusahaan' => 'PT Teknologi Unggul',
            'company_alamat' => 'Jl. Solo KM 10',
            'company_skala' => 'Nasional',
            'nama_atasan' => 'Pak Direktur',
            'email_atasan' => 'direktur@example.com',
            'telepon_atasan' => '081111222333',
        ];

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.alumni.profile.update', $this->alumni->id), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('biodata', [
            'id' => $this->alumni->id,
            'nomor_telepon' => '081234567890',
            'posisi_jabatan' => 'Lead Engineer',
        ]);
        // Data akademik bersifat default / terkunci (tidak dapat diedit dari form profil oleh role manapun)
        $this->assertDatabaseHas('data_akademik', [
            'nim' => $this->alumni->nim,
            'nama' => 'Budi Santoso',
        ]);
        $this->assertDatabaseHas('perusahaan', [
            'nama_perusahaan' => 'PT Teknologi Unggul',
            'alamat' => 'Jl. Solo KM 10',
        ]);
    }

    /**
     * Test superadmin dapat mengunduh file Excel rekap seluruh butir pertanyaan & jawaban per alumni.
     */
    public function test_superadmin_can_export_alumni_answers_excel(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.export-excel', $this->alumni->id));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Kode', $content);
        $this->assertStringContainsString('Respon / Jawaban Alumni', $content);
        $this->assertStringContainsString('Hasil Evaluasi Atasan', $content);
    }

    /**
     * Test superadmin dapat mengunduh arsip ZIP berisi Excel per alumni untuk program studi dan tahun tertentu.
     */
    public function test_superadmin_can_export_zip_per_prodi_and_tahun(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.export-zip', [
                'prodi_id' => $this->prodi->id,
                'tahun' => '2024',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
    }

    /**
     * Test detail alumni memuat data evaluasi atasan dan kuesionernya.
     */
    public function test_superadmin_detail_alumni_includes_evaluasi_atasan(): void
    {
        $section = KuesionerSection::create([
            'kode_section' => 'SEC_ATASAN_1',
            'target' => 'atasan',
            'judul' => 'Evaluasi Pengguna Lulusan',
            'order' => 1,
            'is_active' => true,
        ]);

        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'integritas',
            'teks_pertanyaan' => 'Integritas',
            'tipe' => 'rating_5',
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $section->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => true,
            'tampil_di' => 'kuesioner',
        ]);

        $evaluasiAtasan = EvaluasiAtasan::create([
            'biodata_id' => $this->alumni->id,
            'token' => 'secret-evaluation-token',
            'is_submitted' => true,
        ]);

        KuesionerJawaban::create([
            'responden_type' => 'atasan',
            'biodata_id' => $this->alumni->id,
            'evaluasi_atasan_id' => $evaluasiAtasan->id,
            'section_id' => $section->id,
            'pertanyaan_id' => $question->id,
            'kode_pertanyaan' => 'integritas',
            'jawaban_teks' => 'Sangat Tinggi',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.show', $this->alumni->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Show')
            ->has('evaluasiAtasan')
            ->has('pertanyaanEvaluasiAtasan')
            ->where('evaluasiAtasan.is_submitted', true)
            ->where('evaluasiAtasan.status', 'Selesai')
            ->where('evaluasiAtasan.respons.0.nilai', 'Sangat Tinggi')
            ->missing('evaluasiAtasan.token')
        );
    }

    /**
     * Test superadmin dapat mengirimkan email pengingat kuesioner ke alumni.
     */
    public function test_superadmin_can_send_reminder_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.alumni.send-email', $this->alumni->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(PengingatKuesionerAlumniMail::class);
    }

    /**
     * Test opsi filter skills dan perusahaan menyesuaikan tahun kelulusan aktif
     * serta otomatis mereset parameter yang tidak relevan dengan tahun tersebut.
     */
    public function test_filter_options_cascade_by_selected_year_and_resets_invalid_skills(): void
    {
        // 1. Tambah data alumni kedua dengan tahun kelulusan berbeda (2022)
        $user2022 = User::create([
            'username' => '72180099',
            'name' => 'Alumni Angkatan 2018',
            'email' => 'alumni2018@example.com',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72180099',
            'nama' => 'Alumni Angkatan 2018',
            'angkatan_masuk' => '2018',
            'tahun_akademik_lulus' => 'Genap 2021/2022',
            'tahun_lulus' => 2022,
            'ip_kumulatif' => '3.50',
        ]);

        Biodata::create([
            'user_id' => $user2022->id,
            'nim' => '72180099',
            'nama' => 'Alumni Angkatan 2018',
            'prodi_id' => $this->prodi->id,
            'kode_provinsi' => '34',
            'kode_kabupaten' => '3404',
            'skills' => 'Big Data Infrastructure, Hadoop',
        ]);

        // Perbarui alumni Budi Santoso (lulus 2024) dengan skill khusus
        $this->alumni->update([
            'skills' => 'Vue.js, Tailwind CSS',
        ]);

        // 2. Akses direktori dengan Tahun 2024
        $response2024 = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', ['tahun' => '2024']));

        $response2024->assertStatus(200);
        $response2024->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Index')
            ->where('filters.tahun', '2024')
            ->where('filterOptions.skills', fn ($skills) => collect($skills)->contains('Vue.js') && ! collect($skills)->contains('Big Data Infrastructure'))
        );

        // 3. Akses direktori tahun 2024 namun menyertakan filter skill tahun 2022 yang tidak relevan
        $responseStale = $this->actingAs($this->superadmin)
            ->get(route('superadmin.alumni.index', [
                'tahun' => '2024',
                'skills' => 'Big Data Infrastructure',
            ]));

        $responseStale->assertStatus(200);
        $responseStale->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Alumni/Index')
            ->where('filters.tahun', '2024')
            // Otomatis di-reset ke 'all' agar tabel tidak menghasilkan 0 data
            ->where('filters.skills', 'all')
            ->has('alumnis', 1)
        );
    }
}
