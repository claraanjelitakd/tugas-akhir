<?php

namespace App\Services\LinkedIn\Mappers;

use App\Models\Biodata;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Services\LinkedIn\DTOs\LinkedInPosition;
use App\Services\LinkedIn\DTOs\LinkedInProfile;
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
 * 3. Status verifikasi perusahaan baru WAJIB 'Menunggu Verifikasi' meskipun data
 *    wilayah / negara terisi lengkap, sesuai regulasi validasi universitas.
 */
class LinkedInProfileMapper
{
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
     * Status verifikasi perusahaan baru selalu disetel ke 'Menunggu Verifikasi'.
     */
    public function findOrCreateCompany(string $companyName, ?string $locationName = null): Perusahaan
    {
        $cleanName = trim($companyName);

        // 1. Pencocokan eksak (case-insensitive & trimmed)
        $existing = Perusahaan::whereRaw('LOWER(TRIM(nama_perusahaan)) = ?', [strtolower($cleanName)])->first();

        if ($existing) {
            return $existing;
        }

        // 2. Pemetaan lokasi dasar jika terdeteksi dengan jelas (tanpa tebak-tebakan agresif)
        $propinsiId = null;
        $negara = 'Indonesia';

        if ($locationName) {
            $lowerLoc = strtolower($locationName);
            if (str_contains($lowerLoc, 'yogyakarta') || str_contains($lowerLoc, 'diy')) {
                $propinsiId = Propinsi::where('nama_provinsi', 'like', '%Yogyakarta%')->value('id');
            } elseif (str_contains($lowerLoc, 'jakarta')) {
                $propinsiId = Propinsi::where('nama_provinsi', 'like', '%Jakarta%')->value('id');
            }

            if (! str_contains($lowerLoc, 'indonesia')) {
                // Jika lokasi luar negeri teridentifikasi
                $parts = array_map('trim', explode(',', $locationName));
                $lastPart = end($parts);
                if (! empty($lastPart) && strlen($lastPart) > 2) {
                    $negara = $lastPart;
                }
            }
        }

        // 3. Buat institusi perusahaan baru dengan status WAJIB 'Menunggu Verifikasi'
        return Perusahaan::create([
            'nama_perusahaan' => $cleanName,
            'status_verifikasi' => 'Menunggu Verifikasi', // Kebijakan mutlak: Menunggu Verifikasi
            'propinsi_id' => $propinsiId,
            'kabupaten_id' => null, // Jangan menebak kabupaten tanpa data pasti
            'negara' => $negara,
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
