<?php

namespace Tests\Feature;

use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFakultasKuesionerProdiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_fakultas_can_manage_only_prodi_in_its_fakultas(): void
    {
        $fakultasA = RefFakultas::create([
            'kode_fakultas' => 'FA',
            'nama_fakultas' => 'Fakultas A',
        ]);
        $fakultasB = RefFakultas::create([
            'kode_fakultas' => 'FB',
            'nama_fakultas' => 'Fakultas B',
        ]);

        $prodiA = Prodi::create([
            'kode_prodi' => 'A1',
            'nama_prodi' => 'Program Studi A',
            'fakultas_id' => $fakultasA->id,
        ]);
        Prodi::create([
            'kode_prodi' => 'A2',
            'nama_prodi' => 'Program Studi A2',
            'fakultas_id' => $fakultasA->id,
        ]);
        $prodiB = Prodi::create([
            'kode_prodi' => 'B1',
            'nama_prodi' => 'Program Studi B',
            'fakultas_id' => $fakultasB->id,
        ]);

        $admin = User::create([
            'username' => 'admin-fakultas-a',
            'name' => 'Admin Fakultas A',
            'password' => Hash::make('password123'),
            'role' => 'admin_fakultas',
            'fakultas_id' => $fakultasA->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('fakultas.prodi-kuesioner.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Pertanyaan/ProdiKuesionerIndex')
            ->where('role', 'admin_fakultas')
            ->where('basePath', '/fakultas/prodi-kuesioner')
            ->has('prodis', 2)
        );

        $this->actingAs($admin)
            ->post(route('fakultas.prodi-kuesioner.sections.store'), [
                'prodi_id' => $prodiA->id,
                'title' => 'Section Fakultas A',
                'order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kuesioner_section', [
            'prodi_id' => $prodiA->id,
            'judul' => 'Section Fakultas A',
        ]);

        $this->actingAs($admin)
            ->post(route('fakultas.prodi-kuesioner.sections.store'), [
                'prodi_id' => $prodiB->id,
                'title' => 'Section Di Luar Fakultas',
                'order' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('kuesioner_section', [
            'prodi_id' => $prodiB->id,
            'judul' => 'Section Di Luar Fakultas',
        ]);
    }

    public function test_admin_prodi_can_manage_only_its_own_prodi(): void
    {
        $fakultas = RefFakultas::create([
            'kode_fakultas' => 'FA',
            'nama_fakultas' => 'Fakultas A',
        ]);
        $prodi = Prodi::create([
            'kode_prodi' => 'A1',
            'nama_prodi' => 'Program Studi A',
            'fakultas_id' => $fakultas->id,
        ]);
        $prodiLain = Prodi::create([
            'kode_prodi' => 'A2',
            'nama_prodi' => 'Program Studi A2',
            'fakultas_id' => $fakultas->id,
        ]);

        $admin = User::create([
            'username' => 'admin-prodi-a',
            'name' => 'Admin Prodi A',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $prodi->id,
            'must_change_password' => false,
        ]);
        $sectionLain = KuesionerSection::create([
            'kode_section' => 'SEC_TEST_A2',
            'target' => 'prodi',
            'prodi_id' => $prodiLain->id,
            'judul' => 'Section Prodi Lain',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('prodi.kuesioner.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('role', 'admin_prodi')
            ->where('basePath', '/prodi/kuesioner')
            ->has('prodis', 1)
            ->where('prodis.0.id', $prodi->id)
        );

        $this->actingAs($admin)
            ->get(route('prodi.pertanyaan.index'))
            ->assertRedirect(route('prodi.kuesioner.index'));

        $this->actingAs($admin)
            ->delete(route('prodi.kuesioner.sections.destroy', $sectionLain->id))
            ->assertForbidden();

        $this->assertDatabaseHas('kuesioner_section', [
            'id' => $sectionLain->id,
        ]);
    }

    /**
     * Memastikan pertanyaan pinjaman hanya dapat dipakai atau dilepas dari instrumen lokal.
     */
    public function test_admin_prodi_cannot_edit_borrowed_question_but_can_remove_local_placement(): void
    {
        $fakultas = RefFakultas::create([
            'kode_fakultas' => 'FA',
            'nama_fakultas' => 'Fakultas A',
        ]);
        $prodiPemilik = Prodi::create([
            'kode_prodi' => 'OWN',
            'nama_prodi' => 'Prodi Pemilik',
            'fakultas_id' => $fakultas->id,
        ]);
        $prodiPengguna = Prodi::create([
            'kode_prodi' => 'USE',
            'nama_prodi' => 'Prodi Pengguna',
            'fakultas_id' => $fakultas->id,
        ]);
        $admin = User::create([
            'username' => 'admin-prodi-use',
            'name' => 'Admin Prodi Pengguna',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'prodi_id' => $prodiPengguna->id,
            'must_change_password' => false,
        ]);
        $sectionPemilik = KuesionerSection::create([
            'kode_section' => 'SEC_OWN',
            'target' => 'prodi',
            'prodi_id' => $prodiPemilik->id,
            'judul' => 'Section Pemilik',
            'order' => 1,
            'is_active' => true,
        ]);
        $sectionPengguna = KuesionerSection::create([
            'kode_section' => 'SEC_USE',
            'target' => 'prodi',
            'prodi_id' => $prodiPengguna->id,
            'judul' => 'Section Pengguna',
            'order' => 1,
            'is_active' => true,
        ]);
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'OWN-01',
            'teks_pertanyaan' => 'Pertanyaan milik prodi pemilik',
            'tipe' => 'text',
            'pemilik_prodi_id' => $prodiPemilik->id,
            'is_reusable' => true,
            'is_active' => true,
        ]);
        $ownerPlacement = KuesionerSectionPertanyaan::create([
            'section_id' => $sectionPemilik->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => true,
        ]);
        $borrowedPlacement = KuesionerSectionPertanyaan::create([
            'section_id' => $sectionPengguna->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('prodi.kuesioner.index'))
            ->assertInertia(fn ($page) => $page
                ->where('questions.0.id', $question->id)
                ->where('questions.0.placement_id', $borrowedPlacement->id)
                ->where('questions.0.can_edit', false)
                ->where('questions.0.is_placement_active', true)
            );

        $this->actingAs($admin)
            ->post(route('prodi.kuesioner.penempatan.toggle-active', $borrowedPlacement->id), [
                'prodi_id' => $prodiPengguna->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'id' => $borrowedPlacement->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'id' => $ownerPlacement->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('prodi.kuesioner.pertanyaan.update', $question->id), [
                'prodi_id' => $prodiPengguna->id,
                'code' => 'OWN-UPDATED',
                'question_text' => 'Tidak boleh diubah oleh prodi pengguna',
                'type' => 'text',
                'is_required' => true,
                'is_reusable' => true,
                'prodi_question_section_id' => $sectionPengguna->id,
                'order' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'id' => $question->id,
            'kode_pertanyaan' => 'OWN-01',
            'teks_pertanyaan' => 'Pertanyaan milik prodi pemilik',
        ]);

        $this->actingAs($admin)
            ->delete(route('prodi.kuesioner.pertanyaan.destroy', $borrowedPlacement->id), [
                'prodi_id' => $prodiPengguna->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'id' => $ownerPlacement->id,
            'section_id' => $sectionPemilik->id,
            'pertanyaan_id' => $question->id,
        ]);
        $this->assertDatabaseMissing('kuesioner_section_pertanyaan', [
            'id' => $borrowedPlacement->id,
        ]);
        $this->assertDatabaseHas('kuesioner_pertanyaan', [
            'id' => $question->id,
        ]);
    }
}
