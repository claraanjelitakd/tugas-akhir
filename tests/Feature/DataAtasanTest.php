<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji halaman master Data Atasan beserta agregasi sumber data dan scope admin.
 */
class DataAtasanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tamu diarahkan ke halaman login saat membuka master Data Atasan.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('superadmin.atasan.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Superadmin menerima atasan, perusahaan, alumni, sumber data, dan status evaluasi.
     */
    public function test_superadmin_receives_atasan_directory_with_relational_data(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => 'SI',
            'nama_prodi' => 'Sistem Informasi',
            'jenjang' => 'S1',
        ]);
        $company = Perusahaan::create(['nama_perusahaan' => 'PT Data Nusantara']);
        $alumniUser = $this->createUser('alumni-data', 'alumni-data@example.test', 'alumni');
        $alumni = $this->createAlumni($alumniUser, $prodi, '72123456', 'Budi Data');
        $secondAlumni = $this->createAlumni(
            $this->createUser('alumni-data-2', 'alumni-data-2@example.test', 'alumni'),
            $prodi,
            '72123457',
            'Sari Data',
        );
        $atasan = Atasan::create([
            'nama' => 'Rina Atasan',
            'email' => 'rina@example.test',
            'telepon' => '08123456789',
            'perusahaan_id' => $company->id,
        ]);
        $alumni->update(['atasan_id' => $atasan->id, 'perusahaan_id' => $company->id]);
        $secondAlumni->update(['atasan_id' => $atasan->id, 'perusahaan_id' => $company->id]);
        EvaluasiAtasan::create([
            'biodata_id' => $alumni->id,
            'perusahaan_id' => $company->id,
            'atasan_id' => $atasan->id,
            'token' => 'token-data-atasan',
            'is_submitted' => true,
        ]);
        $admin = $this->createUser('superadmin-data', 'superadmin-data@example.test', 'superadmin');

        $response = $this->actingAs($admin)->get(route('superadmin.atasan.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Shared/DataAtasan/Index')
            ->has('atasans.data', 2)
            ->where('atasans.data.0.atasan.nama', 'Rina Atasan')
            ->where('atasans.data.0.perusahaan.nama', 'PT Data Nusantara')
            ->where('atasans.data.0.alumni.nama', 'Budi Data')
            ->where('atasans.data.0.diisi_oleh', 'Alumni')
            ->where('atasans.data.0.sumber_data', 'Profil alumni')
            ->where('atasans.data.0.evaluasi.status', 'Selesai')
            ->where('atasans.data.1.alumni.nama', 'Sari Data')
            ->where('stats.total_data_atasan', 2)
            ->where('stats.alumni_terhubung', 2)
            ->where('stats.atasan_unik', 1)
            ->where('stats.evaluasi_selesai', 1)
        );
    }

    /**
     * Admin prodi hanya menerima atasan yang terhubung dengan alumninya sendiri.
     */
    public function test_admin_prodi_only_receives_atasan_from_its_program_study(): void
    {
        $prodiA = Prodi::create(['kode_prodi' => 'SI', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1']);
        $prodiB = Prodi::create(['kode_prodi' => 'TI', 'nama_prodi' => 'Teknik Informatika', 'jenjang' => 'S1']);
        $company = Perusahaan::create(['nama_perusahaan' => 'PT Scope Nusantara']);
        $atasanA = Atasan::create(['nama' => 'Atasan Prodi A', 'perusahaan_id' => $company->id]);
        $atasanB = Atasan::create(['nama' => 'Atasan Prodi B', 'perusahaan_id' => $company->id]);
        $alumniA = $this->createAlumni($this->createUser('alumni-scope-a', 'scope-a@example.test', 'alumni'), $prodiA, '72123457', 'Alumni Scope A');
        $alumniB = $this->createAlumni($this->createUser('alumni-scope-b', 'scope-b@example.test', 'alumni'), $prodiB, '72123458', 'Alumni Scope B');
        $alumniA->update(['atasan_id' => $atasanA->id]);
        $alumniB->update(['atasan_id' => $atasanB->id]);
        $admin = $this->createUser('admin-prodi-scope', 'admin-prodi-scope@example.test', 'admin_prodi', ['kode_prodi' => $prodiA->kode_prodi]);

        $response = $this->actingAs($admin)->get(route('prodi.atasan.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Shared/DataAtasan/Index')
            ->has('atasans.data', 1)
            ->where('atasans.data.0.atasan.nama', 'Atasan Prodi A')
            ->where('scopeLabel', 'Sistem Informasi')
        );
    }

    /**
     * Superadmin dapat menambahkan dan memperbarui relasi atasan dari direktori master.
     */
    public function test_superadmin_can_create_and_update_atasan_from_directory(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => 'SI',
            'nama_prodi' => 'Sistem Informasi',
            'jenjang' => 'S1',
        ]);
        $company = Perusahaan::create([
            'nama_perusahaan' => 'PT Atasan Nusantara',
            'status_verifikasi' => 'Terverifikasi',
        ]);
        $alumni = $this->createAlumni(
            $this->createUser('alumni-atasan-create', 'alumni-atasan-create@example.test', 'alumni'),
            $prodi,
            '72123459',
            'Alumni Atasan Create',
        );
        $admin = $this->createUser('superadmin-atasan-create', 'superadmin-atasan-create@example.test', 'superadmin');

        $response = $this->actingAs($admin)->post(route('superadmin.atasan.store'), [
            'biodata_id' => $alumni->id,
            'perusahaan_id' => $company->id,
            'nama' => 'Rudi Atasan',
            'email' => 'rudi@example.test',
            'telepon' => '08123456789',
        ]);

        $response->assertRedirect();
        $atasan = Atasan::query()->where('nama', 'Rudi Atasan')->firstOrFail();
        $this->assertSame($atasan->id, $alumni->fresh()->atasan_id);
        $this->assertSame($company->id, $atasan->perusahaan_id);

        $updateResponse = $this->actingAs($admin)->put(route('superadmin.atasan.update', $atasan->id), [
            'perusahaan_id' => $company->id,
            'nama' => 'Rudi Atasan Updated',
            'email' => 'rudi.updated@example.test',
            'telepon' => '08987654321',
        ]);

        $updateResponse->assertRedirect();
        $atasan->refresh();
        $this->assertSame('Rudi Atasan Updated', $atasan->nama);
        $this->assertSame('rudi.updated@example.test', $atasan->email);
        $this->assertSame('08987654321', $atasan->telepon);
    }

    /**
     * Pengguna admin dapat memfilter direktori atasan berdasarkan nama perusahaan dan provinsi domisili alumni.
     */
    public function test_admin_can_filter_atasan_by_company_and_domisili_province(): void
    {
        $prodi = Prodi::create(['kode_prodi' => 'INF', 'nama_prodi' => 'Informatika', 'jenjang' => 'S1']);
        $companyA = Perusahaan::create(['nama_perusahaan' => 'PT Alpha Solusi']);
        $companyB = Perusahaan::create(['nama_perusahaan' => 'PT Beta Mandiri']);

        $alumniA = $this->createAlumni($this->createUser('alumni-filter-a', 'a@example.test', 'alumni'), $prodi, '71123451', 'Alumni Alpha');
        $alumniB = $this->createAlumni($this->createUser('alumni-filter-b', 'b@example.test', 'alumni'), $prodi, '71123452', 'Alumni Beta');

        $atasanA = Atasan::create(['nama' => 'Atasan Alpha', 'perusahaan_id' => $companyA->id]);
        $atasanB = Atasan::create(['nama' => 'Atasan Beta', 'perusahaan_id' => $companyB->id]);

        $provinsiYogya = Propinsi::create(['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta']);
        $provinsiJakarta = Propinsi::create(['kode_provinsi' => '31', 'nama_provinsi' => 'DKI Jakarta']);

        $alumniA->update(['atasan_id' => $atasanA->id, 'kode_provinsi' => $provinsiYogya->kode_provinsi]);
        $alumniB->update(['atasan_id' => $atasanB->id, 'kode_provinsi' => $provinsiJakarta->kode_provinsi]);

        $admin = $this->createUser('superadmin-filter', 'filter@example.test', 'superadmin');

        // Filter nama perusahaan
        $responseCompany = $this->actingAs($admin)->get(route('superadmin.atasan.index', ['company' => 'Alpha']));
        $responseCompany->assertInertia(fn ($page) => $page
            ->has('atasans.data', 1)
            ->where('atasans.data.0.atasan.nama', 'Atasan Alpha')
        );

        // Filter domisili provinsi
        $responseProv = $this->actingAs($admin)->get(route('superadmin.atasan.index', ['provinsi' => '31']));
        $responseProv->assertInertia(fn ($page) => $page
            ->has('atasans.data', 1)
            ->where('atasans.data.0.atasan.nama', 'Atasan Beta')
        );
    }

    /**
     * Admin dapat mendaftarkan perusahaan baru berstatus Menunggu Verifikasi melalui modal atasan.
     */
    public function test_admin_can_create_pending_perusahaan_from_atasan_modal(): void
    {
        $admin = $this->createUser('superadmin-new-corp', 'corp@example.test', 'superadmin');

        $response = $this->actingAs($admin)->postJson(route('superadmin.atasan.perusahaan.store'), [
            'nama_perusahaan' => 'PT Startup Inovasi',
            'jenis_lokasi' => 'Dalam Negeri',
            'alamat' => 'Jl. Gejayan No. 12',
            'skala' => 'Nasional',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('perusahaan.nama_perusahaan', 'PT Startup Inovasi')
            ->assertJsonPath('perusahaan.status_verifikasi', 'Menunggu Verifikasi');

        $this->assertDatabaseHas('perusahaan', [
            'nama_perusahaan' => 'PT Startup Inovasi',
            'status_verifikasi' => 'Menunggu Verifikasi',
        ]);
    }

    /**
     * Memastikan Superadmin dapat mengunduh berkas Excel rekapitulasi data atasan.
     */
    public function test_superadmin_can_export_atasan_excel(): void
    {
        $admin = $this->createUser('superadmin-export', 'superadmin-export@example.test', 'superadmin');
        $response = $this->actingAs($admin)->get(route('superadmin.atasan.export-excel'));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Rekap_Evaluasi_Atasan_', (string) $response->headers->get('Content-Disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('REKAPITULASI HASIL EVALUASI PENGGUNA LULUSAN', $content);
        $this->assertStringContainsString('Universitas Kristen Duta Wacana', $content);
    }

    /**
     * Buat akun dengan atribut minimal yang sesuai schema users aktif.
     *
     * @param  string  $username  Username unik akun.
     * @param  string  $email  Email akun.
     * @param  string  $role  Peran aplikasi.
     * @param  array<string, mixed>  $attributes  Atribut tambahan akun.
     * @return User Akun yang tersimpan.
     */
    private function createUser(string $username, string $email, string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $username,
            'username' => $username,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'must_change_password' => false,
        ], $attributes));
    }

    /**
     * Buat data akademik dan profil alumni untuk pengujian relasi atasan.
     *
     * @param  User  $user  Akun alumni.
     * @param  Prodi  $prodi  Program studi alumni.
     * @param  string  $nim  Nomor induk mahasiswa.
     * @param  string  $name  Nama alumni pada data akademik.
     * @return Biodata Profil alumni yang tersimpan.
     */
    private function createAlumni(User $user, Prodi $prodi, string $nim, string $name): Biodata
    {
        DataAkademik::create([
            'nim' => $nim,
            'nama' => $name,
            'kode_prodi' => $prodi->kode_prodi,
            'status_mahasiswa' => 'Lulus',
            'tahun_lulus' => 2025,
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'kode_prodi' => $prodi->kode_prodi,
        ]);
    }
}
