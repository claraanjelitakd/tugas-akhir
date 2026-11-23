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
            'kode_fakultas' => $fakultasA->kode_fakultas,
        ]);
        Prodi::create([
            'kode_prodi' => 'A2',
            'nama_prodi' => 'Program Studi A2',
            'kode_fakultas' => $fakultasA->kode_fakultas,
        ]);
        $prodiB = Prodi::create([
            'kode_prodi' => 'B1',
            'nama_prodi' => 'Program Studi B',
            'kode_fakultas' => $fakultasB->kode_fakultas,
        ]);

        $admin = User::create([
            'username' => 'admin-fakultas-a',
            'name' => 'Admin Fakultas A',
            'password' => Hash::make('password123'),
            'role' => 'admin_fakultas',
            'kode_fakultas' => $fakultasA->kode_fakultas,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('fakultas.prodi-kuesioner.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Shared/Kuesioner/ProdiKuesionerIndex')
            ->where('role', 'admin_fakultas')
            ->where('basePath', '/fakultas/prodi-kuesioner')
            ->has('prodis', 2)
        );

        $this->actingAs($admin)
            ->post(route('fakultas.prodi-kuesioner.sections.store'), [
                'kode_prodi' => $prodiA->kode_prodi,
                'title' => 'Section Fakultas A',
                'order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kuesioner_section', [
            'kode_prodi' => $prodiA->kode_prodi,
            'judul' => 'Section Fakultas A',
        ]);

        $this->actingAs($admin)
            ->post(route('fakultas.prodi-kuesioner.sections.store'), [
                'kode_prodi' => $prodiB->kode_prodi,
                'title' => 'Section Di Luar Fakultas',
                'order' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('kuesioner_section', [
            'kode_prodi' => $prodiB->kode_prodi,
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
            'kode_fakultas' => $fakultas->kode_fakultas,
        ]);
        $prodiLain = Prodi::create([
            'kode_prodi' => 'A2',
            'nama_prodi' => 'Program Studi A2',
            'kode_fakultas' => $fakultas->kode_fakultas,
        ]);

        $admin = User::create([
            'username' => 'admin-prodi-a',
            'name' => 'Admin Prodi A',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'kode_prodi' => $prodi->kode_prodi,
            'must_change_password' => false,
        ]);
        $sectionLain = KuesionerSection::create([
            'kode_section' => 'SEC_TEST_A2',
            'target' => 'prodi',
            'kode_prodi' => $prodiLain->kode_prodi,
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
            ->where('prodis.0.kode_prodi', $prodi->kode_prodi)
        );

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
            'kode_fakultas' => $fakultas->kode_fakultas,
        ]);
        $prodiPengguna = Prodi::create([
            'kode_prodi' => 'USE',
            'nama_prodi' => 'Prodi Pengguna',
            'kode_fakultas' => $fakultas->kode_fakultas,
        ]);
        $admin = User::create([
            'username' => 'admin-prodi-use',
            'name' => 'Admin Prodi Pengguna',
            'password' => Hash::make('password123'),
            'role' => 'admin_prodi',
            'kode_prodi' => $prodiPengguna->kode_prodi,
            'must_change_password' => false,
        ]);
        $sectionPemilik = KuesionerSection::create([
            'kode_section' => 'SEC_OWN',
            'target' => 'prodi',
            'kode_prodi' => $prodiPemilik->kode_prodi,
            'judul' => 'Section Pemilik',
            'order' => 1,
            'is_active' => true,
        ]);
        $sectionPengguna = KuesionerSection::create([
            'kode_section' => 'SEC_USE',
            'target' => 'prodi',
            'kode_prodi' => $prodiPengguna->kode_prodi,
            'judul' => 'Section Pengguna',
            'order' => 1,
            'is_active' => true,
        ]);
        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'OWN-01',
            'teks_pertanyaan' => 'Pertanyaan milik prodi pemilik',
            'tipe' => 'text',
            'pemilik_kode_prodi' => $prodiPemilik->kode_prodi,
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
                'kode_prodi' => $prodiPengguna->kode_prodi,
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
                'kode_prodi' => $prodiPengguna->kode_prodi,
                'code' => 'OWN-UPDATED',
                'question_text' => 'Tidak boleh diubah oleh prodi pengguna',
                'type' => 'text',
                'is_required' => true,
                'is_reusable' => true,
                'section_id' => $sectionPengguna->id,
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
                'kode_prodi' => $prodiPengguna->kode_prodi,
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

    /**
     * Memastikan section kuesioner prodi dilarang dihapus jika masih memuat butir pertanyaan.
     */
    public function test_admin_cannot_delete_section_when_it_contains_questions(): void
    {
        $fakultas = RefFakultas::create([
            'kode_fakultas' => 'FTI',
            'nama_fakultas' => 'Fakultas Teknologi Informasi',
        ]);
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
            'kode_fakultas' => $fakultas->kode_fakultas,
        ]);
        $admin = User::create([
            'name' => 'Admin SI',
            'username' => 'admin_si_sec',
            'email' => 'admin_si_sec@ukdw.ac.id',
            'password' => Hash::make('password'),
            'role' => 'admin_prodi',
            'kode_prodi' => $prodi->kode_prodi,
        ]);

        $section = KuesionerSection::create([
            'target' => 'prodi',
            'kode_prodi' => $prodi->kode_prodi,
            'kode_section' => 'PRODI_72_SEC1',
            'judul' => 'Bagian Relevansi',
            'order' => 1,
            'is_active' => true,
        ]);

        $question = KuesionerPertanyaan::create([
            'kode_pertanyaan' => 'PRODI-72-Q1',
            'teks_pertanyaan' => 'Pertanyaan di dalam section',
            'tipe' => 'text',
            'pemilik_kode_prodi' => $prodi->kode_prodi,
            'is_reusable' => true,
            'is_active' => true,
        ]);

        KuesionerSectionPertanyaan::create([
            'section_id' => $section->id,
            'pertanyaan_id' => $question->id,
            'order' => 1,
            'wajib' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('prodi.kuesioner.sections.destroy', $section->id));

        $response->assertSessionHasErrors(['message']);
        $this->assertDatabaseHas('kuesioner_section', [
            'id' => $section->id,
        ]);
        $this->assertDatabaseHas('kuesioner_section_pertanyaan', [
            'section_id' => $section->id,
            'pertanyaan_id' => $question->id,
        ]);
    }

    /**
     * Memastikan section kuesioner prodi yang kosong (tanpa pertanyaan) dapat dihapus.
     */
    public function test_admin_can_delete_empty_section(): void
    {
        $fakultas = RefFakultas::create([
            'kode_fakultas' => 'FTI2',
            'nama_fakultas' => 'Fakultas Teknologi Informasi 2',
        ]);
        $prodi = Prodi::create([
            'kode_prodi' => '71',
            'nama_prodi' => 'Informatika',
            'kode_fakultas' => $fakultas->kode_fakultas,
        ]);
        $admin = User::create([
            'name' => 'Admin Inf',
            'username' => 'admin_inf_sec',
            'email' => 'admin_inf_sec@ukdw.ac.id',
            'password' => Hash::make('password'),
            'role' => 'admin_prodi',
            'kode_prodi' => $prodi->kode_prodi,
        ]);

        $section = KuesionerSection::create([
            'target' => 'prodi',
            'kode_prodi' => $prodi->kode_prodi,
            'kode_section' => 'PRODI_71_SEC1',
            'judul' => 'Bagian Kosong',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('prodi.kuesioner.sections.destroy', $section->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('kuesioner_section', [
            'id' => $section->id,
        ]);
    }
}
