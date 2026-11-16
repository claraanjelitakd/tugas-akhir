<?php

namespace App\Services\LinkedIn\Providers;

use App\Services\LinkedIn\Exceptions\LinkedInProfileNotFoundException;
use App\Services\LinkedIn\Exceptions\LinkedInSyncException;

/**
 * Provider lokal yang meniru kontrak dataset actor Apify.
 *
 * Provider ini tidak mengakses jaringan. Payload dibaca dari berkas JSON pada
 * `storage/app/mock/apify`, kemudian diproses melalui normalizer Apify yang
 * sama agar alur staging, preview, approve, dan reject dapat diuji konsisten.
 */
class MockApifyLinkedInProvider extends ApifyLinkedInProvider
{
    /**
     * Lokasi berkas dataset mock actor Apify.
     */
    private string $mockPath;

    /**
     * Membuat provider mock tanpa memerlukan token atau koneksi HTTP.
     */
    public function __construct(?string $mockPath = null)
    {
        parent::__construct(apiToken: 'mock-apify', actorId: 'mock-apify', timeoutSeconds: 1);
        $configuredPath = $mockPath ?: (string) config('services.apify.mock_path', storage_path('app/mock/apify'));
        $this->mockPath = $this->resolveMockPath($configuredPath);
    }

    /**
     * Membaca dataset actor berdasarkan handle pada URL profil.
     *
     * @return list<array<string, mixed>>
     *
     * @throws LinkedInProfileNotFoundException
     * @throws LinkedInSyncException
     */
    public function scrapeProfile(string $url): array
    {
        if (! self::isValidLinkedInUrl($url)) {
            throw new LinkedInSyncException('Format URL LinkedIn tidak valid untuk dataset mock Apify.');
        }

        $handle = $this->extractHandle($url);
        // Hapus penanda mock di akhiran (-mock) sesuai konvensi pengujian, maupun awalan (mock-)
        $cleanHandle = preg_replace('/[-_]mock$/i', '', $handle);
        $cleanHandle = preg_replace('/^mock[-_]/i', '', $cleanHandle);

        // Cari berkas mock dengan variasi nama (misal: 'jovanyulianto-mock.json' atau 'jovanyulianto.json')
        $candidates = array_unique(array_filter([
            $handle,
            $cleanHandle,
            $cleanHandle.'-mock',
            'mock-'.$cleanHandle,
        ]));

        $filePath = null;
        foreach ($candidates as $cand) {
            $checkPath = rtrim($this->mockPath, '/\\').DIRECTORY_SEPARATOR.$cand.'.json';
            if (is_file($checkPath)) {
                $filePath = $checkPath;
                break;
            }
        }

        if (! $filePath || ! is_file($filePath)) {
            throw new LinkedInProfileNotFoundException($url);
        }

        $content = file_get_contents($filePath);
        $payload = json_decode($content ?: '', true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($payload) || $payload === []) {
            throw new LinkedInSyncException('Dataset mock Apify tidak memiliki format JSON yang valid.');
        }

        $firstItem = $payload[0] ?? null;
        if (! is_array($firstItem)) {
            throw new LinkedInSyncException('Dataset mock Apify harus berupa daftar objek actor.');
        }

        if (($firstItem['entity_type'] ?? null) === 'error' || strtoupper((string) ($firstItem['error_label'] ?? '')) === 'BLOCKED') {
            throw new LinkedInSyncException((string) ($firstItem['message'] ?? 'Dataset mock Apify menandai profil sebagai blocked.'));
        }

        return array_values(array_filter($payload, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * Mengambil vanity handle dari URL profil untuk memilih fixture JSON.
     */
    private function extractHandle(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = explode('/', $path);

        return preg_replace('/[^a-z0-9-]/i', '', (string) end($segments)) ?: 'unknown';
    }

    /**
     * Menjadikan path relatif konsisten baik saat dipanggil dari CLI maupun HTTP.
     *
     * @param  string  $path  Path fixture dari konfigurasi aplikasi.
     * @return string Path absolut fixture mock.
     */
    private function resolveMockPath(string $path): string
    {
        $normalizedPath = trim($path);
        $isAbsolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $normalizedPath) === 1;

        return $isAbsolute ? $normalizedPath : base_path($normalizedPath);
    }
}
