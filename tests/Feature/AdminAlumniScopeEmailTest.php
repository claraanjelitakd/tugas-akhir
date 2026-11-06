<?php

namespace Tests\Feature;

use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Memverifikasi pembatasan scope pengiriman email pengingat alumni.
 */
class AdminAlumniScopeEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admin Prodi tanpa relasi Prodi tidak boleh mengirim email.
     */
    public function test_admin_prodi_without_prodi_scope_receives_forbidden(): void
    {
        Mail::fake();

        $admin = $this->createUser('admin-prodi-without-scope', 'admin_prodi');
        $alumni = $this->createAlumni(null, 'alumni-without-scope@example.test');

        $response = $this->actingAs($admin)->post(route('prodi.alumni.send-email', $alumni->id));

        $response->assertForbidden();
        Mail::assertNothingSent();
    }

    /**
     * Admin Prodi hanya boleh mengirim email kepada alumni Prodi yang sama.
     */
    public function test_admin_prodi_cannot_send_email_to_another_prodi(): void
    {
        Mail::fake();

        $prodiA = $this->createProdi('PA', 'Program Studi A');
        $prodiB = $this->createProdi('PB', 'Program Studi B');
        $admin = $this->createUser('admin-prodi-a', 'admin_prodi', $prodiA->id);
        $alumni = $this->createAlumni($prodiB->id, 'alumni-prodi-b@example.test');

        $response = $this->actingAs($admin)->post(route('prodi.alumni.send-email', $alumni->id));

        $response->assertNotFound();
        Mail::assertNothingSent();
    }

    /**
     * Admin Fakultas hanya boleh mengirim email kepada alumni di Fakultasnya.
     */
    public function test_admin_fakultas_cannot_send_email_to_another_faculty(): void
    {
        Mail::fake();

        $fakultasA = $this->createFakultas('FA', 'Fakultas A');
        $fakultasB = $this->createFakultas('FB', 'Fakultas B');
        $prodiB = $this->createProdi('PB', 'Program Studi B', $fakultasB->id);
        $admin = $this->createUser('admin-fakultas-a', 'admin_fakultas', null, $fakultasA->id);
        $alumni = $this->createAlumni($prodiB->id, 'alumni-fakultas-b@example.test');

        $response = $this->actingAs($admin)->post(route('fakultas.alumni.send-email', $alumni->id));

        $response->assertNotFound();
        Mail::assertNothingSent();
    }

    /**
     * Admin Prodi dapat mengirim email kepada alumni dalam scope-nya.
     */
    public function test_admin_prodi_can_send_email_to_alumni_in_same_prodi(): void
    {
        Mail::fake();

        $prodi = $this->createProdi('PA', 'Program Studi A');
        $admin = $this->createUser('admin-prodi-a', 'admin_prodi', $prodi->id);
        $alumni = $this->createAlumni($prodi->id, 'alumni-prodi-a@example.test');

        $response = $this->actingAs($admin)->post(route('prodi.alumni.send-email', $alumni->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertSent(PengingatKuesionerAlumniMail::class, function (PengingatKuesionerAlumniMail $mail) use ($alumni): bool {
            return $mail->biodata->is($alumni);
        });
    }

    /**
     * Admin Prodi dapat menelusuri alumni Prodi lain dari analitik dalam mode baca-saja.
     */
    public function test_admin_prodi_can_view_other_prodi_from_analytics_as_read_only(): void
    {
        $prodiA = $this->createProdi('PA', 'Program Studi A');
        $prodiB = $this->createProdi('PB', 'Program Studi B');
        $admin = $this->createUser('admin-prodi-a-readonly', 'admin_prodi', $prodiA->id);
        $alumni = $this->createAlumni($prodiB->id, 'alumni-prodi-b-readonly@example.test');

        $this->actingAs($admin)
            ->get(route('prodi.analytics.alumni.show', $alumni->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminProdi/Alumni/Show')
                ->where('readonly', true)
                ->where('alumni.id', $alumni->id)
            );

        $this->actingAs($admin)
            ->post(route('prodi.alumni.profile.update', $alumni->id), [
                'nama' => 'Tidak boleh diubah lintas Prodi',
            ])
            ->assertNotFound();
    }

    /**
     * Biro 3 dapat menelusuri seluruh stepper tanpa membuka aksi perubahan profil.
     */
    public function test_biro3_detail_is_read_only(): void
    {
        $prodi = $this->createProdi('PB3', 'Program Studi Biro 3');
        $admin = $this->createUser('admin-biro3-readonly', 'admin_biro3');
        $alumni = $this->createAlumni($prodi->id, 'alumni-biro3-readonly@example.test');

        $this->actingAs($admin)
            ->get(route('biro3.analytics.alumni.show', $alumni->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AdminBiroTiga/AlumniShow')
                ->where('readonly', true)
                ->where('alumni.id', $alumni->id)
            );
    }

    /**
     * Membuat master Fakultas untuk kebutuhan setup endpoint.
     */
    private function createFakultas(string $kode, string $nama): RefFakultas
    {
        return RefFakultas::create([
            'kode_fakultas' => $kode,
            'nama_fakultas' => $nama,
        ]);
    }

    /**
     * Membuat master Program Studi untuk kebutuhan setup endpoint.
     */
    private function createProdi(string $kode, string $nama, ?int $fakultasId = null): Prodi
    {
        return Prodi::create([
            'fakultas_id' => $fakultasId,
            'kode_prodi' => $kode,
            'nama_prodi' => $nama,
        ]);
    }

    /**
     * Membuat akun admin dengan scope yang diberikan.
     */
    private function createUser(string $username, string $role, ?int $prodiId = null, ?int $fakultasId = null): User
    {
        return User::create([
            'username' => $username,
            'name' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'prodi_id' => $prodiId,
            'fakultas_id' => $fakultasId,
            'must_change_password' => false,
        ]);
    }

    /**
     * Membuat akun dan profil alumni pada Program Studi tertentu.
     */
    private function createAlumni(?int $prodiId, string $email): Biodata
    {
        $nim = 'AL'.str_pad((string) (Biodata::count() + 1), 6, '0', STR_PAD_LEFT);
        $user = User::create([
            'username' => $nim,
            'name' => 'Alumni '.$nim,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $prodiId,
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => $nim,
            'nama' => 'Alumni '.$nim,
            'email_pribadi' => $email,
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'nama' => 'Alumni '.$nim,
            'prodi_id' => $prodiId,
            'email_pribadi' => $email,
        ]);
    }
}
