<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\Prodi;
use Database\Seeders\AlumniSistemInformasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Seeder Data Induk Alumni Sistem Informasi FTI UKDW
 */
class AlumniSistemInformasiSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan seluruh 8 alumni berprestasi Sistem Informasi berhasil diseed lengkap
     */
    public function test_alumni_sistem_informasi_seeder_populates_all_entities(): void
    {
        Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $this->seed(AlumniSistemInformasiSeeder::class);

        $expectedNims = [
            '72050015', // Laurentia Lisa Setiawan
            '23100570', // Yafet Andromeda
            '72180180', // Magdalena Evelyn Halim
            '72120088', // Manila Kristin
            '72200391', // Gilbert Christyano Day
            '72160043', // Agustinus Arsa Paskha Avaleriantha
            '23070204', // Teddy Wirawan
            '72210055', // Patrik Kurniawan Saputra
        ];

        foreach ($expectedNims as $nim) {
            $this->assertDatabaseHas('users', ['username' => $nim, 'role' => 'alumni']);
            $this->assertDatabaseHas('data_akademik', ['nim' => $nim]);
            $this->assertDatabaseHas('yudisium', ['nim' => $nim]);
            $this->assertDatabaseHas('biodata', ['nim' => $nim]);
        }

        $yafet = Biodata::where('nim', '23100570')->first();
        $this->assertNotNull($yafet);
        $this->assertEquals('Lead Data Engineer', $yafet->posisi_jabatan);
        $this->assertEquals('FinAccel (Kredivo)', $yafet->perusahaan?->nama_perusahaan);

        $laurentia = Biodata::where('nim', '72050015')->first();
        $this->assertNotNull($laurentia);
        $this->assertEquals('Wiraswasta', $laurentia->kategori_pekerjaan);
        $this->assertEquals('Owner & Founder', $laurentia->posisi_wiraswasta);
        $this->assertEquals('PT Glori Indah Farm', $laurentia->perusahaan?->nama_perusahaan);
    }
}
