<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\ProdiResponse;
use App\Models\Tracer;
use App\Models\User;
use App\Models\Yudisium;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JoshuaAndreanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_joshua_andrean_seeder_populates_academic_data_with_clean_biodata(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('username', '72220001')->first();
        $this->assertNotNull($user, 'User Joshua Andrean harus ditemukan.');
        $this->assertEquals('Joshua Andrean', $user->name);
        $this->assertEquals('alumni', $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('15082004', $user->password));

        $dataAkademik = DataAkademik::where('nim', '72220001')->first();
        $this->assertNotNull($dataAkademik, 'Data akademik harus terisi.');
        $this->assertEquals('Joshua Andrean', $dataAkademik->nama);
        $this->assertEquals('2022', $dataAkademik->angkatan_masuk);
        $this->assertEquals(2026, $dataAkademik->tahun_lulus);
        $this->assertEquals('SMA Negeri 3 Yogyakarta', $dataAkademik->asal_sekolah);

        $yudisium = Yudisium::where('nim', '72220001')->first();
        $this->assertNotNull($yudisium, 'Data yudisium harus terisi.');
        $this->assertEquals(2026, $yudisium->tahun_lulus);
        $this->assertEquals('Lulus', $yudisium->proses_yudisium);

        $biodata = Biodata::where('nim', '72220001')->first();
        $this->assertNotNull($biodata, 'Biodata record harus terhubung.');
        $this->assertEquals($user->id, $biodata->user_id);
        $this->assertEquals('2026', $biodata->tahun_lulus);
        $this->assertEquals('Sistem Informasi', $biodata->prodi?->nama_prodi);

        // Pastikan field karier / perusahaan / data tambahan masih kosong (untuk diisi mandiri)
        $this->assertNull($biodata->perusahaan_id);
        $this->assertNull($biodata->atasan_id);
        $this->assertNull($biodata->posisi_jabatan);
        $this->assertNull($biodata->gaji);
        $this->assertNull($biodata->skills);
        $this->assertNull($biodata->experience);
        $this->assertNull($biodata->getAttribute('npwp'));

        // Pastikan kuesioner belum diisi
        $this->assertEquals(0, Tracer::where('biodata_id', $biodata->id)->count());
        $this->assertEquals(0, ProdiResponse::where('biodata_id', $biodata->id)->count());

        // Alumni baru wajib diarahkan untuk mengganti password awal.
        $response = $this->actingAs($user)->get(route('alumni.profile'));
        $response->assertRedirect('/change-password');

        $responseDashboard = $this->actingAs($user)->get('/alumni/dashboard');
        $responseDashboard->assertRedirect('/change-password');
    }
}
