<?php

namespace Tests\Feature;

use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\EvaluasiAtasan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\PertanyaanEvaluasiAtasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Memastikan halaman grafik evaluasi atasan membaca data database dan
 * menghitung ringkasan tanpa membuat data sintetis di frontend.
 */
class SuperAdminAtasanAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tamu tidak boleh membuka halaman maupun endpoint data evaluasi atasan.
     */
    public function test_guest_is_redirected_from_supervisor_analytics(): void
    {
        $this->get('/superadmin/analitik-atasan')->assertRedirect('/login');
        $this->getJson('/superadmin/analitik-atasan/data')->assertUnauthorized();
    }

    /**
     * Superadmin menerima payload grafik walaupun belum ada evaluasi terkirim.
     */
    public function test_superadmin_can_render_empty_supervisor_analytics(): void
    {
        $admin = $this->createSuperadmin('supervisor-analytics-empty');
        $this->seed(PertanyaanEvaluasiAtasanSeeder::class);

        $this->actingAs($admin)
            ->get('/superadmin/analitik-atasan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Analytics/GrafikAtasan')
                ->has('analytics.filters')
                ->has('analytics.question_stats', 30)
                ->where('analytics.summary.population', 0)
                ->where('analytics.has_data', false));
    }

    /**
     * Skor Likert dan filter tahun/prodi dihitung dari respons yang tersimpan.
     */
    public function test_supervisor_analytics_calculates_scores_from_submitted_responses(): void
    {
        $admin = $this->createSuperadmin('supervisor-analytics-data');
        $this->seed(PertanyaanEvaluasiAtasanSeeder::class);
        $prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);
        $biodata = $this->createAlumni($prodi, '722026001', 2026);
        $perusahaan = Perusahaan::create(['nama_perusahaan' => 'PT Data Nusantara']);
        $atasan = Atasan::create([
            'perusahaan_id' => $perusahaan->id,
            'nama' => 'Rina Supervisor',
            'email' => 'rina@example.test',
        ]);
        $evaluasi = EvaluasiAtasan::create([
            'biodata_id' => $biodata->id,
            'perusahaan_id' => $perusahaan->id,
            'atasan_id' => $atasan->id,
            'token' => Str::random(40),
            'is_submitted' => true,
            'submitted_at' => now(),
        ]);

        $integritas = KuesionerPertanyaan::where('kode_pertanyaan', 'EVA_integritas')->firstOrFail();
        $komunikasi = KuesionerPertanyaan::where('kode_pertanyaan', 'EVA_komunikasi')->firstOrFail();
        $sectionId = $integritas->sections()->first()?->id ?? 1;

        KuesionerJawaban::create([
            'responden_type' => 'atasan',
            'evaluasi_atasan_id' => $evaluasi->id,
            'biodata_id' => $biodata->id,
            'section_id' => $sectionId,
            'pertanyaan_id' => $integritas->id,
            'kode_pertanyaan' => 'EVA_integritas',
            'jawaban_teks' => 'Sangat Tinggi',
        ]);
        KuesionerJawaban::create([
            'responden_type' => 'atasan',
            'evaluasi_atasan_id' => $evaluasi->id,
            'biodata_id' => $biodata->id,
            'section_id' => $sectionId,
            'pertanyaan_id' => $komunikasi->id,
            'kode_pertanyaan' => 'EVA_komunikasi',
            'jawaban_teks' => 'Cukup',
        ]);

        $response = $this->actingAs($admin)->getJson('/superadmin/analitik-atasan/data?tahun=2026&prodi_id='.$prodi->id);

        $response
            ->assertOk()
            ->assertJsonPath('summary.population', 1)
            ->assertJsonPath('summary.submitted', 1)
            ->assertJsonPath('summary.average_score', 4)
            ->assertJsonPath('question_stats.0.kode', 'tingkat_kesiapan_kerja')
            ->assertJsonPath('records.0.nama_prodi', 'Sistem Informasi')
            ->assertJsonPath('records.0.linkedin_url', 'https://www.linkedin.com/in/alumni-evaluasi')
            ->assertJsonPath('prodi_summary.0.average', 4);
    }

    /**
     * Membuat akun superadmin untuk pengujian halaman yang dilindungi.
     */
    private function createSuperadmin(string $username): User
    {
        return User::create([
            'username' => $username,
            'name' => 'Super Administrator',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'must_change_password' => false,
        ]);
    }

    /**
     * Membuat data akademik dan biodata yang menjadi populasi evaluasi.
     */
    private function createAlumni(Prodi $prodi, string $nim, int $year): Biodata
    {
        $user = User::create([
            'username' => $nim,
            'name' => 'Alumni Evaluasi',
            'password' => Hash::make('password123'),
            'role' => 'alumni',
            'must_change_password' => false,
        ]);
        DataAkademik::create([
            'nim' => $nim,
            'nama' => 'Alumni Evaluasi',
            'tahun_lulus' => $year,
        ]);

        return Biodata::create([
            'user_id' => $user->id,
            'nim' => $nim,
            'prodi_id' => $prodi->id,
            'email_pribadi' => 'alumni@example.test',
            'linkedin_url' => 'https://www.linkedin.com/in/alumni-evaluasi',
        ]);
    }
}
