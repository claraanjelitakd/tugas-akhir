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

    /** Memastikan tabel legacy orang tua tidak dibuat. */
    public function test_parent_table_is_not_created(): void
    {
        $this->assertFalse(Schema::hasTable('data_orang_tua'));
    }

    /** Memastikan field sensitif dan field sekolah/place tidak tersedia. */
    public function test_sensitive_and_removed_columns_are_absent(): void
    {
        foreach (['biodata', 'data_akademik'] as $table) {
            foreach (['nik', 'npwp', 'no_kk', 'orang_tua_id'] as $column) {
                $this->assertFalse(Schema::hasColumn($table, $column));
            }
        }

        foreach (['tempat_lahir', 'email_students', 'asal_sekolah', 'provinsi_asal_sekolah', 'kota_kabupaten_asal_sekolah', 'jurusan_asal_sekolah', 'alamat_asal_sekolah'] as $column) {
            $this->assertFalse(Schema::hasColumn('data_akademik', $column));
            $this->assertFalse(Schema::hasColumn('biodata', $column));
        }
    }

    /** Memastikan domisili memakai kode wilayah sebagai PK/FK canonical. */
    public function test_biodata_uses_canonical_wilayah_keys(): void
    {
        $activeBiodataColumns = ['nomor_telepon', 'alamat_saat_ini', 'kelurahan', 'kecamatan', 'kode_kabupaten', 'kode_provinsi', 'kode_pos'];

        foreach ($activeBiodataColumns as $column) {
            $this->assertTrue(Schema::hasColumn('biodata', $column));
            $this->assertFalse(Schema::hasColumn('data_akademik', $column));
        }

        $this->assertTrue(Schema::hasColumn('data_akademik', 'email_pribadi'));
        $this->assertTrue(Schema::hasColumn('biodata', 'email_pribadi'));

        $this->assertTrue(Schema::hasColumn('propinsi', 'kode_provinsi'));
        $this->assertTrue(Schema::hasColumn('kabupaten', 'kode_kabupaten'));
        $this->assertFalse(Schema::hasColumn('propinsi', 'id'));
        $this->assertFalse(Schema::hasColumn('kabupaten', 'id'));
        $this->assertTrue(Schema::hasColumn('perusahaan', 'kode_negara'));
        $this->assertFalse(Schema::hasColumn('perusahaan', 'negara'));
    }

    /** Memastikan bidang pekerjaan dan divisi tersimpan sebagai atribut biodata yang berbeda. */
    public function test_biodata_has_separate_job_field_and_division_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('biodata', 'bidang_pekerjaan'));
        $this->assertTrue(Schema::hasColumn('biodata', 'divisi'));
    }
}
