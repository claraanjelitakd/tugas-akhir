<?php

namespace App\Http\Controllers\SuperAdmin\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KelompokPertanyaan;
use App\Models\Kuesioner;
use App\Models\Prodi;
use App\Models\RefSubpertanyaan2021;
use App\Services\Kuesioner\KelengkapanTracerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Class DaftarPertanyaanController
 *
 * Fungsi:
 * Controller terpadu untuk instrumen Kuesioner Universitas (Tracer Study Dikti).
 * Menampilkan dan mengelola:
 * 1. Struktur Bagian / Seksi (Sections)
 * 2. Butir Pertanyaan & Opsi Jawaban (Questions)
 * 3. Kuesioner Induk Tracer Study (Questionnaires)
 */
class DaftarPertanyaanController extends Controller
{
    /**
     * Menampilkan halaman utama Kelola Kuesioner Universitas terpadu.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Ambil seluruh pertanyaan beserta relasi section dan opsi
        $subpertanyaans = RefSubpertanyaan2021::with(['kelompokPertanyaan.kuesioner', 'detils'])
            ->orderBy('order', 'asc')
            ->get();

        // 2. Ambil seluruh section kuesioner berurutan beserta relasi dan jumlah pertanyaan
        $sections = KelompokPertanyaan::with('kuesioner')
            ->withCount('subpertanyaans')
            ->orderBy('order', 'asc')
            ->get();

        // 3. Ambil daftar kuesioner induk
        $kuesioners = Kuesioner::withCount('kelompokPertanyaan')
            ->orderBy('year', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'title', 'description', 'year', 'is_active']);

        // 4. Ambil daftar program studi aktif
        $prodis = Prodi::orderBy('kode_prodi', 'asc')->get(['id', 'kode_prodi', 'nama_prodi']);

        // 5. Map kode pertanyaan ke teks pertanyaan untuk label percabangan jump_to
        $targetQuestionMap = RefSubpertanyaan2021::pluck('subpertanyaan', 'kode_pertanyaan')->toArray();

        // 6. Susun daftar pilihan target lompatan untuk dropdown dan pemilih bertingkat (grouped by section)
        $availableJumpTargets = RefSubpertanyaan2021::with('kelompokPertanyaan')
            ->orderBy('order', 'asc')
            ->get()
            ->map(function ($q) {
                // Tentukan judul section untuk pengelompokan di dropdown bertingkat
                $sectionTitle = $q->kelompokPertanyaan
                    ? 'Kelompok '.$q->kelompokPertanyaan->order.': '.$q->kelompokPertanyaan->title
                    : 'Pertanyaan Lainnya / Tanpa Section';

                return [
                    'id' => $q->id,
                    'kode_pertanyaan' => $q->kode_pertanyaan,
                    'label' => $q->kode_pertanyaan.' — '.Str::limit($q->subpertanyaan, 65),
                    'text' => $q->subpertanyaan,
                    'kelompok_pertanyaan_id' => $q->kelompok_pertanyaan_id,
                    'section_title' => $sectionTitle,
                    'section_order' => $q->kelompokPertanyaan?->order ?? 999,
                ];
            });

        // 7. Ambil kuesioner aktif
        $activeKuesioner = $kuesioners->firstWhere('is_active', true) ?? $kuesioners->first();

        $totalTracerQuestions = $subpertanyaans->filter(function ($q) {
            return $q->tampil_di !== 'profil' && $q->tampil_di !== 'profile';
        })->count();

        $profileCodes = array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES);

        $totalProfileQuestions = $subpertanyaans->filter(function ($q) use ($profileCodes) {
            $codeUpper = strtoupper($q->kode_pertanyaan ?? '');

            return in_array($codeUpper, $profileCodes, true)
                || $q->tampil_di === 'profil'
                || $q->tampil_di === 'profile'
                || $q->tampil_di === 'both';
        })->count();

        // 8. Render komponen Vue SuperAdmin/Pertanyaan/Index
        return Inertia::render('SuperAdmin/Pertanyaan/Index', [
            'user' => $user,
            'subpertanyaans' => $subpertanyaans,
            'sections' => $sections,
            'kuesioners' => $kuesioners,
            'activeKuesioner' => $activeKuesioner,
            'prodis' => $prodis,
            'availableJumpTargets' => $availableJumpTargets,
            'targetQuestionMap' => $targetQuestionMap,
            'profileManagedCodes' => $profileCodes,
            'initialTab' => $request->query('tab', 'sections'),
            'stats' => [
                'total_kuesioners' => $kuesioners->count(),
                'total_sections' => $sections->count(),
                'total_tracer_questions' => $totalTracerQuestions,
                'total_profile_questions' => $totalProfileQuestions,
                'total_questions' => $subpertanyaans->count(),
            ],
        ]);
    }
}
