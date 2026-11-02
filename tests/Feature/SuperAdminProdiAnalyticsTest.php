<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionOption;
use App\Models\ProdiQuestionSection;
use App\Models\ProdiResponse;
use App\Models\User;
use App\Services\Analytics\ProdiAnalyticsService;
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

    /**
     * Skor Likert prodi dibulatkan ke bilangan bulat dan payload menyediakan rumusnya.
     */
    public function test_likert_scores_are_rounded_and_include_a_formula(): void
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

        foreach ([['nim' => '72100001', 'score' => '2'], ['nim' => '72100002', 'score' => '3']] as $index => $response) {
            DataAkademik::create([
                'nim' => $response['nim'],
                'nama' => 'Alumni '.$index,
                'tahun_lulus' => 2025,
            ]);
            $alumni = User::create([
                'username' => 'alumni-prodi-'.$index,
                'name' => 'Alumni '.$index,
                'password' => Hash::make('password123'),
                'role' => 'alumni',
                'must_change_password' => false,
            ]);
            $biodata = Biodata::create([
                'user_id' => $alumni->id,
                'nim' => $response['nim'],
                'prodi_id' => $prodi->id,
            ]);
            ProdiResponse::create([
                'biodata_id' => $biodata->id,
                'prodi_question_id' => $question->id,
                'answer_text' => $response['score'],
            ]);
        }

        $analytics = app(ProdiAnalyticsService::class)->buildProdiChart($prodi->id, $section->id, 'ALL');

        $this->assertSame(3, $analytics['chart']['summary']['average_score']);
        $this->assertSame(3, $analytics['chart']['summary']['median_score']);
        $this->assertSame(3, $analytics['chart']['visualizations'][0]['stat']['average']);
        $this->assertStringContainsString('ROUND', $analytics['chart']['methodology']['formula']);
        $this->assertStringContainsString('bilangan bulat', $analytics['chart']['methodology']['denominator']);
    }

    /**
     * Opsi Other lama dinormalisasi menjadi Lainnya dan isian bebasnya tetap tersedia pada data responden.
     */
    public function test_other_choice_is_normalized_and_preserves_its_custom_answer(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
        $section = ProdiQuestionSection::create([
            'prodi_id' => $prodi->id,
            'title' => 'Kontribusi Alumni',
            'order' => 1,
        ]);
        $question = ProdiQuestion::create([
            'prodi_id' => $prodi->id,
            'prodi_question_section_id' => $section->id,
            'code' => 'PSI-1-04',
            'question_text' => 'Kontribusi yang akan diberikan',
            'type' => 'multiple_choice',
            'order' => 1,
        ]);
        ProdiQuestionOption::create(['prodi_question_id' => $question->id, 'code' => 'PSI-1-04-01', 'option_text' => 'Kuliah Umum', 'order' => 1]);
        ProdiQuestionOption::create(['prodi_question_id' => $question->id, 'code' => 'PSI-1-04-02', 'option_text' => 'Other', 'order' => 2]);
        DataAkademik::create(['nim' => '72100003', 'nama' => 'Alumni Lainnya', 'tahun_lulus' => 2025]);
        $alumni = User::create([
            'username' => 'alumni-prodi-other',
            'name' => 'Alumni Lainnya',
            'email' => 'alumni.lainnya@example.test',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        $biodata = Biodata::create([
            'user_id' => $alumni->id,
            'nim' => '72100003',
            'prodi_id' => $prodi->id,
            'nomor_telepon' => '081234567890',
            'email_pribadi' => 'pribadi.lainnya@example.test',
        ]);
        ProdiResponse::create([
            'biodata_id' => $biodata->id,
            'prodi_question_id' => $question->id,
            'answer_text' => 'Other (Menjadi mentor proyek)',
            'answer_json' => ['selected' => ['Other'], 'custom' => 'Menjadi mentor proyek'],
        ]);

        $analytics = app(ProdiAnalyticsService::class)->buildProdiChart($prodi->id, $section->id, 'ALL');

        $this->assertSame('Lainnya', $analytics['chart']['visualizations'][0]['stat']['categories'][1]['label']);
        $this->assertSame('Lainnya', $analytics['chart']['records'][0]['jawaban_label']);
        $this->assertSame('Menjadi mentor proyek', $analytics['chart']['records'][0]['jawaban_lainnya']);
        $this->assertSame('081234567890', $analytics['chart']['records'][0]['nomor_telepon']);
        $this->assertSame('pribadi.lainnya@example.test', $analytics['chart']['records'][0]['email']);
    }
}
