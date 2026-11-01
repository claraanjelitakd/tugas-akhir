<?php

namespace Tests\Feature;

use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengujian fitur Analitik Grafik Kuesioner Program Studi Superadmin.
 */
class SuperAdminProdiAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::create([
            'username' => 'superadmin-prodi-analytics',
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);
    }

    /**
     * Tamu harus diarahkan ke halaman login.
     */
    public function test_guest_is_redirected_from_prodi_analytics(): void
    {
        $this->get('/superadmin/analitik-prodi')
            ->assertRedirect('/login');

        $this->getJson('/superadmin/analitik-prodi/data')
            ->assertUnauthorized();
    }

    /**
     * Superadmin dapat membuka halaman grafik prodi.
     */
    public function test_superadmin_can_access_prodi_analytics_page(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $section = ProdiQuestionSection::create([
            'prodi_id' => $prodi->id,
            'title' => 'Pengalaman Belajar Selama Studi',
            'order' => 1,
        ]);

        $question = ProdiQuestion::create([
            'prodi_id' => $prodi->id,
            'prodi_question_section_id' => $section->id,
            'code' => 'PSI-2-01',
            'question_text' => 'Pemahaman konsep dan praktik Sistem Informasi',
            'type' => 'rating_5',
            'order' => 1,
        ]);

        $superadmin = $this->createSuperAdmin();

        $response = $this->actingAs($superadmin)->get('/superadmin/analitik-prodi');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Analytics/GrafikProdi')
            ->has('analytics')
            ->where('analytics.prodi.id', $prodi->id)
            ->where('analytics.has_data', true)
        );
    }

    /**
     * Superadmin dapat meminta data JSON analitik prodi asinkron.
     */
    public function test_superadmin_can_fetch_async_prodi_analytics_data(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $section = ProdiQuestionSection::create([
            'prodi_id' => $prodi->id,
            'title' => 'Pengalaman Belajar',
            'order' => 1,
        ]);

        $question = ProdiQuestion::create([
            'prodi_id' => $prodi->id,
            'prodi_question_section_id' => $section->id,
            'code' => 'PSI-2-01',
            'question_text' => 'Pemahaman konsep',
            'type' => 'rating_5',
            'order' => 1,
        ]);

        $superadmin = $this->createSuperAdmin();

        $response = $this->actingAs($superadmin)->getJson("/superadmin/analitik-prodi/data?prodi_id={$prodi->id}");

        $response->assertStatus(200)
            ->assertJsonPath('has_data', true)
            ->assertJsonPath('prodi.id', $prodi->id)
            ->assertJsonStructure([
                'has_data',
                'prodi',
                'filters' => [
                    'available_prodis',
                    'selected_prodi_id',
                    'available_years',
                    'selected_year',
                    'sections',
                    'selected_section_id',
                ],
                'chart' => [
                    'type',
                    'title',
                    'subtitle',
                    'caption',
                    'methodology',
                    'summary',
                    'insights',
                    'series',
                    'visualizations',
                    'pivot',
                    'questions',
                    'records',
                ],
            ]);

        $response->assertJsonPath('chart.visualizations.0.question_id', $question->id)
            ->assertJsonPath('chart.pivot.0.question_id', $question->id)
            ->assertJsonPath('chart.pivot.0.valid_count', 0);
    }
}
