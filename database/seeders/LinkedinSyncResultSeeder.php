<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\LinkedinSyncResult;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Class LinkedinSyncResultSeeder
 *
 * Menjamin preservasi data scraping LinkedIn (hasil staging Apify/mock):
 * - Membaca dan memulihkan snapshot cadangan dari `storage/app/backup/linkedin_sync_results_backup.json`
 * - Memastikan data mock pengujian utama (Jovan Yulianto, Argo Wibowo, dll.) selalu tersedia setelah `migrate:fresh --seed`
 * - Menautkan secara tangguh berbasis NIM alumni agar ID auto-increment tidak merusak relasi.
 */
class LinkedinSyncResultSeeder extends Seeder
{
    /**
     * Lokasi direktori dan berkas cadangan data scraping LinkedIn.
     */
    public const BACKUP_DIR = 'storage/app/backup';

    public const BACKUP_FILE = 'storage/app/backup/linkedin_sync_results_backup.json';

    /**
     * Jalankan proses seeding preservasi data scraping LinkedIn.
     */
    public function run(): void
    {
        $backupPath = base_path(self::BACKUP_FILE);
        $restoredCount = 0;

        // 1. Pulihkan dari berkas cadangan jika tersedia
        if (File::exists($backupPath)) {
            $content = File::get($backupPath);
            $backupItems = json_decode($content, true);

            if (is_array($backupItems)) {
                foreach ($backupItems as $item) {
                    $nim = $item['nim'] ?? null;
                    $biodata = null;

                    if ($nim) {
                        $biodata = Biodata::where('nim', $nim)->first();
                    }

                    if (! $biodata && ! empty($item['biodata_id'])) {
                        $biodata = Biodata::find($item['biodata_id']);
                    }

                    if (! $biodata) {
                        continue;
                    }

                    // Sinkronkan linkedin_url pada biodata alumni jika belum ada
                    if (empty($biodata->linkedin_url) && ! empty($item['linkedin_url'])) {
                        $biodata->linkedin_url = $item['linkedin_url'];
                        $biodata->saveQuietly();
                    }

                    LinkedinSyncResult::updateOrCreate(
                        [
                            'biodata_id' => $biodata->id,
                            'linkedin_url' => $item['linkedin_url'],
                        ],
                        [
                            'linkedin_username' => $item['linkedin_username'] ?? null,
                            'scraped_data' => $item['scraped_data'] ?? [],
                            'status' => $item['status'] ?? 'pending',
                            'reviewed_by' => $item['reviewed_by'] ?? null,
                            'reviewed_at' => ! empty($item['reviewed_at']) ? Carbon::parse($item['reviewed_at']) : null,
                            'scraped_at' => ! empty($item['scraped_at']) ? Carbon::parse($item['scraped_at']) : now(),
                        ]
                    );

                    $restoredCount++;
                }
            }
        }

        // 2. Pastikan fixture kanonik utama selalu terisi untuk pengujian (Jovan Yulianto & Argo Wibowo)
        $this->ensureCanonicalFixtures();

        // 3. Cadangkan kembali kondisi snapshot terkini ke disk
        self::backupSyncResults();
    }

    /**
     * Memastikan data fixture mock pengujian kunci selalu terhubung ke alumni terkait.
     */
    private function ensureCanonicalFixtures(): void
    {
        // A. Jovan Yulianto (Sistem Informasi - NIM 72220002)
        $jovanBiodata = Biodata::where('nim', '72220002')->first()
            ?? Biodata::whereHas('prodi', fn ($q) => $q->where('kode_prodi', '72'))->first();

        if ($jovanBiodata) {
            $jovanMockFile = storage_path('app/mock/apify/jovanyulianto.json');
            if (! File::exists($jovanMockFile)) {
                $jovanMockFile = storage_path('app/mock/apify/jovanyulianto-mock.json');
            }

            if (File::exists($jovanMockFile)) {
                $rawJovan = json_decode(File::get($jovanMockFile), true);

                $jovanBiodata->linkedin_url = 'https://www.linkedin.com/in/jovanyulianto-mock';
                $jovanBiodata->saveQuietly();

                LinkedinSyncResult::updateOrCreate(
                    [
                        'biodata_id' => $jovanBiodata->id,
                        'linkedin_url' => 'https://www.linkedin.com/in/jovanyulianto-mock',
                    ],
                    [
                        'linkedin_username' => 'jovanyulianto',
                        'scraped_data' => $rawJovan,
                        'status' => 'pending',
                        'scraped_at' => now(),
                    ]
                );
            }
        }

        // B. Argo Wibowo (Informatika - NIM 71170001 atau Biodata 1)
        $argoBiodata = Biodata::where('nim', '71170001')->first() ?? Biodata::find(1);

        if ($argoBiodata) {
            $argoMockFile = storage_path('app/mock/apify/argo-wibowo-99180935.json');
            if (! File::exists($argoMockFile)) {
                $argoMockFile = storage_path('app/mock/apify/argo-wibowo-99180935-mock.json');
            }

            if (File::exists($argoMockFile)) {
                $rawArgo = json_decode(File::get($argoMockFile), true);

                $argoBiodata->linkedin_url = 'https://www.linkedin.com/in/argo-wibowo-99180935/';
                $argoBiodata->saveQuietly();

                LinkedinSyncResult::updateOrCreate(
                    [
                        'biodata_id' => $argoBiodata->id,
                        'linkedin_url' => 'https://www.linkedin.com/in/argo-wibowo-99180935/',
                    ],
                    [
                        'linkedin_username' => 'argo-wibowo-99180935',
                        'scraped_data' => $rawArgo,
                        'status' => 'pending',
                        'scraped_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Mencadangkan seluruh data `linkedin_sync_results` aktif ke berkas JSON cadangan di disk.
     * Metode publik statis ini dapat dipanggil otomatis setiap kali ada proses scraping baru.
     */
    public static function backupSyncResults(): void
    {
        try {
            $backupDir = base_path(self::BACKUP_DIR);
            if (! File::isDirectory($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }

            $results = LinkedinSyncResult::with('biodata')->orderBy('id')->get();
            $backupData = [];

            foreach ($results as $res) {
                $backupData[] = [
                    'nim' => $res->biodata?->nim,
                    'biodata_id' => $res->biodata_id,
                    'linkedin_url' => $res->linkedin_url,
                    'linkedin_username' => $res->linkedin_username,
                    'scraped_data' => $res->scraped_data,
                    'status' => $res->status,
                    'reviewed_by' => $res->reviewed_by,
                    'reviewed_at' => $res->reviewed_at?->toIso8601String(),
                    'scraped_at' => $res->scraped_at?->toIso8601String(),
                ];
            }

            File::put(base_path(self::BACKUP_FILE), json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('Gagal mencadangkan data linkedin_sync_results: '.$e->getMessage());
        }
    }
}
