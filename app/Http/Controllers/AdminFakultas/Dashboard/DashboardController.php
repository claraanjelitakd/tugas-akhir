<?php

namespace App\Http\Controllers\AdminFakultas\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RefFakultas;
use App\Services\Dashboard\RoleDashboardDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menyajikan dashboard Admin Fakultas dengan tampilan dan kontrak data yang seragam.
 */
class DashboardController extends Controller
{
    /**
     * Menampilkan metrik fakultas, ringkasan prodi, dan pratinjau alumni dalam scope fakultas.
     */
    public function tampilkanDashboard(Request $request, RoleDashboardDataService $dashboardData): Response
    {
        $user = Auth::user()->load('fakultas');
        $fakultas = $user->fakultas ?: RefFakultas::find($user->fakultas_id) ?: RefFakultas::first();
        $dashboard = $dashboardData->build('fakultas', $fakultas?->id, $request->string('tahun')->toString());
        $stats = $dashboard['stats'];

        return Inertia::render('AdminFakultas/Dashboard', [
            'user' => $user,
            'fakultas' => $fakultas,
            'availableYears' => $dashboard['availableYears'],
            'selectedYear' => $dashboard['selectedYear'],
            'dashboardStats' => $stats,
            'completionMetrics' => $dashboard['completionMetrics'],
            'prodiSummaries' => $dashboard['prodiSummaries'],
            'recentAlumni' => $dashboard['recentAlumni'],
            'sectionsList' => $dashboard['sectionsList'],
            'stats' => array_merge($stats, [
                'total_alumni' => $stats['total_alumni'],
                'total_selesai' => $dashboard['completionMetrics']['profile_and_university']['completed_count'],
                'total_belum_selesai' => $stats['total_alumni'] - $dashboard['completionMetrics']['profile_and_university']['completed_count'],
                'persentase_selesai' => $dashboard['completionMetrics']['profile_and_university']['percentage'],
                'total_prodi' => $stats['total_prodi'],
                'total_pending_perusahaan' => $stats['total_pending_perusahaan'],
                'alumni_linkedin' => $stats['total_linkedin'],
            ]),
        ]);
    }
}
