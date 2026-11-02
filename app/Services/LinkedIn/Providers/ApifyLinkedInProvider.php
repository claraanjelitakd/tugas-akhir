<?php

namespace App\Services\LinkedIn\Providers;

use App\Services\LinkedIn\Contracts\LinkedInProfileProvider;
use App\Services\LinkedIn\DTOs\LinkedInPosition;
use App\Services\LinkedIn\DTOs\LinkedInProfile;
use App\Services\LinkedIn\Exceptions\LinkedInProfileNotFoundException;
use App\Services\LinkedIn\Exceptions\LinkedInSyncException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class ApifyLinkedInProvider
 *
 * Provider pihak ketiga (third-party) untuk mengambil data profil LinkedIn publik
 * menggunakan Apify Actor `data_forge_org~linkedin-scraper`.
 *
 * =================================================================================
 * KEBIJAKAN ARSITEKTUR & KEAMANAN:
 * 1. Provider ini hanya aktif ketika konfigurasi LINKEDIN_PROVIDER='apify'.
 * 2. Kredensial API token dibaca eksklusif melalui Laravel config ('services.apify.api_token')
 *    dan DIKIRIMKAN HANYA melalui header HTTP 'Authorization: Bearer {token}'.
 * 3. Token DILARANG KERAS diletakkan pada query string (?token=...), URL, console log,
 *    maupun diekspos ke frontend / respon HTTP.
 * 4. Input scraper HANYA bersumber dari URL profil (biodata.linkedin_url), BUKAN dari nama,
 *    email, username, atau nomor telepon.
 * 5. Cost Safety: 1 klik Super Admin = tepat 1 request per alumni, tanpa bulk sync liar
 *    maupun infinite retry.
 * =================================================================================
 */
class ApifyLinkedInProvider implements LinkedInProfileProvider
{
    public const DEFAULT_ACTOR_ID = 'data_forge_org~linkedin-scraper';

    public const APIFY_BASE_URL = 'https://api.apify.com/v2/actors';

    protected ?string $apiToken;

    protected string $actorId;

    protected int $timeoutSeconds;

    protected HttpFactory $http;

    /**
     * Inisialisasi provider dengan konfigurasi terpusat.
     */
    public function __construct(
        ?string $apiToken = null,
        ?string $actorId = null,
        int $timeoutSeconds = 60,
        ?HttpFactory $http = null,
    ) {
        $this->apiToken = $apiToken ?? config('services.apify.api_token');
        $this->actorId = $actorId ?? config('services.apify.linkedin_actor_id', self::DEFAULT_ACTOR_ID);
        $this->timeoutSeconds = $timeoutSeconds;
        $this->http = $http ?? Http::getFacadeRoot();
    }

    /**
     * Memeriksa apakah URL yang diberikan merupakan URL LinkedIn yang valid.
     */
    public static function isValidLinkedInUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $trimmed = trim($url);

        if (! filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower(parse_url($trimmed, PHP_URL_HOST) ?? '');
        $path = parse_url($trimmed, PHP_URL_PATH) ?? '';

        // Host harus mengandung linkedin.com
        $validHost = str_ends_with($host, 'linkedin.com') || $host === 'linkedin.com';
        if (! $validHost) {
            return false;
        }

        // Harus memiliki path profil (misal: /in/username)
        return ! empty(trim($path, '/'));
    }

