<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base test case untuk seluruh feature test aplikasi.
 *
 * Menyediakan fixture negara default hanya ketika migrasi database test aktif.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the test case and register the default country required by the
     * canonical company foreign key before individual fixtures are created.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Beberapa feature test hanya merender view dan tidak menjalankan migrasi database.
        if (! Schema::hasTable('ref_negara')) {
            return;
        }

        DB::table('ref_negara')->insertOrIgnore([
            'kode_negara' => 'ID',
            'kode_iso3' => 'IDN',
            'nama_negara' => 'Indonesia',
            'nama_resmi' => 'Republik Indonesia',
            'benua' => 'Asia',
            'ibu_kota' => 'Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
