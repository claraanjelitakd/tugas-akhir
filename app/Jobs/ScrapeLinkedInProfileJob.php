<?php

namespace App\Jobs;

use App\Models\Biodata;
use App\Models\LinkedinSyncResult;
use App\Models\LogActivity;
use App\Services\LinkedIn\Exceptions\LinkedInProfileNotFoundException;
use App\Services\LinkedIn\Exceptions\LinkedInSyncException;
use App\Services\LinkedIn\Mappers\LinkedInProfileMapper;
use App\Services\LinkedIn\Providers\ApifyLinkedInProvider;
use App\Services\LinkedIn\Providers\MockApifyLinkedInProvider;
use Database\Seeders\LinkedinSyncResultSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ScrapeLinkedInProfileJob
 *
 * Job antrean untuk mengeksekusi penarikan data profil LinkedIn publik alumni via Apify Actor
 * di latar belakang. Mencegah web browser membeku atau mengalami HTTP 504 Gateway Timeout
 * saat permintaan scraping eksternal membutuhkan waktu 30–75 detik.
 */
class ScrapeLinkedInProfileJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    /**
     * Inisialisasi job penarikan profil LinkedIn.
     *
     * @param  int  $biodataId  ID Biodata alumni target
     * @param  string  $url  URL profil LinkedIn publik yang valid
     * @param  int|null  $initiatorUserId  ID Pengguna (Admin) yang memicu sinkronisasi
     */
    public function __construct(
        public int $biodataId,
        public string $url,
        public ?int $initiatorUserId = null
    ) {}

    /**
     * Eksekusi pekerjaan penarikan profil LinkedIn di antrean latar belakang.
     */
    public function handle(LinkedInProfileMapper $mapper): void
    {
        $biodata = Biodata::with(['perusahaan', 'dataAkademik'])->find($this->biodataId);

        if (! $biodata) {
            Log::warning("ScrapeLinkedInProfileJob: Biodata ID {$this->biodataId} tidak ditemukan. Job dibatalkan.");

            return;
        }

        $provider = config('services.linkedin.provider', 'official');

        Log::info("ScrapeLinkedInProfileJob: Memulai penarikan profil untuk alumni {$biodata->nama} ({$biodata->nim}) via provider [{$provider}].");

        try {
            /** @var ApifyLinkedInProvider|MockApifyLinkedInProvider $apify */
            $apify = $provider === 'mock_apify'
                ? app(MockApifyLinkedInProvider::class)
                : app(ApifyLinkedInProvider::class);

            $scrapedData = $apify->scrapeProfile($this->url);

            // Simpan hasil scraping ke staging tabel linkedin_sync_results dengan status pending
            $syncResult = LinkedinSyncResult::create([
                'biodata_id' => $biodata->id,
                'linkedin_url' => $this->url,
                'linkedin_username' => $biodata->linkedin_username,
                'scraped_data' => $scrapedData,
                'status' => 'pending',
                'scraped_at' => now(),
            ]);

            $preview = $syncResult->getPreviewData();
            $companyResolution = null;

            if (filled($preview['current_company'] ?? null)) {
                $companyResolution = $mapper->resolveCompany(
                    (string) $preview['current_company'],
                    $preview['current_company_location'] ?? null,
                    $biodata,
                );

                if (
                    $companyResolution['status'] === 'matched'
                    && $biodata->perusahaan_id
                    && $biodata->perusahaan_id !== $companyResolution['company']->id
                ) {
                    $companyResolution['status'] = 'needs_review';
                }
            }

            // Catat log audit aktivitas
            LogActivity::record(
                action: 'linkedin_sync_apify',
                description: "Pengambilan data profil LinkedIn via antrean berhasil untuk alumni {$biodata->nama} ({$biodata->nim}). Menunggu tinjauan.",
                subject: $biodata,
                oldValues: null,
                newValues: [
                    'sync_result_id' => $syncResult->id,
                    'status' => 'pending',
                    'provider' => $provider,
                    'company_resolution' => $companyResolution ? [
                        'company_id' => $companyResolution['company']->id,
                        'status' => $companyResolution['status'],
                        'match_type' => $companyResolution['match_type'],
                        'similarity_score' => $companyResolution['similarity_score'],
                    ] : null,
                ],
                model: 'Biodata',
                modelId: $biodata->id,
            );

            // Cadangkan snapshot ke disk
            if (class_exists(LinkedinSyncResultSeeder::class)) {
                LinkedinSyncResultSeeder::backupSyncResults();
            }

            Log::info("ScrapeLinkedInProfileJob: Profil LinkedIn untuk NIM {$biodata->nim} berhasil disimpan ke staging (ID Sync: {$syncResult->id}).");

        } catch (LinkedInProfileNotFoundException $e) {
            Log::warning("ScrapeLinkedInProfileJob: Profil tidak ditemukan untuk NIM {$biodata->nim}: {$e->getMessage()}");
            LogActivity::record(
                action: 'linkedin_sync_apify_failed',
                description: "Penarikan LinkedIn gagal: profil tidak ditemukan untuk alumni {$biodata->nama} ({$biodata->nim}).",
                subject: $biodata,
                model: 'Biodata',
                modelId: $biodata->id,
            );
            throw $e;
        } catch (LinkedInSyncException|Throwable $e) {
            Log::error("ScrapeLinkedInProfileJob: Gagal mengekstrak profil untuk NIM {$biodata->nim}: {$e->getMessage()}");
            LogActivity::record(
                action: 'linkedin_sync_apify_failed',
                description: "Penarikan LinkedIn gagal untuk alumni {$biodata->nama} ({$biodata->nim}): {$e->getMessage()}",
                subject: $biodata,
                model: 'Biodata',
                modelId: $biodata->id,
            );
            throw $e;
        }
    }
}
