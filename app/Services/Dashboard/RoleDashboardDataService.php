<?php

namespace App\Services\Dashboard;

use App\Models\KelompokPertanyaan;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\ProdiQuestion;
use App\Models\ProdiQuestionSection;
use App\Models\RefSubpertanyaan2021;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyediakan kontrak data dashboard yang sama untuk role pengelola tracer study.
 *
 * Service ini hanya membaca view audit dan tabel master yang diperlukan. Scope
 * prodi atau fakultas diterapkan sebelum agregasi agar angka dan daftar yang
 * ditampilkan pada dashboard tidak melewati kewenangan role yang sedang login.
 */
class RoleDashboardDataService
{
    /**
     * Membentuk data dashboard berdasarkan lingkup universitas, fakultas, atau prodi.
     *
     * @return array<string, mixed>
     */
    public function build(string $scope, ?int $scopeId = null, ?string $requestedYear = null): array
    {
        $availableYears = $this->availableYears();
        $selectedYear = $this->resolveYear($requestedYear, $availableYears);
        $auditRows = $this->scopedAuditQuery($scope, $scopeId, $selectedYear)->get();
        $prodis = $this->scopedProdis($scope, $scopeId);
        $prodiIds = $prodis->pluck('id')->all();

        $totalAlumni = $auditRows->count();
        $totalRespondents = $auditRows->where('answered_mandatory_univ', '>', 0)->count();
        $responseRate = $this->percentage($totalRespondents, $totalAlumni);
        $profileComplete = $auditRows->where('is_profile_complete', 1)->count();
        $universityComplete = $auditRows->where('is_complete_univ', 1)->count();
        $overallComplete = $auditRows
            ->where('is_profile_complete', 1)
            ->where('is_complete_univ', 1)
            ->count();

        $totalQuestions = $scope === 'prodi'
            ? ProdiQuestion::where('prodi_id', $scopeId)->count()
            : RefSubpertanyaan2021::count();
        $totalSections = $scope === 'prodi'
            ? ProdiQuestionSection::where('prodi_id', $scopeId)->count()
            : KelompokPertanyaan::count();

        $pendingCompaniesQuery = Perusahaan::where('status_verifikasi', 'Menunggu Verifikasi');
        if ($scope === 'prodi') {
            $pendingCompaniesQuery->where('created_by_prodi_id', $scopeId);
        } elseif ($scope === 'fakultas') {
            $pendingCompaniesQuery->whereIn('created_by_prodi_id', $prodiIds);
        }

        $totalLinkedIn = $auditRows->filter(function (object $row): bool {
            return ! empty($row->linkedin_url) || ! empty($row->linkedin_username);
        })->count();

        return [
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'stats' => [
                'total_alumni' => $totalAlumni,
                'total_responden' => $totalRespondents,
                'response_rate' => $responseRate,
                'response_rate_status' => $this->responseRateStatus($responseRate),
                'total_prodi' => $prodis->count(),
                'total_linkedin' => $totalLinkedIn,
                'total_pertanyaan' => $totalQuestions,
                'total_sections' => $totalSections,
                'total_pending_perusahaan' => $pendingCompaniesQuery->count(),
            ],
            'completionMetrics' => [
                'population' => $totalAlumni,
                'profile' => $this->completionMetric('Profil Alumni', $profileComplete, $totalAlumni),
                'university_questionnaire' => $this->completionMetric('Kuesioner Universitas', $universityComplete, $totalAlumni),
                'profile_and_university' => $this->completionMetric('Profil dan Kuesioner Universitas', $overallComplete, $totalAlumni),
            ],
            'prodiSummaries' => $this->buildProdiSummaries($prodis, $auditRows),
            'recentAlumni' => $this->buildRecentAlumni($auditRows),
            'sectionsList' => $this->buildSections($scope, $scopeId),
        ];
    }

