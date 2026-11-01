<?php

namespace App\Http\Controllers\SuperAdmin\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\ProdiAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Analitik Kuesioner Program Studi untuk Superadmin.
 *
 * Bertanggung jawab menyajikan visualisasi grafik dan data respon kuesioner khusus
 * program studi (default Sistem Informasi) berbasis seksi dan butir pertanyaan prodi.
 */
class ProdiAnalyticsController extends Controller
{
    /**
     * Menampilkan halaman analitik program studi.
     */
    public function index(Request $request, ProdiAnalyticsService $analyticsService): Response
    {
        $prodiId = $request->integer('prodi_id') ?: null;
        $sectionId = $request->input('section_id') ?: null;
        $year = $request->string('tahun')->toString() ?: null;

        return Inertia::render('SuperAdmin/Analytics/GrafikProdi', [
            'user' => $request->user(),
            'analytics' => $analyticsService->buildProdiChart($prodiId, $sectionId, $year),
        ]);
    }

    /**
     * Mengembalikan data analitik kuesioner prodi sebagai JSON untuk pergantian filter
     * tanpa memuat ulang halaman Inertia secara penuh.
     */
    public function data(Request $request, ProdiAnalyticsService $analyticsService): JsonResponse
    {
        $prodiId = $request->integer('prodi_id') ?: null;
        $sectionId = $request->input('section_id') ?: null;
        $year = $request->string('tahun')->toString() ?: null;

        return response()->json(
            $analyticsService->buildProdiChart($prodiId, $sectionId, $year)
        );
    }
}
