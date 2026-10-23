<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Memastikan schema fresh-install mengikuti kontrak data profil aktif.
 */
class ProfileSchemaContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tabel orang tua tidak dibuat pada schema aktif.
     */
    public function test_parent_table_is_not_created(): void
    {
        $this->assertFalse(Schema::hasTable('data_orang_tua'));
    }

    /**
     * Field sensitif dan relasi legacy tidak tersedia di tabel aktif.
     */
    public function test_sensitive_and_legacy_columns_are_removed(): void
    {
        foreach (['biodata', 'data_akademik'] as $table) {
            foreach (['nik', 'npwp', 'no_kk', 'orang_tua_id'] as $column) {
                $this->assertFalse(Schema::hasColumn($table, $column), $table.'.'.$column.' masih tersedia.');
            }
        }

        foreach (['dosen_pembimbing_1', 'dosen_pembimbing_2', 'dosen_penguji_1', 'dosen_penguji_2'] as $column) {
            $this->assertFalse(Schema::hasColumn('yudisium', $column), 'yudisium.'.$column.' masih tersedia.');
        }
    }

    /**
     * Kontak dan domisili aktif hanya tersedia pada biodata.
     */
    public function test_biodata_is_the_canonical_contact_and_address_table(): void
    {
        foreach (['nomor_telepon', 'email_pribadi', 'alamat_saat_ini', 'kelurahan', 'kecamatan', 'kabupaten_id', 'propinsi_id', 'kode_pos'] as $column) {
            $this->assertTrue(Schema::hasColumn('biodata', $column), 'biodata.'.$column.' tidak tersedia.');
            $this->assertFalse(Schema::hasColumn('data_akademik', $column), 'data_akademik.'.$column.' masih duplikat.');
        }
    }
}
