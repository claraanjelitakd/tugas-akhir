<?php

namespace Tests\Feature;

use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\Prodi;
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

        $section = KuesionerSection::create([
            'prodi_id' => $prodi->id,
            'target' => 'prodi',
            'kode_section' => 'SEC-PSI-1',
            'judul' => 'Pengalaman Belajar Selama Studi',
            'order' => 1,
            'is_active' => true,
        ]);

        $question = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $prodi->id,
            'kode_pertanyaan' => 'PSI-2-01',
            'teks_pertanyaan' => 'Pemahaman konsep dan praktik Sistem Informasi',
            'tipe' => 'rating_5',
            'is_active' => true,
        ]);

        $section->pertanyaan()->attach($question->id, ['order' => 1, 'wajib' => true]);

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
     * Bagian pertama menjadi pilihan default ketika filter section belum dipilih.
     */
    public function test_first_section_is_selected_by_default(): void
    {
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
        $secondSection = KuesionerSection::create([
            'prodi_id' => $prodi->id,
            'target' => 'prodi',
            'kode_section' => 'SEC-PSI-2',
            'judul' => 'Bagian Kedua',
            'order' => 2,
            'is_active' => true,
        ]);
        $firstSection = KuesionerSection::create([
            'prodi_id' => $prodi->id,
            'target' => 'prodi',
            'kode_section' => 'SEC-PSI-1',
            'judul' => 'Bagian Pertama',
            'order' => 1,
            'is_active' => true,
        ]);
        $q2 = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $prodi->id,
            'kode_pertanyaan' => 'PSI-2-01',
            'teks_pertanyaan' => 'Pertanyaan bagian kedua',
            'tipe' => 'rating_5',
            'is_active' => true,
        ]);
        $q1 = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $prodi->id,
            'kode_pertanyaan' => 'PSI-1-01',
            'teks_pertanyaan' => 'Pertanyaan bagian pertama',
            'tipe' => 'rating_5',
            'is_active' => true,
        ]);

        $secondSection->pertanyaan()->attach($q2->id, ['order' => 1, 'wajib' => true]);
        $firstSection->pertanyaan()->attach($q1->id, ['order' => 1, 'wajib' => true]);

        $analytics = app(ProdiAnalyticsService::class)->buildProdiChart($prodi->id, null, 'ALL');

        $this->assertSame($firstSection->id, $analytics['filters']['selected_section_id']);
        $this->assertSame('Bagian Pertama', $analytics['chart']['title']);
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

        $section = KuesionerSection::create([
            'prodi_id' => $prodi->id,
            'target' => 'prodi',
            'kode_section' => 'SEC-PSI-1',
            'judul' => 'Pengalaman Belajar',
            'order' => 1,
            'is_active' => true,
        ]);

        $question = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $prodi->id,
            'kode_pertanyaan' => 'PSI-2-01',
            'teks_pertanyaan' => 'Pemahaman konsep',
            'tipe' => 'rating_5',
            'is_active' => true,
        ]);

        $section->pertanyaan()->attach($question->id, ['order' => 1, 'wajib' => true]);

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
        $section = KuesionerSection::create([
            'prodi_id' => $prodi->id,
            'target' => 'prodi',
            'kode_section' => 'SEC-PSI-1',
            'judul' => 'Pengalaman Belajar',
            'order' => 1,
            'is_active' => true,
        ]);
        $question = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $prodi->id,
            'kode_pertanyaan' => 'PSI-2-01',
            'teks_pertanyaan' => 'Pemahaman konsep',
            'tipe' => 'rating_5',
            'is_active' => true,
        ]);

        $section->pertanyaan()->attach($question->id, ['order' => 1, 'wajib' => true]);

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
            KuesionerJawaban::create([
                'responden_type' => 'alumni',
                'biodata_id' => $biodata->id,
                'section_id' => $section->id,
                'pertanyaan_id' => $question->id,
                'kode_pertanyaan' => $question->kode_pertanyaan,
                'jawaban_teks' => $response['score'],
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
        $section = KuesionerSection::create([
            'prodi_id' => $prodi->id,
            'target' => 'prodi',
            'kode_section' => 'SEC-PSI-1',
            'judul' => 'Kontribusi Alumni',
            'order' => 1,
            'is_active' => true,
        ]);
        $question = KuesionerPertanyaan::create([
            'pemilik_prodi_id' => $prodi->id,
            'kode_pertanyaan' => 'PSI-1-04',
            'teks_pertanyaan' => 'Kontribusi yang akan diberikan',
            'tipe' => 'multiple_choice',
            'is_active' => true,
        ]);

        $section->pertanyaan()->attach($question->id, ['order' => 1, 'wajib' => true]);

        KuesionerOpsi::create(['pertanyaan_id' => $question->id, 'kode_opsi' => 'PSI-1-04-01', 'label' => 'Kuliah Umum', 'order' => 1]);
        KuesionerOpsi::create(['pertanyaan_id' => $question->id, 'kode_opsi' => 'PSI-1-04-02', 'label' => 'Other', 'order' => 2]);
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
        KuesionerJawaban::create([
            'responden_type' => 'alumni',
            'biodata_id' => $biodata->id,
            'section_id' => $section->id,
            'pertanyaan_id' => $question->id,
            'kode_pertanyaan' => $question->kode_pertanyaan,
            'jawaban_teks' => 'Other (Menjadi mentor proyek)',
            'jawaban_json' => ['selected' => ['Other'], 'custom' => 'Menjadi mentor proyek'],
        ]);

        $analytics = app(ProdiAnalyticsService::class)->buildProdiChart($prodi->id, $section->id, 'ALL');

        $this->assertSame('Lainnya', $analytics['chart']['visualizations'][0]['stat']['categories'][1]['label']);
        $this->assertSame('Lainnya', $analytics['chart']['records'][0]['jawaban_label']);
        $this->assertSame('Menjadi mentor proyek', $analytics['chart']['records'][0]['jawaban_lainnya']);
        $this->assertSame('081234567890', $analytics['chart']['records'][0]['nomor_telepon']);
        $this->assertSame('pribadi.lainnya@example.test', $analytics['chart']['records'][0]['email']);
    }
}
