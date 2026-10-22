<?php

namespace Tests\Feature;

use App\Mail\UndanganEvaluasiAtasanMail;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvaluasiAtasanTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_does_not_redirect_to_latest_token(): void
    {
        $response = $this->get('/evaluasi-atasan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Public/EvaluasiAtasanNotFound'));
    }

    public function test_public_can_view_evaluasi_atasan_form_with_valid_token(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi']);
        $user = User::create([
            'username' => '72200001',
            'name' => 'Budi Alumni',
            'email' => 'budi@example.com',
            'password' => bcrypt('password'),
            'role' => 'alumni',
        ]);
        DataAkademik::create(['nim' => '72200001', 'nama' => 'Budi Alumni']);
        $biodata = Biodata::where('nim', '72200001')->first();
        if (! $biodata) {
            $biodata = Biodata::create(['user_id' => $user->id, 'nim' => '72200001', 'nama' => 'Budi Alumni', 'prodi_id' => $prodi->id]);
        } else {
            $biodata->update(['prodi_id' => $prodi->id, 'nama' => 'Budi Alumni']);
        }

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Teknologi Indonesia',
            'alamat' => 'Jl. Jenderal Sudirman No. 10',
            'homepage' => 'https://tekno.co.id',
        ]);

        $evaluasi = EvaluasiAtasan::create([
            'biodata_id' => $biodata->id,
            'perusahaan_id' => $perusahaan->id,
            'token' => Str::random(40),
            'nama_perusahaan' => 'PT Teknologi Indonesia',
            'alamat_lengkap' => 'Jl. Jenderal Sudirman No. 10',
        ]);

        $response = $this->get("/evaluasi-atasan/{$evaluasi->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/EvaluasiAtasanForm')
            ->where('token', $evaluasi->token)
            ->where('alumniInfo.nama', 'Budi Alumni')
        );
    }

    public function test_invalid_token_returns_not_found_component(): void
    {
        $response = $this->get('/evaluasi-atasan/invalid-token-123456');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/EvaluasiAtasanNotFound')
        );
    }

    public function test_atasan_can_submit_evaluasi_form(): void
    {
        $prodi = Prodi::create(['kode_prodi' => '72', 'nama_prodi' => 'Sistem Informasi']);
        $user = User::create([
            'username' => '72200002',
            'name' => 'Alumni Test',
            'email' => 'alumni@example.com',
            'password' => bcrypt('password'),
            'role' => 'alumni',
        ]);
        DataAkademik::create(['nim' => '72200002', 'nama' => 'Alumni Test']);
        $biodata = Biodata::where('nim', '72200002')->first();
        if (! $biodata) {
            $biodata = Biodata::create(['user_id' => $user->id, 'nim' => '72200002', 'prodi_id' => $prodi->id]);
        } else {
            $biodata->update(['prodi_id' => $prodi->id]);
        }

        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Makmur Sentosa',
            'alamat' => 'Jl. Magelang KM 5, Yogyakarta',
        ]);

        $evaluasi = EvaluasiAtasan::create([
            'biodata_id' => $biodata->id,
            'perusahaan_id' => $perusahaan->id,
            'token' => Str::random(40),
        ]);

        PertanyaanEvaluasiAtasan::create([
            'kode' => 'integritas',
            'aspek' => 'Integritas',
            'order' => 1,
        ]);

        $postData = [
            'nama_perusahaan' => 'PT Makmur Sentosa',
            'alamat_lengkap' => 'Jl. Magelang KM 5, Yogyakarta',
            'no_telp_fax' => '0274-123456',
            'homepage' => 'https://makmursentosa.com',
            'nama_atasan' => 'Bapak Atasan',
            'email_atasan' => 'atasan@makmursentosa.com',
            'telepon_atasan' => '08123456789',
            'bentuk_perusahaan' => 'Perusahaan Terbatas',
            'skala_perusahaan' => 'Nasional',
            'jumlah_pegawai' => '51 - 100 Orang',
            'jumlah_alumni_ukdw' => '< 5 Orang',
            'standar_gaji_pertama' => '3.000.000 – 4.000.000',
            'tingkat_kesiapan_kerja' => 'Sangat siap',
            'jawaban' => [
                'integritas' => 'Sangat Tinggi',
            ],
            'catatan_lainnya' => 'Kinerja alumni sangat memuaskan.',
        ];

        $response = $this->post("/evaluasi-atasan/{$evaluasi->token}", $postData);

        $response->assertStatus(302);
        $this->assertDatabaseHas('evaluasi_atasan', [
            'id' => $evaluasi->id,
            'is_submitted' => true,
            'jumlah_alumni_ukdw' => '< 5 Orang',
        ]);

        $this->assertDatabaseHas('perusahaan', [
            'id' => $perusahaan->id,
            'nama_perusahaan' => 'PT Makmur Sentosa',
            'alamat' => 'Jl. Magelang KM 5, Yogyakarta',
            'bentuk_perusahaan' => null,
            'jumlah_pegawai' => null,
            'homepage' => null,
            'no_telp_fax' => null,
        ]);

        $this->assertDatabaseHas('respon_evaluasi_atasan', [
            'evaluasi_atasan_id' => $evaluasi->id,
            'nilai' => 'Sangat Tinggi',
        ]);

        $this->assertDatabaseHas('atasan', [
            'nama' => 'Bapak Atasan',
            'email' => 'atasan@makmursentosa.com',
            'telepon' => '08123456789',
        ]);
        $this->assertNotNull($biodata->fresh()->atasan_id);

        $this->post("/evaluasi-atasan/{$evaluasi->token}", $postData)
            ->assertSessionHasErrors('jawaban');
    }

    public function test_superadmin_can_send_email_undangan_atasan(): void
    {
        Mail::fake();

        $prodi = Prodi::create(['kode_prodi' => '71', 'nama_prodi' => 'Informatika']);
        $admin = User::create([
            'username' => 'admin_super',
            'name' => 'Super Administrator',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        $alumniUser = User::create([
            'username' => '71200001',
            'name' => 'Alumni Informatika',
            'email' => 'alumni_if@example.com',
            'password' => bcrypt('password'),
            'role' => 'alumni',
        ]);

        DataAkademik::create(['nim' => '71200001', 'nama' => 'Alumni Informatika']);
        $biodata = Biodata::where('nim', '71200001')->first();
        if (! $biodata) {
            $biodata = Biodata::create([
                'user_id' => $alumniUser->id,
                'nim' => '71200001',
                'prodi_id' => $prodi->id,
            ]);
        } else {
            $biodata->update([
                'prodi_id' => $prodi->id,
            ]);
        }

        $response = $this->actingAs($admin)->post('/alumni/kirim-email-atasan', [
            'biodata_id' => $biodata->id,
            'email_atasan' => 'boss@company.com',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        Mail::assertSent(UndanganEvaluasiAtasanMail::class, function ($mail) {
            return $mail->hasTo('boss@company.com');
        });
    }

    public function test_admin_prodi_can_only_send_email_atasan_within_their_prodi(): void
    {
        Mail::fake();

        $prodi1 = Prodi::create(['kode_prodi' => '73', 'nama_prodi' => 'Teknik Elektro']);
        $prodi2 = Prodi::create(['kode_prodi' => '74', 'nama_prodi' => 'Teknik Arsitektur']);

        $adminProdi = User::create([
            'username' => 'admin_te',
            'name' => 'Admin Prodi TE',
            'email' => 'admin_te@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin_prodi',
            'prodi_id' => $prodi1->id,
        ]);

        $userAlumni2 = User::create([
            'username' => '74200001',
            'name' => 'Alumni Arsitektur',
            'email' => 'alumni_ars@example.com',
            'password' => bcrypt('password'),
            'role' => 'alumni',
        ]);

        DataAkademik::create(['nim' => '74200001', 'nama' => 'Alumni Arsitektur']);
        $alumniProdi2 = Biodata::where('nim', '74200001')->first();
        if (! $alumniProdi2) {
            $alumniProdi2 = Biodata::create([
                'user_id' => $userAlumni2->id,
                'nim' => '74200001',
                'prodi_id' => $prodi2->id,
            ]);
        } else {
            $alumniProdi2->update(['prodi_id' => $prodi2->id]);
        }

        // Harus 403 jika prodi tidak cocok
        $response = $this->actingAs($adminProdi)->post('/alumni/kirim-email-atasan', [
            'biodata_id' => $alumniProdi2->id,
            'email_atasan' => 'atasan_ars@studio.com',
        ]);

        $response->assertStatus(403);
    }
}
