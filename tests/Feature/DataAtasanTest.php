<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\Perusahaan;
use App\Models\Prodi;
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
        $admin = $this->createUser('admin-prodi-scope', 'admin-prodi-scope@example.test', 'admin_prodi', ['prodi_id' => $prodiA->id]);

        $response = $this->actingAs($admin)->get(route('prodi.atasan.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Shared/DataAtasan/Index')
            ->has('atasans.data', 1)
            ->where('atasans.data.0.atasan.nama', 'Atasan Prodi A')
            ->where('scopeLabel', 'Sistem Informasi')
        );
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
            'prodi_id' => $prodi->id,
            'status_mahasiswa' => 'Lulus',
            'tahun_lulus' => 2025,
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'prodi_id' => $prodi->id,
        ]);
    }
}
