<?php

namespace Tests\Feature;

use App\Jobs\ScrapeLinkedInProfileJob;
use App\Models\Biodata;
use App\Models\DataAkademik;
use App\Models\LinkedinSyncResult;
use App\Models\LogActivity;
use App\Models\Prodi;
use App\Models\User;
use App\Services\LinkedIn\Mappers\LinkedInProfileMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ScrapeLinkedInProfileJobTest
 *
 * Menguji eksekusi job antrean ScrapeLinkedInProfileJob untuk scraping profil LinkedIn latar belakang.
 */
class ScrapeLinkedInProfileJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Prodi $prodi;

    protected Biodata $biodata;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.linkedin.provider', 'mock_apify');

        $this->prodi = Prodi::create([
            'kode_prodi' => '72',
            'nama_prodi' => 'Sistem Informasi',
        ]);

        $this->user = User::create([
            'username' => '72200003',
            'name' => 'John Doe Alumni',
            'email' => 'johndoe@example.com',
            'role' => 'alumni',
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ]);

        DataAkademik::create([
            'nim' => '72200003',
            'nama' => 'John Doe Alumni',
            'kode_prodi' => $this->prodi->kode_prodi,
            'angkatan_masuk' => '2020',
            'tahun_lulus' => '2024',
            'tahun_akademik_lulus' => 'Genap 2023/2024',
        ]);

        $this->biodata = Biodata::create([
            'user_id' => $this->user->id,
            'nim' => '72200003',
            'kode_prodi' => $this->prodi->kode_prodi,
            'linkedin_url' => 'https://www.linkedin.com/in/argo-wibowo-99180935/',
            'linkedin_username' => 'argo-wibowo-99180935',
            'email_pribadi' => 'johndoe@example.com',
        ]);
    }

    /**
     * Memastikan job mengekstrak profil via mock provider dan menyimpan hasil status pending.
     */
    public function test_job_scrapes_profile_and_creates_pending_sync_result(): void
    {
        $job = new ScrapeLinkedInProfileJob(
            $this->biodata->id,
            'https://www.linkedin.com/in/argo-wibowo-99180935/',
            $this->user->id
        );

        $mapper = app(LinkedInProfileMapper::class);
        $job->handle($mapper);

        $syncResult = LinkedinSyncResult::where('biodata_id', $this->biodata->id)->latest('id')->first();
        $this->assertNotNull($syncResult, 'Hasil sinkronisasi wajib tersimpan di tabel staging.');
        $this->assertEquals('pending', $syncResult->status);
        $this->assertNotEmpty($syncResult->scraped_data);

        $log = LogActivity::where('action', 'linkedin_sync_apify')
            ->where('model_id', $this->biodata->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log, 'Log audit aktivitas linkedin_sync_apify wajib tercatat.');
    }
}
