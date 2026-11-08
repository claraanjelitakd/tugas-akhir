<?php

namespace Tests\Feature;

use App\Models\Propinsi;
use App\Models\Ump;
use Database\Seeders\UmpSeeder;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test relasi foreign key kode_provinsi antara Ump dan Propinsi.
     */
    public function test_can_create_ump_with_foreign_key_to_kode_provinsi(): void
    {
        $propinsi = Propinsi::create([
            'kode_provinsi' => '31',
            'nama_provinsi' => 'DKI Jakarta',
        ]);

        $ump = Ump::create([
            'kode_provinsi' => '31',
            'tahun' => 2026,
            'besaran' => 5729876,
            'catatan' => null,
        ]);

        $this->assertDatabaseHas('ump', [
            'kode_provinsi' => '31',
            'tahun' => 2026,
            'besaran' => '5729876.00',
        ]);

        $this->assertEquals('DKI Jakarta', $ump->propinsi->nama_provinsi);
        $this->assertEquals('5729876.00', (string) $propinsi->ump->besaran);
        $this->assertCount(1, $propinsi->umps);
    }

    /**
     * Test factory UMP memakai model Propinsi yang tersedia pada schema aktif.
     */
    public function test_ump_factory_uses_existing_propinsi_model(): void
    {
        $propinsi = Propinsi::create([
            'kode_provinsi' => '34',
            'nama_provinsi' => 'DI Yogyakarta',
        ]);

        $ump = Ump::factory()->create();

        $this->assertSame($propinsi->kode_provinsi, $ump->kode_provinsi);
        $this->assertTrue($ump->propinsi->is($propinsi));
    }

    /**
     * Test seeder UmpSeeder memuat nominal CSV dan memetakan kode Papua ke
     * kode master wilayah aplikasi tanpa membuat foreign key baru.
     */
    public function test_ump_seeder_successfully_seeds_all_provinces(): void
    {
        $this->seed(WilayahSeeder::class);
        $this->seed(UmpSeeder::class);

        $this->assertEquals(38, Ump::count());

        // Verifikasi Aceh
        $aceh = Ump::where('kode_provinsi', '11')->first();
        $this->assertNotNull($aceh);
        $this->assertEquals('3932552.00', (string) $aceh->besaran);

        // Verifikasi DKI Jakarta
        $jakarta = Ump::where('kode_provinsi', '31')->first();
        $this->assertNotNull($jakarta);
        $this->assertEquals('5729876.00', (string) $jakarta->besaran);

        // Verifikasi NTT
        $ntt = Ump::where('kode_provinsi', '53')->first();
        $this->assertNotNull($ntt);
        $this->assertEquals('2455898.00', (string) $ntt->besaran);

        $this->assertSame('3228949.00', (string) Ump::where('kode_provinsi', '12')->value('besaran'));
        $this->assertSame('3762431.00', (string) Ump::where('kode_provinsi', '64')->value('besaran'));
        $this->assertSame('3510240.00', (string) Ump::where('kode_provinsi', '82')->value('besaran'));
        $this->assertSame('4436283.00', (string) Ump::where('kode_provinsi', '91')->value('besaran'));
        $this->assertSame('3841000.00', (string) Ump::where('kode_provinsi', '92')->value('besaran'));
        $this->assertSame('4508100.00', (string) Ump::where('kode_provinsi', '93')->value('besaran'));
        $this->assertSame('4285848.00', (string) Ump::where('kode_provinsi', '94')->value('besaran'));
        $this->assertSame('4508714.00', (string) Ump::where('kode_provinsi', '95')->value('besaran'));
        $this->assertSame('3766000.00', (string) Ump::where('kode_provinsi', '96')->value('besaran'));
    }
}
