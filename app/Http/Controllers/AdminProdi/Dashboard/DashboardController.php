<?php

namespace App\Http\Controllers\AdminProdi\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\RoleDashboardDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menyajikan dashboard Admin Program Studi dengan kontrak data dashboard bersama.
 */
class DashboardController extends Controller
{
    /**
     * Menampilkan metrik, ringkasan prodi, dan pratinjau alumni sesuai scope prodi.
     */
    public function tampilkanDashboard(Request $request, RoleDashboardDataService $dashboardData): Response
    {
        $user = Auth::user()->load('prodi');
        $dashboard = $dashboardData->build('prodi', $user->prodi_id, $request->string('tahun')->toString());
        $stats = $dashboard['stats'];

        return Inertia::render('AdminProdi/Dashboard', [
            'user' => $user,
            'prodi' => $user->prodi,
            'availableYears' => $dashboard['availableYears'],
            'selectedYear' => $dashboard['selectedYear'],
            'dashboardStats' => $stats,
            'completionMetrics' => $dashboard['completionMetrics'],
            'prodiSummaries' => $dashboard['prodiSummaries'],
            'recentAlumni' => $dashboard['recentAlumni'],
            'sectionsList' => $dashboard['sectionsList'],
            'stats' => array_merge($stats, [
                'totalAlumni' => $stats['total_alumni'],
                'totalSections' => $stats['total_sections'],
                'totalPertanyaan' => $stats['total_pertanyaan'],
                'totalRespondenProdi' => $stats['total_responden'],
                'persentasePartisipasi' => $stats['response_rate'],
                'totalUnivSelesai' => $dashboard['completionMetrics']['university_questionnaire']['completed_count'],
                'persentaseUniv' => $dashboard['completionMetrics']['university_questionnaire']['percentage'],
                'totalPendingPerusahaan' => $stats['total_pending_perusahaan'],
                'totalLinkedIn' => $stats['total_linkedin'],
            ]),
            'recentAlumnis' => $dashboard['recentAlumni'],
        ]);
    }
}