    /**
     * Mengambil data mentah (raw dataset) dari Apify Actor berdasarkan URL profil LinkedIn.
     *
     * @param  string  $url  URL profil LinkedIn publik alumni.
     * @return array Array data mentah hasil scraping dari Apify.
     *
     * @throws LinkedInProfileNotFoundException
     * @throws LinkedInSyncException
     */
    public function scrapeProfile(string $url): array
    {
        $cleanUrl = trim($url);

        // 1. Validasi input: URL wajib diisi
        if (empty($cleanUrl)) {
            throw new LinkedInSyncException('URL LinkedIn alumni belum diisi. Provider Apify hanya menerima input dari field linkedin_url.');
        }

        // 2. Validasi format URL LinkedIn
        if (! self::isValidLinkedInUrl($cleanUrl)) {
            throw new LinkedInSyncException("Format URL LinkedIn tidak valid: '{$cleanUrl}'. Harap gunakan URL profil LinkedIn yang valid.");
        }

        // 3. Validasi konfigurasi token Apify di backend (tanpa query string)
        if (empty($this->apiToken)) {
            throw new LinkedInSyncException('APIFY_API_TOKEN belum dikonfigurasi di server. Silakan hubungi Administrator.');
        }

        $endpoint = sprintf('%s/%s/run-sync-get-dataset-items', self::APIFY_BASE_URL, $this->actorId);

        $payload = [
            'profileUrls' => [$cleanUrl],
            'includeProfilePosts' => false,
            'includeComments' => false,
            'includeCompanyPosts' => false,
            'includeCompanyJobs' => false,
            'includeJobDetails' => false,
        ];

        try {
            // Eksekusi pemanggilan HTTP POST sinkron ke Apify
            $response = $this->http
                ->withToken($this->apiToken)
                ->timeout($this->timeoutSeconds)
                ->post($endpoint, $payload);

            $status = $response->status();

            if ($status === 400) {
                Log::warning('Apify Scraper 400 Bad Request');
                throw new LinkedInSyncException('Permintaan ke Apify tidak valid (Bad Request).');
            }

            if ($status === 401) {
                Log::error('Apify Scraper 401 Unauthorized: API token invalid.');
                throw new LinkedInSyncException('Autentikasi Apify gagal. Periksa kembali konfigurasi API token.');
            }

            if ($status === 402) {
                Log::error('Apify Scraper 402 Payment Required: Credit limit reached.');
                throw new LinkedInSyncException('Batas kredit atau kuota Apify telah habis (Payment Required).');
            }

            if ($status === 404) {
                Log::warning('Apify Scraper 404 Not Found: Actor atau URL tidak ditemukan.');
                throw new LinkedInSyncException('Actor Apify atau profil LinkedIn tidak ditemukan (404 Not Found).');
            }

            if ($status === 429) {
                Log::warning('Apify Scraper 429 Rate Limit Exceeded.');
                throw new LinkedInSyncException('Terlalu banyak permintaan ke Apify (Rate Limit Exceeded). Silakan coba beberapa saat lagi.');
            }

            if ($status >= 500) {
                Log::error("Apify Scraper HTTP {$status} Server Error");
                throw new LinkedInSyncException("Server Apify mengalami gangguan atau batas waktu tercapai (HTTP {$status}).");
            }

            if (! $response->successful()) {
                throw new LinkedInSyncException("Gagal mengambil data dari Apify (HTTP {$status}).");
            }

            // Parsing respons JSON mentah
            $data = $response->json();

            if (! is_array($data)) {
                throw new LinkedInSyncException('Format data respons dari Apify tidak valid.');
            }

            // Jika dataset kosong
            if (empty($data)) {
                throw new LinkedInProfileNotFoundException($cleanUrl);
            }

            // Actor dapat mengembalikan HTTP 200 tetapi menaruh kegagalan di item dataset.
            $actorItem = $data[0] ?? [];
            $actorError = $actorItem['error'] ?? null;
            $actorMessage = $actorItem['message'] ?? null;
            $isBlocked = ($actorItem['entity_type'] ?? null) === 'error'
                || strtoupper((string) ($actorItem['error_label'] ?? '')) === 'BLOCKED';

            if ($isBlocked || ! empty($actorError)) {
                $err = (string) ($actorError ?: $actorMessage ?: 'Actor Apify mengembalikan error tanpa pesan.');
                if (stripos($err, 'not found') !== false || stripos($err, 'private') !== false) {
                    throw new LinkedInProfileNotFoundException($cleanUrl);
                }
                throw new LinkedInSyncException("Apify Scraper Error: {$err}");
            }

            return $data;

        } catch (LinkedInProfileNotFoundException $e) {
            throw $e;
        } catch (LinkedInSyncException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            Log::error('Apify Connection Timeout: '.$e->getMessage());
            throw new LinkedInSyncException('Koneksi ke server Apify terputus atau melebihi batas waktu (timeout).');
        } catch (Throwable $e) {
            Log::error('Apify Unexpected Error: '.$e->getMessage());
            throw new LinkedInSyncException('Terjadi kendala saat berkomunikasi dengan server Apify.');
        }
    }

    /**
     * Mengambil dan menormalisasi profil LinkedIn dari Apify berdasarkan URL.
     *
     * @param  string  $url  URL lengkap profil LinkedIn.
     *
     * @throws LinkedInProfileNotFoundException
     * @throws LinkedInSyncException
     */
    public function findByUrl(string $url): ?LinkedInProfile
    {
        $raw = $this->scrapeProfile($url);

        $item = isset($raw[0]) && is_array($raw[0]) ? $raw[0] : $raw;

        return $this->normalizeToDto($item, $url);
    }

    /**
     * Mengambil dan menormalisasi profil LinkedIn dari Apify berdasarkan username.
     * Mengubah username menjadi URL profil resmi terlebih dahulu.
     *
     * @param  string  $username  Username / vanity name profil alumni.
     *
     * @throws LinkedInProfileNotFoundException
     * @throws LinkedInSyncException
     */
    public function findByUsername(string $username): ?LinkedInProfile
    {
        $clean = trim($username);
        if (empty($clean)) {
            return null;
        }

        // Jika username adalah URL lengkap, gunakan langsung
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            return $this->findByUrl($clean);
        }

