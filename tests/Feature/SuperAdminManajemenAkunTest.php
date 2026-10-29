<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pengujian Fitur Manajemen Akun dan Sinkronisasi Akun Alumni (Super Admin)
 */
class SuperAdminManajemenAkunTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_test',
            'name' => 'Super Administrator Test',
            'email' => 'superadmin@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
    }

    /**
     * Memastikan halaman manajemen akun dapat dimuat dengan props stats dan unsynced count.
     */
    public function test_superadmin_can_view_manajemen_akun_page_with_stats(): void
    {
        DataAkademik::create([
            'nim' => '72230001',
            'nama' => 'Mahasiswa Belum Sinkron',
            'status_mahasiswa' => 'Lulus',
            'tahun_lulus' => 2026,
            'tanggal_lahir' => '2002-05-10',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get('/superadmin/manajemen-akun');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/ManajemenAkun/Index')
            ->has('stats')
            ->where('stats.unsynced_alumni_count', 1)
        );
    }

    /**
     * Memastikan proses sinkronisasi akun alumni berhasil membuat User dan Biodata.
     */
    public function test_superadmin_can_sync_unsynced_alumni_accounts(): void
    {
        DataAkademik::create([
            'nim' => '72230002',
            'nama' => 'Clara Anjelita',
            'status_mahasiswa' => 'Lulus',
            'tahun_lulus' => 2026,
            'tanggal_lahir' => '2001-08-15',
            'email_students' => '72230002@students.ukdw.ac.id',
        ]);

        $this->assertDatabaseMissing('users', ['username' => '72230002']);
        $this->assertDatabaseMissing('biodata', ['nim' => '72230002']);

        $response = $this->actingAs($this->superadmin)
            ->post('/superadmin/manajemen-akun/sync-alumni');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => '72230002',
            'name' => 'Clara Anjelita',
            'role' => 'alumni',
            'must_change_password' => true,
            'prodi_id' => $this->prodi->id,
        ]);

        $this->assertDatabaseHas('biodata', [
            'nim' => '72230002',
            'prodi_id' => $this->prodi->id,
        ]);

        $user = User::where('username', '72230002')->first();
        $this->assertTrue(Hash::check('15082001', $user->password));
    }

    /**
     * Memastikan jika tidak ada data yang perlu disinkronkan, sistem menampilkan pesan info.
     */
    public function test_sync_returns_info_when_no_records_need_syncing(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post('/superadmin/manajemen-akun/sync-alumni');

        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    /**
     * Memastikan Super Admin dapat mengirim tautan reset ke akun admin.
     */
    public function test_superadmin_can_send_password_reset_link_to_admin_account(): void
    {
        $admin = User::create([
            'username' => 'admin_prodi_reset',
            'name' => 'Admin Prodi Reset',
            'email' => 'admin.prodi.reset@ukdw.ac.id',
            'password' => Hash::make('password-lama'),
            'role' => 'admin_prodi',
            'must_change_password' => false,
        ]);

        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.manajemen-akun.password-reset', $admin->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($admin): bool {
            return $mail->hasTo($admin->email) && $mail->user->is($admin);
        });
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $admin->email,
        ]);
    }

    /**
     * Memastikan akun tanpa email valid ditolak tanpa membuat token reset.
     */
    public function test_password_reset_link_is_not_sent_when_admin_email_is_invalid(): void
    {
        $admin = User::create([
            'username' => 'admin_prodi_invalid_email',
            'name' => 'Admin Prodi Invalid Email',
            'email' => 'alamat-tidak-valid',
            'password' => Hash::make('password-lama'),
            'role' => 'admin_prodi',
            'must_change_password' => false,
        ]);

        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.manajemen-akun.password-reset', $admin->id));

        $response->assertRedirect();
        $response->assertSessionHasErrors('reset');
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $admin->email,
        ]);
    }

    /**
     * Memastikan sinkronisasi hanya memproses NIM mahasiswa yang dicentang (selected_nims).
     */
    public function test_superadmin_can_sync_specific_selected_alumni_accounts(): void
    {
        DataAkademik::create([
            'nim' => '72230010',
            'nama' => 'Mahasiswa Terpilih',
            'status_mahasiswa' => 'Lulus',
            'tahun_lulus' => 2026,
            'tanggal_lahir' => '2001-01-01',
        ]);

        DataAkademik::create([
            'nim' => '72230011',
            'nama' => 'Mahasiswa Tidak Terpilih',
            'status_mahasiswa' => 'Lulus',
            'tahun_lulus' => 2026,
            'tanggal_lahir' => '2001-02-02',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/superadmin/manajemen-akun/sync-alumni', [
                'selected_nims' => ['72230010'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 72230010 dibuatkan akun
        $this->assertDatabaseHas('users', ['username' => '72230010']);
        $this->assertDatabaseHas('biodata', ['nim' => '72230010']);

        // 72230011 TIDAK dibuatkan akun karena tidak dicentang
        $this->assertDatabaseMissing('users', ['username' => '72230011']);
        $this->assertDatabaseMissing('biodata', ['nim' => '72230011']);
    }
}
