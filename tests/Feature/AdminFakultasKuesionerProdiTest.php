<?php

namespace Tests\Feature;

use App\Models\Prodi;
use App\Models\ProdiQuestionSection;
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

        $this->assertDatabaseHas('prodi_question_section', [
            'prodi_id' => $prodiA->id,
            'title' => 'Section Fakultas A',
        ]);

        $this->actingAs($admin)
            ->post(route('fakultas.prodi-kuesioner.sections.store'), [
                'prodi_id' => $prodiB->id,
                'title' => 'Section Di Luar Fakultas',
                'order' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('prodi_question_section', [
            'prodi_id' => $prodiB->id,
            'title' => 'Section Di Luar Fakultas',
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
        $sectionLain = ProdiQuestionSection::create([
            'prodi_id' => $prodiLain->id,
            'title' => 'Section Prodi Lain',
            'order' => 1,
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
            ->delete(route('prodi.kuesioner.sections.destroy', $sectionLain->id))
            ->assertForbidden();

        $this->assertDatabaseHas('prodi_question_section', [
            'id' => $sectionLain->id,
        ]);
    }
}
