<?php

namespace Tests\Feature;

use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\KelompokPertanyaan;
use App\Models\Kuesioner;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\Prodi;
use App\Models\RefSubpertanyaan2021;
use App\Models\ResponEvaluasiAtasan;
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

    protected KelompokPertanyaan $section;

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
            'posisi_jabatan' => 'Software Engineer',
        ]);

        $this->kuesioner = Kuesioner::create([
            'title' => 'Tracer Study 2026',
            'year' => 2026,
            'is_active' => true,
        ]);

        $this->section = KelompokPertanyaan::create([
            'kuesioner_id' => $this->kuesioner->id,
            'title' => 'Kurikulum, Fasilitas & Nilai Kedutawacanaan',
            'order' => 9,
        ]);

        RefSubpertanyaan2021::create([
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'F24A',
            'subpertanyaan' => 'Sebutkan sumberdana dalam pembiayaan kuliah S1 di UKDW :',
            'type' => 'single_choice',
            'wajib' => true,
            'order' => 1,
        ]);

        RefSubpertanyaan2021::create([
            'kelompok_pertanyaan_id' => $this->section->id,
            'kode_pertanyaan' => 'F24B',
            'subpertanyaan' => 'Jika Anda melanjutkan ke jenjang pascasarjana (S2), sebutkan sumberdana dalam pembiayaan kuliah S2 Anda :',
            'type' => 'single_choice',
            'wajib' => true,
            'order' => 2,
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
        $question = PertanyaanEvaluasiAtasan::create([
            'kode' => 'integritas',
            'aspek' => 'Integritas',
            'kategori' => 'Kinerja',
            'tipe' => 'likert_5',
            'is_active' => true,
            'order' => 1,
        ]);
        $evaluasiAtasan = EvaluasiAtasan::create([
            'biodata_id' => $this->alumni->id,
            'token' => 'secret-evaluation-token',
            'is_submitted' => true,
        ]);
        ResponEvaluasiAtasan::create([
            'evaluasi_atasan_id' => $evaluasiAtasan->id,
            'pertanyaan_id' => $question->id,
            'nilai' => 'Sangat Tinggi',
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
}
