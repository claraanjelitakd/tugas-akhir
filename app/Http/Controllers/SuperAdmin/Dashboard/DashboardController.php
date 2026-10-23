<?php

namespace App\Http\Controllers\SuperAdmin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\KelompokPertanyaan;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\RefSubpertanyaan2021;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class DashboardController (SuperAdmin)
 *
 * Fungsi:
 * Menampilkan halaman dasbor eksekutif utama untuk Superadmin.
 *
 * Tujuan:
 * Menyajikan gambaran umum ekosistem Tracer Study secara menyeluruh dengan kueri berkecepatan tinggi
 * berbasis Database View `v_alumni_audit_rekap`.
 */
class DashboardController extends Controller
{
    private const RESPONSE_RATE_YELLOW_THRESHOLD = 50.0;

    private const RESPONSE_RATE_GREEN_THRESHOLD = 75.0;

    /**
     * Tampilkan Dashboard Superadmin beserta metrik ringkasan sistem.
     */
    public function tampilkanDashboard(Request $request): Response
    {
        $user = $request->user();
        $defaultYear = (string) now()->year;
        $availableYears = DB::table('v_alumni_audit_rekap')
            ->whereNotNull('tahun_lulus')
            ->pluck('tahun_lulus')
            ->filter()
            ->map(fn ($year): string => (string) $year)
            ->unique()
            ->push($defaultYear)
            ->sortDesc()
            ->values()
            ->all();
        $selectedYear = (string) $request->input('tahun', $defaultYear);

        if (! in_array($selectedYear, $availableYears, true)) {
            $selectedYear = $defaultYear;
        }

        // 1. Menghitung ringkasan statistik kuesioner dan partisipasi via v_alumni_audit_rekap
        $totalPertanyaan = RefSubpertanyaan2021::count();
        $totalSections = KelompokPertanyaan::count();
        $dashboardAggregate = DB::table('v_alumni_audit_rekap')
            ->where('tahun_lulus', $selectedYear)
            ->selectRaw('COUNT(*) AS total_alumni')
            ->selectRaw('SUM(CASE WHEN answered_mandatory_univ > 0 THEN 1 ELSE 0 END) AS total_responden')
            ->selectRaw('SUM(CASE WHEN is_profile_complete = 1 THEN 1 ELSE 0 END) AS profile_complete_count')
            ->selectRaw('SUM(CASE WHEN is_complete_univ = 1 THEN 1 ELSE 0 END) AS university_complete_count')
            ->selectRaw('SUM(CASE WHEN is_profile_complete = 1 AND is_complete_univ = 1 THEN 1 ELSE 0 END) AS overall_complete_count')
            ->selectRaw("SUM(CASE WHEN (linkedin_url IS NOT NULL AND linkedin_url != '') OR (linkedin_username IS NOT NULL AND linkedin_username != '') THEN 1 ELSE 0 END) AS total_linkedin")
            ->first();

        $totalAlumni = (int) ($dashboardAggregate->total_alumni ?? 0);
        $totalResponden = (int) ($dashboardAggregate->total_responden ?? 0);
        $totalProdi = Prodi::count();
        $totalPendingPerusahaan = Perusahaan::where('status_verifikasi', 'Menunggu Verifikasi')->count();
        $responseRate = $totalAlumni > 0 ? round(($totalResponden / $totalAlumni) * 100, 1) : 0;

        $totalLinkedIn = (int) ($dashboardAggregate->total_linkedin ?? 0);

        $completionMetrics = [
            'population' => $totalAlumni,
            'profile' => $this->buildCompletionMetric(
                'Profil Alumni',
                (int) ($dashboardAggregate->profile_complete_count ?? 0),
                $totalAlumni,
            ),
            'university_questionnaire' => $this->buildCompletionMetric(
                'Kuesioner Universitas',
                (int) ($dashboardAggregate->university_complete_count ?? 0),
                $totalAlumni,
            ),
            'profile_and_university' => $this->buildCompletionMetric(
                'Profil dan Kuesioner Universitas',
                (int) ($dashboardAggregate->overall_complete_count ?? 0),
                $totalAlumni,
            ),
        ];

        // 2. Ringkasan Partisipasi per Program Studi se-Universitas (Single Aggregated Query)
        $statsPerProdi = DB::table('v_alumni_audit_rekap')
            ->where('tahun_lulus', $selectedYear)
            ->select(
                'prodi_id',
                DB::raw('COUNT(*) as total_alumni'),
                DB::raw('SUM(CASE WHEN answered_mandatory_univ > 0 THEN 1 ELSE 0 END) as total_responden')
            )
            ->whereNotNull('prodi_id')
            ->groupBy('prodi_id')
            ->get()
            ->keyBy('prodi_id');

        $prodiSummaries = Prodi::orderBy('kode_prodi', 'asc')
            ->get()
            ->map(function ($p) use ($statsPerProdi) {
                $stat = $statsPerProdi->get($p->id);
                $totalBio = (int) ($stat->total_alumni ?? 0);
                $respondenCount = (int) ($stat->total_responden ?? 0);
                $rate = $totalBio > 0 ? round(($respondenCount / $totalBio) * 100, 1) : 0;

                return [
                    'id' => $p->id,
                    'kode_prodi' => $p->kode_prodi,
                    'nama_prodi' => $p->nama_prodi,
                    'total_alumni' => $totalBio,
                    'total_responden' => $respondenCount,
                    'response_rate' => $rate,
                    'response_rate_status' => $this->getResponseRateStatus($rate),
                ];
            });

        // 3. Pratinjau 8 Alumni Terbaru Terdaftar via v_alumni_audit_rekap
        $recentAlumni = DB::table('v_alumni_audit_rekap')
            ->where('tahun_lulus', $selectedYear)
            ->orderByDesc('biodata_id')
            ->take(8)
            ->get()
            ->map(function ($alumni) {
                return [
                    'id' => $alumni->biodata_id,
                    'nim' => $alumni->nim ?? '-',
                    'nama' => $alumni->nama ?? 'Mahasiswa',
                    'prodi' => $alumni->nama_prodi ?? '-',
                    'tahun_lulus' => $alumni->tahun_lulus ?? $alumni->tahun_akademik_lulus ?? '-',
                    'has_responded' => ((int) $alumni->answered_mandatory_univ) > 0,
                    'has_linkedin' => ! empty($alumni->linkedin_url) || ! empty($alumni->linkedin_username),
                ];
            });

        // 4. Pratinjau 6 Log Aktivitas Audit Terbaru
        $recentLogs = LogActivity::with('user')
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name ?? 'Sistem',
                    'action' => $log->action,
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at ? $log->created_at->diffForHumans() : '-',
                ];
            });

        // 5. Antrean Perusahaan Menunggu Verifikasi
        $pendingPerusahaanList = Perusahaan::with('createdProdi')
            ->where('status_verifikasi', 'Menunggu Verifikasi')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nama' => $c->nama_perusahaan,
                    'sektor' => $c->sektor_industri ?? $c->bentuk_lembaga ?? '-',
                    'kota' => $c->kota ?? $c->wilayah ?? '-',
                    'prodi' => $c->createdProdi?->nama_prodi ?? 'Umum',
                    'created_at' => $c->created_at ? $c->created_at->diffForHumans() : '-',
                ];
            });

        // 6. Mengembalikan view dasbor Superadmin
        return Inertia::render('SuperAdmin/Dashboard', [
            'user' => $user,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'stats' => [
                'total_pertanyaan' => $totalPertanyaan,
                'total_sections' => $totalSections,
                'total_alumni' => $totalAlumni,
                'total_responden' => $totalResponden,
                'total_prodi' => $totalProdi,
                'total_pending_perusahaan' => $totalPendingPerusahaan,
                'response_rate' => $responseRate,
                'total_linkedin' => $totalLinkedIn,
                'response_rate_status' => $this->getResponseRateStatus($responseRate),
            ],
            'completionMetrics' => $completionMetrics,
            'prodiSummaries' => $prodiSummaries,
            'recentAlumni' => $recentAlumni,
            'recentLogs' => $recentLogs,
            'pendingPerusahaanList' => $pendingPerusahaanList,
        ]);
    }

    /**
     * @return array{code: string, label: string}
     */
    private function getResponseRateStatus(float $rate): array
    {
        if ($rate < self::RESPONSE_RATE_YELLOW_THRESHOLD) {
            return [
                'code' => 'red',
                'label' => 'Di bawah 50%',
            ];
        }

        if ($rate < self::RESPONSE_RATE_GREEN_THRESHOLD) {
            return [
                'code' => 'yellow',
                'label' => '50–74,9%',
            ];
        }

        return [
            'code' => 'green',
            'label' => '75% atau lebih',
        ];
    }

    /**
     * @return array{label: string, completed_count: int, population: int, percentage: float}
     */
    private function buildCompletionMetric(string $label, int $completedCount, int $population): array
    {
        return [
            'label' => $label,
            'completed_count' => $completedCount,
            'population' => $population,
            'percentage' => $population > 0 ? round(($completedCount / $population) * 100, 1) : 0,
        ];
    }
}
