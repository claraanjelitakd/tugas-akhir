<?php

namespace App\Http\Controllers\AdminBiroTiga\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\RoleDashboardDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menyajikan dashboard Biro 3 menggunakan metrik universitas yang sama dengan role pengelola lain.
 */
class DashboardController extends Controller
{
    /**
     * Menampilkan metrik universitas, partisipasi per prodi, dan pratinjau alumni.
     */
    public function tampilkanDashboard(Request $request, RoleDashboardDataService $dashboardData): Response
    {
        $dashboard = $dashboardData->build('universitas', null, $request->string('tahun')->toString());
        $stats = $dashboard['stats'];

        return Inertia::render('AdminBiroTiga/Dashboard', [
            'user' => $request->user(),
            'availableYears' => $dashboard['availableYears'],
            'selectedYear' => $dashboard['selectedYear'],
            'dashboardStats' => $stats,
            'completionMetrics' => $dashboard['completionMetrics'],
            'prodiSummaries' => $dashboard['prodiSummaries'],
            'recentAlumni' => $dashboard['recentAlumni'],
            'sectionsList' => $dashboard['sectionsList'],
            'stats' => array_merge($stats, [
                'total_alumni' => $stats['total_alumni'],
                'total_responden' => $stats['total_responden'],
                'persentase_respon' => $stats['response_rate'],
                'total_pertanyaan' => $stats['total_pertanyaan'],
                'total_prodi' => $stats['total_prodi'],
                'alumni_linkedin' => $stats['total_linkedin'],
                'total_pending_perusahaan' => $stats['total_pending_perusahaan'],
            ]),
        ]);
    }
}
