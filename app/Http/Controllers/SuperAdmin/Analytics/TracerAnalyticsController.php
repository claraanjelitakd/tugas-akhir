<?php

namespace App\Http\Controllers\SuperAdmin\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\TracerAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menampilkan analitik Tracer Study tingkat universitas untuk Superadmin.
 */
class TracerAnalyticsController extends Controller
{
    /**
     * Menyusun halaman analitik berdasarkan filter tahun lulusan, tahun UMP,
     * dan program studi yang dipilih.
     */
    public function index(Request $request, TracerAnalyticsService $analyticsService): Response
    {
        $chart = $request->string('grafik')->toString() ?: null;
        $year = $request->string('tahun')->toString() ?: null;
        $umpYear = $request->integer('tahun_ump') ?: null;
        $prodiId = $request->integer('prodi_id') ?: null;

        return Inertia::render('SuperAdmin/Analytics/GrafikUniv', [
            'user' => $request->user(),
            'analytics' => $analyticsService->buildUniversityChart($chart, $year, $umpYear, $prodiId),
        ]);
    }

    /**
     * Mengembalikan satu payload indikator sebagai JSON untuk pergantian filter
     * tanpa melakukan kunjungan ulang halaman Inertia.
     */
    public function data(Request $request, TracerAnalyticsService $analyticsService): JsonResponse
    {
        $chart = $request->string('grafik')->toString() ?: null;
        $year = $request->string('tahun')->toString() ?: null;
        $umpYear = $request->integer('tahun_ump') ?: null;
        $prodiId = $request->integer('prodi_id') ?: null;

        return response()->json(
            $analyticsService->buildUniversityChart($chart, $year, $umpYear, $prodiId)
        );
    }
}
