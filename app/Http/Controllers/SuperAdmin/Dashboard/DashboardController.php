<?php

namespace App\Http\Controllers\SuperAdmin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Models\Prodi;
use Illuminate\Database\Eloquent\Builder;
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
        $totalPertanyaan = KuesionerPertanyaan::where('is_active', true)->count();
        $totalSections = KuesionerSection::where('is_active', true)->count();
        $dashboardAggregate = DB::table('v_alumni_audit_rekap')
            ->where('tahun_lulus', $selectedYear)
            ->selectRaw('COUNT(*) AS total_alumni')
            ->selectRaw('SUM(CASE WHEN is_complete_univ = 1 THEN 1 ELSE 0 END) AS total_responden')
            ->selectRaw('SUM(CASE WHEN is_profile_complete = 1 THEN 1 ELSE 0 END) AS profile_complete_count')
            ->selectRaw('SUM(CASE WHEN is_complete_univ = 1 THEN 1 ELSE 0 END) AS university_complete_count')
            ->selectRaw('SUM(CASE WHEN is_profile_complete = 1 AND is_complete_univ = 1 THEN 1 ELSE 0 END) AS overall_complete_count')
            ->selectRaw("SUM(CASE WHEN (linkedin_url IS NOT NULL AND linkedin_url != '') OR (linkedin_username IS NOT NULL AND linkedin_username != '') THEN 1 ELSE 0 END) AS total_linkedin")
            ->first();

        $totalAlumni = (int) ($dashboardAggregate->total_alumni ?? 0);
        $totalResponden = (int) ($dashboardAggregate->total_responden ?? 0);
        $totalProdi = Prodi::count();
        $totalPendingPerusahaan = Perusahaan::where('status_verifikasi', 'Menunggu Verifikasi')->count();
        $totalPerusahaan = Perusahaan::count();
        $totalPerusahaanTerverifikasi = Perusahaan::where('status_verifikasi', 'Terverifikasi')->count();
        $totalAtasan = Biodata::query()
            ->whereNotNull('atasan_id')
            ->whereHas('dataAkademik', fn (Builder $query): Builder => $query->where('tahun_lulus', $selectedYear))
            ->count();
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
                DB::raw('SUM(CASE WHEN is_complete_univ = 1 THEN 1 ELSE 0 END) as total_responden')
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

        $companyRequests = $this->buildCompanyRequests();
        $companyDirectory = $this->buildCompanyDirectory();
        $supervisorDirectory = $this->buildSupervisorDirectory($selectedYear);
        $companyDistribution = $this->buildCompanyDistribution();

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
            'dashboardSummary' => [
                'total_alumni' => $totalAlumni,
                'total_perusahaan' => $totalPerusahaan,
                'total_perusahaan_terverifikasi' => $totalPerusahaanTerverifikasi,
                'total_atasan' => $totalAtasan,
                'total_prodi' => $totalProdi,
                'pending_perusahaan' => $totalPendingPerusahaan,
            ],
            'companyRequests' => $companyRequests,
            'companyDirectory' => $companyDirectory,
            'supervisorDirectory' => $supervisorDirectory,
            'companyDistribution' => $companyDistribution,
        ]);
    }

    /**
     * Membentuk antrean pengajuan perusahaan dan identitas pengusulnya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanyRequests(): array
    {
        return Perusahaan::query()
            ->with([
                'creator:id,name,role,username',
                'createdProdi:id,nama_prodi',
                'propinsi:kode_provinsi,nama_provinsi',
                'kabupaten:kode_kabupaten,nama_kabupaten',
            ])
            ->where('status_verifikasi', 'Menunggu Verifikasi')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Perusahaan $company): array => $this->formatCompanyRecord($company))
            ->all();
    }

    /**
     * Membentuk pratinjau master perusahaan dengan jumlah alumni yang terhubung.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanyDirectory(): array
    {
        return Perusahaan::query()
            ->with([
                'creator:id,name,role,username',
                'createdProdi:id,nama_prodi',
                'propinsi:kode_provinsi,nama_provinsi',
                'kabupaten:kode_kabupaten,nama_kabupaten',
            ])
            ->withCount('biodata')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Perusahaan $company): array => [
                ...$this->formatCompanyRecord($company),
                'alumni_count' => (int) $company->biodata_count,
            ])
            ->all();
    }

    /**
     * Membentuk pratinjau data atasan beserta perusahaan dan jumlah alumni.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSupervisorDirectory(string $selectedYear): array
    {
        return Biodata::query()
            ->whereNotNull('atasan_id')
            ->whereHas('dataAkademik', fn (Builder $query): Builder => $query->where('tahun_lulus', $selectedYear))
            ->with([
                'atasan:id,nama,email,telepon,perusahaan_id',
                'atasan.perusahaan:id,nama_perusahaan',
                'perusahaan:id,nama_perusahaan',
                'dataAkademik:nim,nama,tahun_lulus',
            ])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Biodata $biodata): array => [
                'id' => $biodata->id,
                'name' => $biodata->atasan?->nama ?? 'Atasan belum diisi',
                'company' => $biodata->atasan?->perusahaan?->nama_perusahaan
                    ?? $biodata->perusahaan?->nama_perusahaan
                    ?? 'Perusahaan belum ditentukan',
                'alumni_name' => $biodata->dataAkademik?->nama ?? $biodata->nim,
                'alumni_nim' => $biodata->nim,
                'contact' => $biodata->atasan?->email ?: ($biodata->atasan?->telepon ?: '-'),
            ])
            ->all();
    }

    /**
     * Mengagregasi sebaran perusahaan berdasarkan provinsi kantor yang tercatat.
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function buildCompanyDistribution(): array
    {
        return DB::table('perusahaan')
            ->leftJoin('propinsi', 'propinsi.kode_provinsi', '=', 'perusahaan.kode_provinsi')
            ->selectRaw("COALESCE(propinsi.nama_provinsi, 'Belum ditentukan') AS label")
            ->selectRaw('COUNT(perusahaan.id) AS jumlah')
            ->groupBy('propinsi.nama_provinsi')
            ->orderByDesc('jumlah')
            ->orderBy('label')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->jumlah,
            ])
            ->all();
    }

    /**
     * Menormalkan data perusahaan untuk kartu pengajuan dan daftar master.
     *
     * @return array<string, mixed>
     */
    private function formatCompanyRecord(Perusahaan $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->nama_perusahaan,
            'status' => $company->status_verifikasi,
            'sector' => $company->sektor ?: ($company->bentuk_perusahaan ?: '-'),
            'location' => $company->kabupaten?->nama_kabupaten
                ?: ($company->propinsi?->nama_provinsi ?: 'Lokasi belum ditentukan'),
            'applicant' => $company->creator?->name
                ?: ($company->createdProdi?->nama_prodi ?: 'Sistem'),
            'applicant_role' => $company->creator?->role ?: 'admin_prodi',
            'applicant_prodi' => $company->createdProdi?->nama_prodi,
            'created_at' => $company->created_at?->diffForHumans() ?: '-',
        ];
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