    /**
     * Mengambil tahun lulusan yang tersedia dan selalu menyertakan tahun berjalan.
     *
     * @return array<int, string>
     */
    private function availableYears(): array
    {
        return DB::table('v_alumni_audit_rekap')
            ->whereNotNull('tahun_lulus')
            ->pluck('tahun_lulus')
            ->filter()
            ->map(fn (mixed $year): string => (string) $year)
            ->unique()
            ->push((string) now()->year)
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Menentukan tahun aktif yang valid untuk filter dashboard.
     *
     * @param  array<int, string>  $availableYears
     */
    private function resolveYear(?string $requestedYear, array $availableYears): string
    {
        $defaultYear = (string) now()->year;
        $year = $requestedYear ?: $defaultYear;

        return in_array($year, $availableYears, true) ? $year : $defaultYear;
    }

    /**
     * Membuat query sumber audit dengan scope role dan tahun yang telah dipilih.
     */
    private function scopedAuditQuery(string $scope, ?int $scopeId, string $selectedYear): Builder
    {
        return DB::table('v_alumni_audit_rekap')
            ->where('tahun_lulus', $selectedYear)
            ->when($scope === 'prodi', fn ($query) => $query->where('prodi_id', $scopeId))
            ->when($scope === 'fakultas', fn ($query) => $query->where('fakultas_id', $scopeId));
    }

    /**
     * Mengambil daftar program studi yang berada dalam scope dashboard.
     */
    private function scopedProdis(string $scope, ?int $scopeId): Collection
    {
        return Prodi::query()
            ->when($scope === 'prodi', fn ($query) => $query->whereKey($scopeId))
            ->when($scope === 'fakultas', fn ($query) => $query->where('fakultas_id', $scopeId))
            ->orderBy('kode_prodi')
            ->get();
    }

    /**
     * Membuat ringkasan partisipasi per program studi dari baris audit yang telah scope.
     *
     * @param  Collection<int, object>  $auditRows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildProdiSummaries(Collection $prodis, Collection $auditRows): Collection
    {
        return $prodis->map(function (Prodi $prodi) use ($auditRows): array {
            $rows = $auditRows->where('prodi_id', $prodi->id);
            $total = $rows->count();
            $respondents = $rows->where('answered_mandatory_univ', '>', 0)->count();
            $rate = $this->percentage($respondents, $total);

            return [
                'id' => $prodi->id,
                'kode_prodi' => $prodi->kode_prodi,
                'nama_prodi' => $prodi->nama_prodi,
                'total_alumni' => $total,
                'total_responden' => $respondents,
                'response_rate' => $rate,
                'response_rate_status' => $this->responseRateStatus($rate),
            ];
        });
    }

    /**
     * Membatasi pratinjau alumni agar dashboard tetap ringan dan interaktif.
     *
     * @param  Collection<int, object>  $auditRows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRecentAlumni(Collection $auditRows): Collection
    {
        return $auditRows
            ->sortByDesc('biodata_id')
            ->take(8)
            ->values()
            ->map(function (object $alumni): array {
                return [
                    'id' => $alumni->biodata_id,
                    'nim' => $alumni->nim ?? '-',
                    'nama' => $alumni->nama ?? 'Mahasiswa',
                    'prodi' => $alumni->nama_prodi ?? '-',
                    'tahun_lulus' => $alumni->tahun_lulus ?? '-',
                    'has_responded' => ((int) ($alumni->answered_mandatory_univ ?? 0)) > 0,
                    'has_linkedin' => ! empty($alumni->linkedin_url) || ! empty($alumni->linkedin_username),
                    'is_profile_complete' => (bool) ($alumni->is_profile_complete ?? false),
                    'is_univ_complete' => (bool) ($alumni->is_complete_univ ?? false),
                ];
            });
    }

    /**
     * Menyediakan struktur section khusus prodi bila memang tersedia.
     *
     * @return Collection<int, array<string, mixed>>|array<int, mixed>
     */
    private function buildSections(string $scope, ?int $scopeId): Collection|array
    {
        if ($scope !== 'prodi' || ! $scopeId) {
            return [];
        }

        return ProdiQuestionSection::where('prodi_id', $scopeId)
            ->withCount('questions')
            ->orderBy('order')
            ->get()
            ->map(fn (ProdiQuestionSection $section): array => [
                'id' => $section->id,
                'title' => $section->title,
                'questions_count' => $section->questions_count,
            ]);
    }

    /**
     * Menghitung persentase dengan pembagi aman saat populasi kosong.
     */
    private function percentage(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0;
    }

    /**
     * Menentukan status warna response rate untuk indikator visual dashboard.
     *
     * @return array{code: string, label: string}
     */
    private function responseRateStatus(float $rate): array
    {
        if ($rate < 50) {
            return ['code' => 'red', 'label' => 'Di bawah 50%'];
        }

        if ($rate < 75) {
            return ['code' => 'yellow', 'label' => '50–74,9%'];
        }

        return ['code' => 'green', 'label' => '75% atau lebih'];
    }

    /**
     * Membentuk metrik kelengkapan yang dipakai semua role.
     *
     * @return array{label: string, completed_count: int, population: int, percentage: float}
     */
    private function completionMetric(string $label, int $completedCount, int $population): array
    {
        return [
            'label' => $label,
            'completed_count' => $completedCount,
            'population' => $population,
            'percentage' => $this->percentage($completedCount, $population),
        ];
    }
}
