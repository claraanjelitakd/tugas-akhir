<?php

namespace Tests\Feature;

use App\Models\DataAkademik;
use App\Models\Prodi;
use Database\Seeders\IkuS1AcademicPopulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan seeder IKU mengisi periodisasi masuk dan populasi non-alumni.
 */
class IkuS1AcademicPopulationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeder memakai semester ganjil dan memasukkan status non-lulus.
     */
    public function test_seeder_creates_s1_population_with_odd_entry_period(): void
    {
        foreach ([
            ['11', 'Manajemen'], ['12', 'Akuntansi'], ['21', 'Arsitektur'],
            ['41', 'Biologi'], ['42', 'Teknologi Pangan'],
        ] as [$code, $name]) {
            Prodi::create(['kode_prodi' => $code, 'nama_prodi' => $name, 'jenjang' => 'S1']);
        }

        $this->seed(IkuS1AcademicPopulationSeeder::class);

        $this->assertSame('2024/2025 (Semester Ganjil)', DataAkademik::where('nim', '90110001')->value('tahun_akademik_masuk'));
        $this->assertSame('Aktif', DataAkademik::where('nim', '90110001')->value('status_mahasiswa'));
        $this->assertSame('Pindah', DataAkademik::where('nim', '90210001')->value('status_mahasiswa'));
        $this->assertSame(6, DataAkademik::whereIn('status_mahasiswa', ['Aktif', 'Cuti', 'Drop Out', 'Pindah', 'AR', 'AK'])->count());
    }
}
