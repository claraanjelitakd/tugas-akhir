<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AlumniPerusahaanHeadOfficeProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function createAlumniUser(string $nim = '72220001'): array
    {
        $fakultas = RefFakultas::firstOrCreate(
            ['kode_fakultas' => 'FTI'],
            ['nama_fakultas' => 'Teknologi Informasi']
        );

        $prodi = Prodi::firstOrCreate(
            ['kode_prodi' => '72'],
            [
                'nama_prodi' => 'Sistem Informasi',
                'fakultas_id' => $fakultas->id,
                'jenjang' => 'S1',
            ]
        );

        $user = User::create([
            'name' => 'Alumni '.$nim,
            'email' => "alumni_{$nim}@example.com",
            'username' => $nim,
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);

        $akademik = DataAkademik::create([
            'nim' => $nim,
            'nama' => 'Alumni '.$nim,
            'email_pribadi' => "alumni_{$nim}@example.com",
            'tanggal_lahir' => '2001-01-01',
            'jenis_kelamin' => 'Laki-laki',
            'prodi_id' => $prodi->id,
            'status_mahasiswa' => 'Lulus',
            'tahun_masuk' => 2022,
            'tahun_lulus' => 2026,
            'ip_kumulatif' => 3.85,
        ]);

        $biodata = Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'nama' => 'Alumni '.$nim,
            'email_pribadi' => "alumni_{$nim}@example.com",
            'prodi_id' => $prodi->id,
            'data_akademik_id' => $akademik->id,
        ]);

        return [$user, $biodata];
    }

    /**
     * Uji data master head office yang terverifikasi tidak ditimpa langsung saat alumni menyimpan profil.
     */
    public function test_verified_head_office_company_cannot_be_overwritten_by_alumni_profile_save(): void
    {
        [$user, $biodata] = $this->createAlumniUser('72220001');

        $masterCompany = Perusahaan::create([
            'nama_perusahaan' => 'Dinas Komunikasi dan Informatika DIY',
            'status_verifikasi' => 'Terverifikasi',
            'alamat' => 'Jl. Brigjen Katamso No. 1, Yogyakarta',
            'homepage' => 'https://diskominfo.jogjaprov.go.id',
            'skala' => 'Regional/Lokal',
            'bentuk_perusahaan' => 'Instansi Pemerintah',
            'no_telp_fax' => '0274-563929',
            'kode_pos' => '55121',
        ]);

        $payload = [
            'nama_perusahaan' => 'Dinas Komunikasi dan Informatika DIY',
            'kategori_pekerjaan' => 'Pekerja',
            'company_alamat' => 'Alamat Palsu Mencoba Mengubah Master',
            'company_homepage' => 'https://hacker.com',
            'company_skala' => 'Internasional',
            'company_no_telp_fax' => '0899999999',
        ];

        $response = $this->actingAs($user)->post('/alumni/profile', $payload);

        $response->assertStatus(302);

        // Pastikan master company di database tetap utuh dan tidak ditimpa
        $masterCompany->refresh();
        $this->assertEquals('Jl. Brigjen Katamso No. 1, Yogyakarta', $masterCompany->alamat);
        $this->assertEquals('https://diskominfo.jogjaprov.go.id', $masterCompany->homepage);
        $this->assertEquals('Regional/Lokal', $masterCompany->skala);
        $this->assertEquals('0274-563929', $masterCompany->no_telp_fax);

        // Pastikan relasi biodata alumni tertaut ke perusahaan tersebut
        $biodata->refresh();
        $this->assertEquals($masterCompany->id, $biodata->perusahaan_id);
    }

    /**
     * Uji alumni dapat mengajukan usulan pembaruan data kantor pusat via request-update.
     */
    public function test_alumni_can_request_update_for_head_office_company(): void
    {
        [$user, $biodata] = $this->createAlumniUser('72220002');

        $masterCompany = Perusahaan::create([
            'nama_perusahaan' => 'PT Teknologi Jogja Istimewa',
            'status_verifikasi' => 'Terverifikasi',
            'alamat' => 'Jl. Gejayan No. 10',
            'homepage' => null,
            'no_telp_fax' => null,
        ]);

        $response = $this->actingAs($user)->postJson('/alumni/company/request-update', [
            'perusahaan_id' => $masterCompany->id,
            'nama_perusahaan' => 'PT Teknologi Jogja Istimewa',
            'jenis_lokasi' => 'Dalam Negeri',
            'kode_provinsi' => '34',
            'kode_kabupaten' => '3471',
            'jenis_perusahaan' => 'Perusahaan swasta',
            'bentuk_perusahaan' => 'Perusahaan Terbatas',
            'skala' => 'Nasional',
            'homepage' => 'https://teknojogja.co.id',
            'no_telp_fax' => '0274-888999',
            'alamat' => 'Jl. Gejayan No. 12 (Pindah Gedung Baru)',
            'kode_pos' => '55281',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        // Pastikan master company terverifikasi tidak langsung berubah
        $masterCompany->refresh();
        $this->assertNull($masterCompany->homepage);

        // Pastikan usulan tercatat di audit log activities
        $this->assertDatabaseHas('log_activities', [
            'action' => 'REQUEST_UPDATE_PERUSAHAAN',
            'model_id' => $masterCompany->id,
            'user_id' => $user->id,
        ]);
    }
}