        return $this->findByUrl("https://www.linkedin.com/in/{$clean}");
    }

    /**
     * Menormalisasi struktur respons JSON mentah dari Apify Actor menjadi DTO LinkedInProfile standar.
     */
    protected function normalizeToDto(array $item, string $sourceUrl): LinkedInProfile
    {
        $name = trim($item['name'] ?? $item['fullName'] ?? '');
        $firstName = $item['firstName'] ?? '';
        $lastName = $item['lastName'] ?? '';

        if (empty($name) && (! empty($firstName) || ! empty($lastName))) {
            $name = trim("{$firstName} {$lastName}");
        }

        if (empty($firstName) && ! empty($name)) {
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        }

        $headline = $item['headline'] ?? $item['title'] ?? '';

        // Ekstraksi vanity name dari URL
        $vanity = '';
        if (preg_match('~linkedin\.com/in/([^/?#]+)~i', $sourceUrl, $matches)) {
            $vanity = $matches[1];
        }

        // Ekstraksi pengalaman kerja / positions
        $positions = [];
        $rawExp = $this->asList($item['experience'] ?? $item['experiences'] ?? $item['positions'] ?? []);
        $rawExp = array_merge($this->asList($item['currentPosition'] ?? []), $rawExp);

        if (is_array($rawExp)) {
            foreach ($rawExp as $pos) {
                if (! is_array($pos)) {
                    continue;
                }

                $title = $pos['position'] ?? $pos['title'] ?? $pos['jobTitle'] ?? $pos['role'] ?? 'Alumni';
                $company = $pos['companyName'] ?? $pos['company'] ?? $pos['name'] ?? 'Perusahaan';
                $location = $pos['location'] ?? $pos['locationName'] ?? null;
                $isCurrent = (isset($pos['isCurrent']) && $pos['isCurrent'] === true)
                    || (array_key_exists('endDate', $pos) && (is_null($pos['endDate']) || $pos['endDate'] === 'Present'));

                $startYear = null;
                $startMonth = null;
                [$startYear, $startMonth] = $this->dateParts($pos['startDate'] ?? null);
                [$endYear, $endMonth] = $this->dateParts($pos['endDate'] ?? null);

                $positions[] = new LinkedInPosition(
                    id: (string) ($pos['id'] ?? uniqid('pos_')),
                    title: ['localized' => ['en_US' => $title]],
                    localizedTitle: $title,
                    companyName: ['localized' => ['en_US' => $company]],
                    localizedCompanyName: $company,
                    startMonthYear: $startYear ? ['year' => $startYear, 'month' => $startMonth ?? 1] : null,
                    endMonthYear: $endYear ? ['year' => $endYear, 'month' => $endMonth ?? 1] : null,
                    geoPositionLocation: $location ? ['localizedDisplayLocationName' => $location] : null,
                    localizedDisplayLocationName: $location,
                );
            }
        }

        return new LinkedInProfile(
            id: (string) ($item['id'] ?? uniqid('apify_')),
            firstName: ['localized' => ['en_US' => $firstName]],
            localizedFirstName: $firstName,
            lastName: ['localized' => ['en_US' => $lastName]],
            localizedLastName: $lastName,
            headline: ['localized' => ['en_US' => $headline]],
            localizedHeadline: $headline,
            vanityName: $vanity,
            profilePicture: $this->profilePicture($item['profilePicture'] ?? null),
            positions: $positions,
        );
    }

    /**
     * Mengubah nilai actor yang dapat berupa satu objek atau daftar objek menjadi daftar.
     *
     * @param  mixed  $value  Nilai mentah dari actor.
     * @return list<array<string, mixed>>
     */
    private function asList(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    /**
     * Membaca tanggal actor berbentuk string maupun objek month/year/text.
     *
     * @param  mixed  $value  Nilai tanggal dari actor.
     * @return array{0: ?int, 1: ?int}
     */
    private function dateParts(mixed $value): array
    {
        if (is_array($value)) {
            $year = isset($value['year']) ? (int) $value['year'] : null;
            $month = isset($value['month']) && is_numeric($value['month']) ? (int) $value['month'] : null;

            return [$year, $month];
        }

        if (! is_string($value) || trim($value) === '' || strcasecmp(trim($value), 'Present') === 0) {
            return [null, null];
        }

        preg_match('/(\d{4})/', $value, $yearMatch);
        preg_match('/-(\d{1,2})/', $value, $monthMatch);

        return [
            isset($yearMatch[1]) ? (int) $yearMatch[1] : null,
            isset($monthMatch[1]) ? (int) $monthMatch[1] : null,
        ];
    }

    /**
     * Menyamakan struktur foto profil actor menjadi bentuk URL DTO.
     *
     * @param  mixed  $value  Objek foto atau URL mentah dari actor.
     * @return array{url: string}|null
     */
    private function profilePicture(mixed $value): ?array
    {
        if (is_string($value) && trim($value) !== '') {
            return ['url' => trim($value)];
        }

        if (! is_array($value)) {
            return null;
        }

        $url = $value['url'] ?? ($value['sizes'][0]['url'] ?? null);

        return is_string($url) && trim($url) !== '' ? ['url' => trim($url)] : null;
    }
}
