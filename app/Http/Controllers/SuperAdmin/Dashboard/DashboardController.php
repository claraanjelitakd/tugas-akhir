<?php

namespace App\Http\Controllers\SuperAdmin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\KuesionerJawaban;
use App\Models\LogActivity;
use App\Models\Perusahaan;
use App\Services\Dashboard\RoleDashboardDataService;
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
    public function tampilkanDashboard(Request $request, RoleDashboardDataService $dashboardData): Response
    {
        $user = $request->user();
        $requestedYear = $request->input('tahun') ? (string) $request->input('tahun') : null;
        $dashboardRoleData = $dashboardData->build('universitas', null, $requestedYear);
        $availableYears = $dashboardRoleData['availableYears'];
        $selectedYear = $dashboardRoleData['selectedYear'];
        $stats = $dashboardRoleData['stats'];

        $totalPertanyaan = (int) $stats['total_pertanyaan'];
        $totalSections = (int) $stats['total_sections'];
        $totalAlumni = (int) $stats['total_alumni'];
        $totalResponden = (int) $stats['total_responden'];
        $totalProdi = (int) $stats['total_prodi'];
        $totalPendingPerusahaan = (int) $stats['total_pending_perusahaan'];
        // Cleanup: query Perusahaan::count(), Perusahaan::where()->count(), dan Biodata atasan count
        // dihapus karena hanya dipakai oleh prop 'dashboardSummary' yang sudah dihapus
        $responseRate = (float) $stats['response_rate'];
        $totalLinkedIn = (int) $stats['total_linkedin'];

        $completionMetrics = $dashboardRoleData['completionMetrics'];
        $prodiSummaries = $dashboardRoleData['prodiSummaries'];
        $recentAlumni = $dashboardRoleData['recentAlumni'];

        // 4. Pratinjau Log Aktivitas Audit Terbaru dengan Identitas Unit/Prodi
        $recentLogs = LogActivity::with([
            'user.prodi:kode_prodi,nama_prodi',
            'user.fakultas:kode_fakultas,nama_fakultas',
        ])
            ->latest('id')
            ->take(8)
            ->get()
            ->map(function ($log) {
                $prodiName = $log->user?->prodi?->nama_prodi;
                $fakultasName = $log->user?->fakultas?->nama_fakultas;
                $unit = $prodiName
                    ? 'Prodi '.$prodiName
                    : ($fakultasName
                        ? 'Fakultas '.$fakultasName
                        : ($log->user?->role === 'superadmin' ? 'Biro ITC / Pusat' : ($log->user?->role === 'alumni' ? 'Alumni' : 'Sistem')));

                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name ?? 'Sistem',
                    'user_role' => $log->user?->role ?? 'system',
                    'unit' => $unit,
                    'action' => $log->action,
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at ? $log->created_at->diffForHumans() : '-',
                ];
            });

        // 5. Alumni yang Baru Saja Mengisi Kuesioner (Respon Masuk Terkini)
        $recentSubmissions = KuesionerJawaban::query()
            ->where('responden_type', 'alumni')
            ->whereNotNull('biodata_id')
            ->latest('id')
            ->take(30)
            ->with(['biodata.prodi:kode_prodi,nama_prodi', 'biodata.dataAkademik'])
            ->get()
            ->unique('biodata_id')
            ->take(6)
            ->values()
            ->map(function ($answer) {
                $biodata = $answer->biodata;
                $prodiName = $biodata?->prodi?->nama_prodi ?? '-';

                return [
                    'id' => $biodata?->id,
                    'nama' => $biodata?->nama ?? 'Alumni',
                    'nim' => $biodata?->nim ?? '-',
                    'prodi' => $prodiName,
                    'submitted_at' => $answer->updated_at ? $answer->updated_at->diffForHumans() : ($answer->created_at ? $answer->created_at->diffForHumans() : '-'),
                ];
            })
            ->all();

        if (empty($recentSubmissions) && ! empty($recentAlumni)) {
            $recentSubmissions = collect($recentAlumni)
                ->filter(fn ($a) => ! empty($a['has_responded']))
                ->take(6)
                ->map(fn ($a) => [
                    'id' => $a['id'],
                    'nama' => $a['nama'],
                    'nim' => $a['nim'],
                    'prodi' => $a['prodi'],
                    'submitted_at' => 'Tercatat mengisi',
                ])
                ->values()
                ->all();
        }

        $companyRequests = $this->buildCompanyRequests();
        $companyDirectory = $this->buildCompanyDirectory();
        $supervisorDirectory = $this->buildSupervisorDirectory($selectedYear);
        $companyDistribution = $this->buildCompanyDistribution();

        // 6. Mengembalikan view dasbor Superadmin dengan keselarasan kontrak bersama RoleDashboard
        return Inertia::render('SuperAdmin/Dashboard', [
            'user' => $user,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'dashboardStats' => [
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
            'companySummary' => $dashboardRoleData['companySummary'],
            'sectionsList' => $dashboardRoleData['sectionsList'],
            // Cleanup: 'stats' dan 'dashboardSummary' dihapus karena duplikat dari 'dashboardStats'
            // Cleanup: 'pendingPerusahaanList' dihapus karena redundan dengan 'companyRequests' yang lebih lengkap
            'completionMetrics' => $completionMetrics,
            'prodiSummaries' => $prodiSummaries,
            'recentAlumni' => $recentAlumni,
            'recentLogs' => $recentLogs,
            'recentSubmissions' => $recentSubmissions,
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
                'createdProdi:kode_prodi,nama_prodi',
                'propinsi:kode_provinsi,nama_provinsi',
                'kabupaten:kode_kabupaten,nama_kabupaten',
                'negaraMaster:kode_negara,nama_negara',
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
                'createdProdi:kode_prodi,nama_prodi',
                'propinsi:kode_provinsi,nama_provinsi',
                'kabupaten:kode_kabupaten,nama_kabupaten',
                'negaraMaster:kode_negara,nama_negara',
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
     * Mengagregasi sebaran perusahaan berdasarkan provinsi kantor atau negara (untuk luar negeri).
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function buildCompanyDistribution(): array
    {
        return DB::table('perusahaan')
            ->leftJoin('propinsi', 'propinsi.kode_provinsi', '=', 'perusahaan.kode_provinsi')
            ->leftJoin('ref_negara', 'ref_negara.kode_negara', '=', 'perusahaan.kode_negara')
            ->selectRaw("
                CASE
                    WHEN perusahaan.jenis_lokasi = 'Luar Negeri' THEN COALESCE(ref_negara.nama_negara, 'Luar Negeri')
                    ELSE COALESCE(propinsi.nama_provinsi, 'Belum ditentukan')
                END AS label
            ")
            ->selectRaw('COUNT(perusahaan.id) AS jumlah')
            ->groupBy('label')
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
        $location = null;
        if ($company->jenis_lokasi === 'Luar Negeri') {
            $location = $company->negaraMaster?->nama_negara ?: 'Luar Negeri';
        } else {
            $location = $company->kabupaten?->nama_kabupaten
                ?: ($company->propinsi?->nama_provinsi ?: 'Lokasi belum ditentukan');
        }

        return [
            'id' => $company->id,
            'name' => $company->nama_perusahaan,
            'status' => $company->status_verifikasi,
            'sector' => $company->sektor ?: ($company->bentuk_perusahaan ?: '-'),
            'location' => $location,
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
}
