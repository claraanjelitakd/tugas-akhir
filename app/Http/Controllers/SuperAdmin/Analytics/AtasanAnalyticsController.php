<?php

namespace App\Http\Controllers\SuperAdmin\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AtasanAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menyajikan grafik dan data pembentuk evaluasi pengguna lulusan dari jawaban atasan.
 */
class AtasanAnalyticsController extends Controller
{
    /**
     * Menampilkan halaman analitik evaluasi atasan.
     */
    public function index(Request $request, AtasanAnalyticsService $analyticsService): Response
    {
        $user = $request->user();
        $prodiId = $request->integer('prodi_id') ?: null;
        if ($user && $user->role === 'admin_prodi' && $user->prodi_id) {
            $prodiId = (int) $user->prodi_id;
        }

        return Inertia::render('SuperAdmin/Analytics/GrafikAtasan', [
            'user' => $user,
            'analytics' => $analyticsService->build(
                $request->string('tahun')->toString() ?: null,
                $prodiId,
            ),
        ]);
    }

    /**
     * Mengembalikan payload JSON saat filter tahun atau program studi berubah.
     */
    public function data(Request $request, AtasanAnalyticsService $analyticsService): JsonResponse
    {
        $user = $request->user();
        $prodiId = $request->integer('prodi_id') ?: null;
        if ($user && $user->role === 'admin_prodi' && $user->prodi_id) {
            $prodiId = (int) $user->prodi_id;
        }

        return response()->json($analyticsService->build(
            $request->string('tahun')->toString() ?: null,
            $prodiId,
        ));
    }
}
