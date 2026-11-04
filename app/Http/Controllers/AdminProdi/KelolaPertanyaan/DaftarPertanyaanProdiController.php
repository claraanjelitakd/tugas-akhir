<?php

namespace App\Http\Controllers\AdminProdi\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller untuk menampilkan daftar butir pertanyaan kuesioner khusus Program Studi yang sedang login.
 */
class DaftarPertanyaanProdiController extends Controller
{
    /**
     * Menampilkan halaman utama Kelola Pertanyaan Program Studi.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user()->load('prodi');
        $prodi = $user->prodi;
        $prodiId = $user->prodi_id;

        // Ambil seluruh section kuesioner prodi ini
        $sections = $prodiId
            ? KuesionerSection::where('target', 'prodi')
                ->where('prodi_id', $prodiId)
                ->withCount('sectionPertanyaan as questions_count')
                ->orderBy('order', 'asc')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->judul,
                    'description' => $s->deskripsi,
                    'order' => $s->order,
                    'questions_count' => $s->questions_count,
                ])
            : collect();

        // Ambil seluruh pertanyaan khusus prodi ini beserta relasi section dan options
        $rawQuestions = $prodiId
            ? KuesionerPertanyaan::where('pemilik_prodi_id', $prodiId)
                ->with(['sections', 'opsi'])
                ->orderBy('id', 'asc')
                ->get()
            : collect();

        $questions = $rawQuestions->map(function ($q) {
            $section = $q->sections->first();
            $pivot = $section?->pivot;

            return [
                'id' => $q->id,
                'prodi_id' => $q->pemilik_prodi_id,
                'prodi_question_section_id' => $section?->id,
                'code' => $q->kode_pertanyaan,
                'question_text' => $q->teks_pertanyaan,
                'type' => $q->tipe,
                'is_required' => (bool) ($pivot?->wajib ?? true),
                'order' => $pivot?->order ?? 1,
                'section' => $section ? [
                    'id' => $section->id,
                    'title' => $section->judul,
                    'description' => $section->deskripsi,
                    'order' => $section->order,
                ] : null,
                'options' => $q->opsi->map(fn ($opt) => [
                    'id' => $opt->id,
                    'prodi_question_id' => $opt->pertanyaan_id,
                    'code' => $opt->kode_opsi,
                    'option_text' => $opt->label,
                    'score' => $opt->nilai_skor,
                    'jump_to' => $opt->lompat_ke_pertanyaan_id,
                    'order' => $opt->order,
                ])->values(),
            ];
        })->sortBy('order')->values();

        // Ambil kode pertanyaan yang tersedia untuk target lompatan (jump_to)
        $availableJumpTargets = $questions->pluck('code')->filter()->values();

        return Inertia::render('AdminProdi/Pertanyaan/Index', [
            'user' => $user,
            'prodi' => $prodi,
            'questions' => $questions,
            'sections' => $sections,
            'availableJumpTargets' => $availableJumpTargets,
        ]);
    }
}
