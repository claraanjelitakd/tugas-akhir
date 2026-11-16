<?php

namespace App\Services\LinkedIn\Mappers;

use App\Models\Biodata;
use App\Models\Perusahaan;
use App\Services\LinkedIn\DTOs\LinkedInPosition;
use App\Services\LinkedIn\DTOs\LinkedInProfile;
use App\Services\Perusahaan\PerusahaanVerificationService;
use Illuminate\Support\Facades\Auth;

/**
 * Class LinkedInProfileMapper
 *
 * Bertanggung jawab memetakan data dari DTO profil LinkedIn eksternal
 * ke model internal aplikasi Tracer Study (Biodata dan Perusahaan).
 *
 * Kebijakan Integritas Data:
 * 1. Hanya memperbarui `posisi_jabatan` dan `perusahaan_id`.
 * 2. TIDAK PERNAH mengubah data authoritative alumni (NIM, email_pribadi,
 *    nomor_telepon, alamat rumah, maupun data akademik kelulusan).
 * 3. Master perusahaan menyimpan head office dan tidak diubah oleh lokasi kerja
 *    LinkedIn. Kandidat baru tetap berstatus 'Menunggu Verifikasi'.
 */
class LinkedInProfileMapper
{
    /**
     * Menyiapkan matcher perusahaan yang sudah dipakai oleh modul verifikasi master.
     *
     * @param  PerusahaanVerificationService|null  $verificationService  Matcher similarity perusahaan.
     */
    public function __construct(
        private ?PerusahaanVerificationService $verificationService = null,
    ) {}

    /**
     * Memilih posisi pekerjaan aktif / saat ini (Current Position) dari daftar posisi LinkedIn
     * dengan aturan prioritas resmi:
     * 1. Pilih posisi dengan endMonthYear == null (posisi aktif).
     * 2. Jika ada >1 posisi aktif, pilih posisi dengan startMonthYear paling baru.
     * 3. Jika tidak ada posisi aktif, pilih posisi dengan endMonthYear paling baru.
     * 4. Jika tidak ada riwayat posisi sama sekali, kembalikan null.
     */
    public function determineCurrentPosition(LinkedInProfile $profile): ?LinkedInPosition
    {
        $positions = $profile->positions;

        if (empty($positions)) {
            return null;
        }

        // Filter posisi aktif (endMonthYear === null)
        $activePositions = array_filter($positions, fn (LinkedInPosition $p) => $p->isCurrent());

        if (! empty($activePositions)) {
            // Urutkan berdasarkan waktu mulai (startMonthYear) paling baru
            usort($activePositions, function (LinkedInPosition $a, LinkedInPosition $b) {
                if ($a->getStartYear() !== $b->getStartYear()) {
                    return $b->getStartYear() <=> $a->getStartYear();
                }

                return $b->getStartMonth() <=> $a->getStartMonth();
            });

            return reset($activePositions);
        }

        // Jika tidak ada posisi aktif, pilih yang memiliki endMonthYear paling baru
        $pastPositions = $positions;
        usort($pastPositions, function (LinkedInPosition $a, LinkedInPosition $b) {
            if ($a->getEndYear() !== $b->getEndYear()) {
                return $b->getEndYear() <=> $a->getEndYear();
            }

            return $b->getEndMonth() <=> $a->getEndMonth();
        });

        return reset($pastPositions);
    }

    /**
     * Mencari atau membuat record Perusahaan berdasarkan nama perusahaan dari LinkedIn.
     *
     * Aturan Verifikasi:
     * Master terverifikasi dipertahankan; kandidat baru selalu berstatus
     * 'Menunggu Verifikasi'.
     */
    public function findOrCreateCompany(string $companyName, ?string $locationName = null): Perusahaan
    {
        return $this->resolveCompany($companyName, $locationName)['company'];
    }

