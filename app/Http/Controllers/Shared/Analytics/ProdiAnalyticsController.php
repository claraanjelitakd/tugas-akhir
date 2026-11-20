<?php

namespace App\Http\Controllers\Shared\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\ProdiAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Analitik Kuesioner Program Studi untuk SuperAdmin, Biro 3, Fakultas, dan Prodi.
 *
 * Bertanggung jawab menyajikan visualisasi grafik dan data respon kuesioner khusus
 * program studi berbasis seksi dan butir pertanyaan prodi.
 */
class ProdiAnalyticsController extends Controller
{
    /**
     * Menampilkan halaman analitik program studi.
     */
    public function index(Request $request, ProdiAnalyticsService $analyticsService): Response
    {
        $user = $request->user();
        $prodiId = $request->string('kode_prodi')->toString() ?: null;
        if ($user && $user->role === 'admin_prodi' && $user->kode_prodi) {
            $prodiId = $user->kode_prodi;
        }

        $sectionId = $request->input('section_id') ?: null;
        $year = $request->string('tahun')->toString() ?: null;

        return Inertia::render('Shared/Analytics/GrafikProdi', [
            'user' => $user,
            'analytics' => $analyticsService->buildProdiChart($prodiId, $sectionId, $year),
        ]);
    }

    /**
     * Mengembalikan data analitik kuesioner prodi sebagai JSON untuk pergantian filter
     * tanpa memuat ulang halaman Inertia secara penuh.
     */
    public function data(Request $request, ProdiAnalyticsService $analyticsService): JsonResponse
    {
        $user = $request->user();
        $prodiId = $request->string('kode_prodi')->toString() ?: null;
        if ($user && $user->role === 'admin_prodi' && $user->kode_prodi) {
            $prodiId = $user->kode_prodi;
        }

        $sectionId = $request->input('section_id') ?: null;
        $year = $request->string('tahun')->toString() ?: null;

        return response()->json(
            $analyticsService->buildProdiChart($prodiId, $sectionId, $year)
        );
    }
}
