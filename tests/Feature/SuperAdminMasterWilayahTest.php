<?php

namespace Tests\Feature;

use App\Models\Kabupaten;
use App\Models\Propinsi;
use App\Models\Ump;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminMasterWilayahTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $alumni;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_wilayah',
            'name' => 'Super Administrator Wilayah',
            'email' => 'superadmin_wilayah@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->alumni = User::create([
            'username' => 'alumni_wilayah',
            'name' => 'Alumni User',
            'email' => 'alumni_wilayah@ukdw.ac.id',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
    }

    /**
     * Test non-superadmin dialihkan atau tidak memiliki izin akses.
     */
    public function test_non_superadmin_cannot_access_master_wilayah(): void
    {
        $response = $this->actingAs($this->alumni)->get(route('superadmin.master-wilayah.index'));
        $response->assertStatus(403);
    }

    /**
     * Test superadmin dapat mengakses halaman master wilayah dan menerima data lengkap.
     */
    public function test_superadmin_can_view_master_wilayah_page(): void
    {
        $provinsi = Propinsi::create([
            'kode_provinsi' => '34',
            'nama_provinsi' => 'DAERAH ISTIMEWA YOGYAKARTA',
        ]);

        Kabupaten::create([
            'kode_provinsi' => $provinsi->kode_provinsi,
            'kode_kabupaten' => '34.71',
            'nama_kabupaten' => 'KOTA YOGYAKARTA',
        ]);

        Ump::create([
            'kode_provinsi' => '34',
            'tahun' => 2026,
            'besaran' => 2417495,
            'catatan' => 'SK Gubernur DIY',
        ]);

        $response = $this->actingAs($this->superadmin)->get(route('superadmin.master-wilayah.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/MasterWilayah/Index')
            ->has('stats')
            ->has('provinces')
            ->has('kabupatens')
            ->where('stats.total_provinsi', 1)
            ->where('stats.total_kabupaten', 1)
            ->where('stats.total_ump_configured', 1)
        );
    }

    /**
     * Test superadmin dapat menambahkan provinsi baru beserta ketetapan UMP awal.
     */
    public function test_superadmin_can_store_provinsi_with_ump(): void
    {
        $response = $this->actingAs($this->superadmin)->post(route('superadmin.master-wilayah.provinsi.store'), [
            'kode_provinsi' => '31',
            'nama_provinsi' => 'DKI Jakarta',
            'besaran_ump' => 5729876,
            'tahun_ump' => 2026,
            'catatan_ump' => 'SK Gubernur DKI Jakarta',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('propinsi', [
            'kode_provinsi' => '31',
            'nama_provinsi' => 'DKI JAKARTA',
        ]);

        $this->assertDatabaseHas('ump', [
            'kode_provinsi' => '31',
            'tahun' => 2026,
            'besaran' => '5729876.00',
        ]);
    }

    /**
     * Test superadmin dapat memperbarui data provinsi.
     */
    public function test_superadmin_can_update_provinsi(): void
    {
        $provinsi = Propinsi::create([
            'kode_provinsi' => '32',
            'nama_provinsi' => 'JAWA BARAT',
        ]);

        $response = $this->actingAs($this->superadmin)->put(route('superadmin.master-wilayah.provinsi.update', $provinsi->kode_provinsi), [
            'kode_provinsi' => '32',
            'nama_provinsi' => 'PROVINSI JAWA BARAT',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('propinsi', [
            'kode_provinsi' => '32',
            'nama_provinsi' => 'PROVINSI JAWA BARAT',
        ]);
    }

    /**
     * Test superadmin dapat menghapus provinsi jika tidak memiliki kabupaten/kota.
     */
    public function test_superadmin_can_delete_provinsi(): void
    {
        $provinsi = Propinsi::create([
            'kode_provinsi' => '99',
            'nama_provinsi' => 'PROVINSI UJI COBA',
        ]);

        $response = $this->actingAs($this->superadmin)->delete(route('superadmin.master-wilayah.provinsi.destroy', $provinsi->kode_provinsi));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('propinsi', [
            'kode_provinsi' => '99',
        ]);
    }

    /**
     * Test superadmin dicegah menghapus provinsi yang masih memuat kabupaten/kota.
     */
    public function test_superadmin_cannot_delete_provinsi_with_kabupaten(): void
    {
        $provinsi = Propinsi::create([
            'kode_provinsi' => '34',
            'nama_provinsi' => 'DAERAH ISTIMEWA YOGYAKARTA',
        ]);

        Kabupaten::create([
            'kode_provinsi' => $provinsi->kode_provinsi,
            'kode_kabupaten' => '34.04',
            'nama_kabupaten' => 'KABUPATEN SLEMAN',
        ]);

        $response = $this->actingAs($this->superadmin)->delete(route('superadmin.master-wilayah.provinsi.destroy', $provinsi->kode_provinsi));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('propinsi', [
            'kode_provinsi' => '34',
        ]);
    }

    /**
     * Test superadmin dapat menambahkan, memperbarui, dan menghapus kabupaten/kota.
     */
    public function test_superadmin_can_crud_kabupaten(): void
    {
        $provinsi = Propinsi::create([
            'kode_provinsi' => '33',
            'nama_provinsi' => 'JAWA TENGAH',
        ]);

        // 1. Tambah
        $storeResponse = $this->actingAs($this->superadmin)->post(route('superadmin.master-wilayah.kabupaten.store'), [
            'kode_provinsi' => $provinsi->kode_provinsi,
            'kode_kabupaten' => '33.74',
            'nama_kabupaten' => 'KOTA SEMARANG',
        ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('kabupaten', [
            'kode_kabupaten' => '33.74',
            'nama_kabupaten' => 'KOTA SEMARANG',
        ]);

        $kabupaten = Kabupaten::where('kode_kabupaten', '33.74')->first();

        // 2. Edit
        $updateResponse = $this->actingAs($this->superadmin)->put(route('superadmin.master-wilayah.kabupaten.update', $kabupaten->kode_kabupaten), [
            'kode_provinsi' => $provinsi->kode_provinsi,
            'kode_kabupaten' => '33.74',
            'nama_kabupaten' => 'KOTA SEMARANG HEBAT',
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('kabupaten', [
            'kode_kabupaten' => '33.74',
            'nama_kabupaten' => 'KOTA SEMARANG HEBAT',
        ]);

        // 3. Hapus
        $deleteResponse = $this->actingAs($this->superadmin)->delete(route('superadmin.master-wilayah.kabupaten.destroy', $kabupaten->kode_kabupaten));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('kabupaten', [
            'kode_kabupaten' => '33.74',
        ]);
    }

    /**
     * Test superadmin dapat mengatur UMP dan menyalin UMP ke tahun berikutnya.
     */
    public function test_superadmin_can_set_and_copy_ump(): void
    {
        Propinsi::create([
            'kode_provinsi' => '51',
            'nama_provinsi' => 'BALI',
        ]);

        // Set UMP 2026
        $storeUmpResponse = $this->actingAs($this->superadmin)->post(route('superadmin.master-wilayah.ump.store'), [
            'kode_provinsi' => '51',
            'tahun' => 2026,
            'besaran' => 3207459,
            'catatan' => 'Ketetapan UMP Bali 2026',
        ]);

        $storeUmpResponse->assertRedirect();
        $this->assertDatabaseHas('ump', [
            'kode_provinsi' => '51',
            'tahun' => 2026,
            'besaran' => '3207459.00',
        ]);

        // Salin UMP ke tahun 2027 dengan kenaikan 10%
        $copyResponse = $this->actingAs($this->superadmin)->post(route('superadmin.master-wilayah.ump.copy-year'), [
            'source_year' => 2026,
            'target_year' => 2027,
            'percentage_increase' => 10,
        ]);

        $copyResponse->assertRedirect();
        $expected2027 = round(3207459 * 1.10);

        $this->assertDatabaseHas('ump', [
            'kode_provinsi' => '51',
            'tahun' => 2027,
            'besaran' => number_format($expected2027, 2, '.', ''),
        ]);
    }
}
