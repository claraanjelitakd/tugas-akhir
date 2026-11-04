<?php

namespace Tests\Feature;

use App\Models\Kuesioner;
use App\Models\KuesionerSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminKelolaSectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected Kuesioner $kuesioner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'username' => 'superadmin_test',
            'name' => 'Super Administrator Test',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);

        $this->kuesioner = Kuesioner::create([
            'title' => 'Tracer Study 2026',
            'year' => 2026,
            'is_active' => true,
        ]);
    }

    /**
     * Test superadmin dapat melihat halaman daftar section.
     */
    public function test_superadmin_can_view_sections_page(): void
    {
        KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-1',
            'target' => 'univ',
            'judul' => 'Bagian Identitas Diri',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.sections.index'));

        $response->assertRedirect(route('superadmin.pertanyaan.index', ['tab' => 'sections']));
    }

    /**
     * Test superadmin dapat menambahkan section baru.
     */
    public function test_superadmin_can_create_new_section(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.sections.store'), [
                'title' => 'Bagian Riwayat Pekerjaan',
                'order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kuesioner_section', [
            'target' => 'univ',
            'judul' => 'Bagian Riwayat Pekerjaan',
            'order' => 1,
        ]);
    }

    /**
     * Test superadmin dapat memperbarui section yang sudah ada.
     */
    public function test_superadmin_can_update_section(): void
    {
        $section = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-OLD',
            'target' => 'univ',
            'judul' => 'Judul Lama',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.sections.update', $section->id), [
                'title' => 'Judul Baru yang Diperbarui',
                'order' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kuesioner_section', [
            'id' => $section->id,
            'judul' => 'Judul Baru yang Diperbarui',
        ]);
    }

    /**
     * Test superadmin dapat menghapus section.
     */
    public function test_superadmin_can_delete_section(): void
    {
        $section = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-DEL',
            'target' => 'univ',
            'judul' => 'Bagian untuk Dihapus',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->delete(route('superadmin.sections.destroy', $section->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('kuesioner_section', [
            'id' => $section->id,
        ]);
    }

    /**
     * Test superadmin dapat menukar urutan section (up / down).
     */
    public function test_superadmin_can_reorder_sections_directionally(): void
    {
        $section1 = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-1',
            'target' => 'univ',
            'judul' => 'Section 1',
            'order' => 1,
            'is_active' => true,
        ]);

        $section2 = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-2',
            'target' => 'univ',
            'judul' => 'Section 2',
            'order' => 2,
            'is_active' => true,
        ]);

        // Pindahkan section 2 ke atas (direction = up)
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.sections.reorder'), [
                'id' => $section2->id,
                'direction' => 'up',
            ]);

        $response->assertRedirect();

        $this->assertEquals(1, $section2->fresh()->order);
        $this->assertEquals(2, $section1->fresh()->order);
    }

    /**
     * Test superadmin dapat mengubah urutan section secara massal.
     */
    public function test_superadmin_can_bulk_reorder_sections(): void
    {
        $secA = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-A',
            'target' => 'univ',
            'judul' => 'Section A',
            'order' => 1,
            'is_active' => true,
        ]);

        $secB = KuesionerSection::create([
            'kode_section' => 'SEC-UNIV-B',
            'target' => 'univ',
            'judul' => 'Section B',
            'order' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.sections.reorder'), [
                'orders' => [
                    ['id' => $secA->id, 'order' => 5],
                    ['id' => $secB->id, 'order' => 6],
                ],
            ]);

        $response->assertRedirect();

        $this->assertEquals(5, $secA->fresh()->order);
        $this->assertEquals(6, $secB->fresh()->order);
    }

    /**
     * Test superadmin dapat membuat kuesioner induk baru.
     */
    public function test_superadmin_can_create_kuesioner(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.kuesioner.store'), [
                'title' => 'Tracer Study UKDW 2024',
                'description' => 'Kuesioner evaluasi alumni tahun 2024',
                'year' => 2024,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kuesioner', [
            'title' => 'Tracer Study UKDW 2024',
            'year' => 2024,
            'is_active' => true,
        ]);

        // Pastikan kuesioner lama otomatis dinonaktifkan jika yang baru diset aktif
        $this->assertFalse((bool) $this->kuesioner->fresh()->is_active);
    }

    /**
     * Test superadmin dapat memperbarui data kuesioner induk.
     */
    public function test_superadmin_can_update_kuesioner(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.kuesioner.update', $this->kuesioner->id), [
                'title' => 'Tracer Study UKDW 2026 Revisi',
                'description' => 'Deskripsi diperbarui',
                'year' => 2026,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kuesioner', [
            'id' => $this->kuesioner->id,
            'title' => 'Tracer Study UKDW 2026 Revisi',
        ]);
    }

    /**
     * Test superadmin dapat melakukan toggle status aktif kuesioner.
     */
    public function test_superadmin_can_toggle_kuesioner_active_status(): void
    {
        $this->assertTrue((bool) $this->kuesioner->is_active);

        $response = $this->actingAs($this->superadmin)
            ->patch(route('superadmin.kuesioner.toggle-active', $this->kuesioner->id));

        $response->assertRedirect();
        $this->assertFalse((bool) $this->kuesioner->fresh()->is_active);
    }

    /**
     * Test superadmin dapat menghapus kuesioner induk.
     */
    public function test_superadmin_can_delete_kuesioner(): void
    {
        $kuesionerToDelete = Kuesioner::create([
            'title' => 'Kuesioner Sementara',
            'year' => 2025,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->delete(route('superadmin.kuesioner.destroy', $kuesionerToDelete->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('kuesioner', [
            'id' => $kuesionerToDelete->id,
        ]);
    }
}