    /**
     * Mencari perusahaan yang sudah ada, memanfaatkan rekomendasi similarity,
     * atau membuat kandidat master baru yang menunggu verifikasi.
     *
     * Kecocokan similarity hanya dipakai otomatis pada skor sangat tinggi.
     * Kandidat yang belum pasti tetap dibuat agar nama perusahaan terlihat oleh
     * admin tanpa mengubah biodata alumni sebelum approval profil.
     *
     * @return array{company: Perusahaan, status: string, match_type: string, similarity_score: int|null, created: bool, recommendation: array<string, mixed>|null}
     */
    public function resolveCompany(string $companyName, ?string $locationName = null): array
    {
        $cleanName = trim($companyName);

        $existing = Perusahaan::query()
            ->whereRaw('LOWER(TRIM(nama_perusahaan)) = ?', [strtolower($cleanName)])
            ->first();

        if ($existing) {
            return [
                'company' => $existing,
                'status' => $existing->status_verifikasi === 'Terverifikasi'
                    ? 'matched'
                    : 'pending_verification',
                'match_type' => 'exact',
                'similarity_score' => 100,
                'created' => false,
                'recommendation' => null,
            ];
        }

        $candidate = $this->createPendingCompany($cleanName, $locationName);
        $recommendation = $this->verificationService()->getSimilarRecommendations($candidate, 1)[0] ?? null;

        // Skor 90 mengakomodasi variasi kecil dari provider seperti suffix "Official".
        // Skor yang lebih rendah tetap membutuhkan keputusan admin karena bisa merupakan anak usaha.
        if ($recommendation && (int) $recommendation['similarity_score'] >= 90) {
            $candidate->delete();

            return [
                'company' => Perusahaan::findOrFail($recommendation['id']),
                'status' => 'matched',
                'match_type' => 'similarity',
                'similarity_score' => (int) $recommendation['similarity_score'],
                'created' => false,
                'recommendation' => null,
            ];
        }

        return [
            'company' => $candidate,
            'status' => 'pending_verification',
            'match_type' => 'new_candidate',
            'similarity_score' => $recommendation ? (int) $recommendation['similarity_score'] : null,
            'created' => true,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Menyediakan instance matcher melalui container saat mapper dibuat langsung dalam unit test.
     */
    private function verificationService(): PerusahaanVerificationService
    {
        return $this->verificationService ??= app(PerusahaanVerificationService::class);
    }

    /**
     * Membuat kandidat master perusahaan berstatus menunggu verifikasi.
     */
    private function createPendingCompany(string $cleanName, ?string $locationName = null): Perusahaan
    {
        // Lokasi LinkedIn adalah lokasi kerja/cabang, bukan head office master.
        // Lokasi tersebut tetap tersedia di payload staging dan tidak ditulis ke perusahaan.
        $kodeProvinsi = null;
        $kodeNegara = 'ID';

        unset($locationName);

        // Kandidat baru tidak langsung dianggap sebagai master resmi.
        return Perusahaan::create([
            'nama_perusahaan' => $cleanName,
            'status_verifikasi' => 'Menunggu Verifikasi',
            'kode_provinsi' => $kodeProvinsi,
            'kode_kabupaten' => null, // Jangan menebak kabupaten tanpa data pasti
            'kode_negara' => $kodeNegara,
            'sektor' => 'Teknologi Informasi & Jasa Profesional',
            'skala' => 'Nasional',
            'created_by_user_id' => Auth::id(),
        ]);
    }

    /**
     * Memetakan data LinkedIn ke atribut Biodata.
     * Mengembalikan array rincian perubahan yang telah disiapkan.
     *
     * @return array{
     *     biodata_updates: array,
     *     perusahaan: ?Perusahaan,
     *     current_position: ?LinkedInPosition,
     *     changes_summary: array
     * }
     */
    public function mapToBiodata(Biodata $biodata, LinkedInProfile $profile): array
    {
        $currentPosition = $this->determineCurrentPosition($profile);
        $updates = [];
        $changesSummary = [];
        $perusahaan = null;

        // 1. Pemetaan Posisi Jabatan
        if ($currentPosition && ! empty($currentPosition->localizedTitle)) {
            $oldJob = $biodata->posisi_jabatan;
            $newJob = trim($currentPosition->localizedTitle);

            if ($oldJob !== $newJob) {
                $updates['posisi_jabatan'] = $newJob;
                $changesSummary[] = "Jabatan diubah dari '".($oldJob ?: '-')."' menjadi '{$newJob}'";
            }
        }

        // 2. Pemetaan Perusahaan
        if ($currentPosition && ! empty($currentPosition->localizedCompanyName)) {
            $companyName = trim($currentPosition->localizedCompanyName);
            $locationName = $currentPosition->localizedDisplayLocationName;

            $perusahaan = $this->findOrCreateCompany($companyName, $locationName);

            if ($biodata->perusahaan_id !== $perusahaan->id) {
                $oldCompanyName = $biodata->perusahaan?->nama_perusahaan ?? '-';
                $updates['perusahaan_id'] = $perusahaan->id;
                $changesSummary[] = "Perusahaan diubah dari '{$oldCompanyName}' menjadi '{$perusahaan->nama_perusahaan}'";
            }
        }

        // 3. Pastikan URL LinkedIn tersimpan jika belum ada
        if (empty($biodata->linkedin_url) && ! empty($profile->vanityName)) {
            $profileUrl = $profile->getPublicProfileUrl();
            $updates['linkedin_url'] = $profileUrl;
            $changesSummary[] = "URL LinkedIn diperbarui ke '{$profileUrl}'";
        }

        return [
            'biodata_updates' => $updates,
            'perusahaan' => $perusahaan,
            'current_position' => $currentPosition,
            'changes_summary' => $changesSummary,
        ];
    }
}
