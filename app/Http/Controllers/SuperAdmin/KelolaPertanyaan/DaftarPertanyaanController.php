<?php

namespace App\Http\Controllers\SuperAdmin\KelolaPertanyaan;

use App\Http\Controllers\Controller;
use App\Models\Kuesioner;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Prodi;
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
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // 1. Ambil seluruh section kuesioner universitas berurutan
        $univSections = KuesionerSection::where('target', 'univ')
            ->orderBy('order', 'asc')
            ->get();

        $sections = $univSections->map(function ($sec) {
            return [
                'id' => $sec->id,
                'kuesioner_id' => 1,
                'kode_kelompok' => str_replace('SEC_UNIV_', '', $sec->kode_section),
                'title' => $sec->judul,
                'description' => $sec->deskripsi,
                'order' => (int) $sec->order,
                'subpertanyaans_count' => KuesionerSectionPertanyaan::where('section_id', $sec->id)->count(),
                'kuesioner' => [
                    'id' => 1,
                    'title' => 'Tracer Study UKDW',
                    'year' => now()->year,
                ],
            ];
        });

        // 2. Ambil seluruh pertanyaan universitas beserta relasi section dan opsi
        $sectionPertanyaans = KuesionerSectionPertanyaan::whereIn('section_id', $univSections->pluck('id'))
            ->orderBy('order', 'asc')
            ->get();

        $pertanyaanIds = $sectionPertanyaans->pluck('pertanyaan_id')->unique();
        $pertanyaans = KuesionerPertanyaan::whereIn('id', $pertanyaanIds)
            ->with(['opsi' => fn ($q) => $q->orderBy('order', 'asc')])
            ->get()
            ->keyBy('id');

        $univSectionsById = $univSections->keyBy('id');

        $subpertanyaans = $sectionPertanyaans->map(function ($sp) use ($pertanyaans, $univSectionsById) {
            $p = $pertanyaans->get($sp->pertanyaan_id);
            if (! $p) {
                return null;
            }
            $sec = $univSectionsById->get($sp->section_id);

            return [
                'id' => $p->id,
                'pertanyaan_id' => $p->id,
                'section_id' => $sp->section_id,
                'kode_pertanyaan' => $p->kode_pertanyaan,
                'subpertanyaan' => $p->teks_pertanyaan,
                'type' => $p->tipe,
                'wajib' => (bool) $sp->wajib,
                'order' => (int) $sp->order,
                'tampil_di' => $sp->tampil_di ?? 'kuesioner',
                'keterangan' => $p->keterangan,
                'is_active' => (bool) $p->is_active,
                'section' => $sec ? [
                    'id' => $sec->id,
                    'title' => $sec->judul,
                    'kode_kelompok' => str_replace('SEC_UNIV_', '', $sec->kode_section),
                    'order' => (int) $sec->order,
                    'kuesioner' => [
                        'id' => 1,
                        'title' => 'Tracer Study UKDW',
                        'year' => now()->year,
                    ],
                ] : null,
                'detils' => $p->opsi->map(fn ($o) => [
                    'id' => $o->id,
                    'kode_pertanyaan' => $p->kode_pertanyaan,
                    'kode_opsi' => $p->kode_pertanyaan.'-'.str_pad((string) $o->order, 2, '0', STR_PAD_LEFT),
                    'option_text' => $o->label,
                    'score' => $o->nilai_skor,
                    'jump_to' => $o->lompat_ke_pertanyaan_id,
                    'order' => (int) $o->order,
                ])->values(),
            ];
        })->filter()->values();

        // 3. Ambil daftar kuesioner induk
        $kuesioners = Kuesioner::orderBy('year', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'title', 'description', 'year', 'is_active'])
            ->map(function ($k) use ($sections) {
                $kArr = $k->toArray();
                $kArr['sections_count'] = $sections->count();

                return $kArr;
            });

        // 4. Ambil daftar program studi aktif
        $prodis = Prodi::orderBy('kode_prodi', 'asc')->get(['id', 'kode_prodi', 'nama_prodi']);

        // 5. Map kode pertanyaan ke teks pertanyaan untuk label percabangan jump_to
        $targetQuestionMap = $pertanyaans->pluck('teks_pertanyaan', 'kode_pertanyaan')->toArray();

        // 6. Susun daftar pilihan target lompatan untuk dropdown dan pemilih bertingkat (grouped by section)
        $availableJumpTargets = $subpertanyaans->map(function ($q) {
            $sectionTitle = ! empty($q['section'])
                ? 'Kelompok '.$q['section']['order'].': '.$q['section']['title']
                : 'Pertanyaan Lainnya / Tanpa Section';

            return [
                'id' => $q['id'],
                'kode_pertanyaan' => $q['kode_pertanyaan'],
                'label' => $q['kode_pertanyaan'].' — '.Str::limit($q['subpertanyaan'], 65),
                'text' => $q['subpertanyaan'],
                'section_id' => $q['section_id'],
                'section_title' => $sectionTitle,
                'section_order' => $q['section']['order'] ?? 999,
            ];
        });

        // 7. Ambil kuesioner aktif
        $activeKuesioner = $kuesioners->firstWhere('is_active', true) ?? $kuesioners->first();

        // 8. Bank Pertanyaan terpadu (lintas prodi, universitas, & evaluasi atasan yang aktif)
        $existingUnivQuestionIds = $subpertanyaans->pluck('id')->all();
        $bankQuestions = KuesionerPertanyaan::query()
            ->where('is_active', true)
            ->with([
                'pemilikProdi:id,nama_prodi,kode_prodi',
                'sections:id,target,prodi_id,judul',
                'opsi' => fn ($q) => $q->orderBy('order'),
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (KuesionerPertanyaan $p) use ($existingUnivQuestionIds) {
                $isAtasan = str_starts_with($p->kode_pertanyaan, 'EVA_')
                    || $p->sections->contains(fn ($s) => $s->target === 'atasan');

                if ($isAtasan) {
                    $asal = 'Evaluasi Atasan';
                } elseif ($p->pemilikProdi) {
                    $asal = $p->pemilikProdi->nama_prodi;
                } else {
                    $asal = 'Universitas (Pusat)';
                }

                return [
                    'id' => $p->id,
                    'kode' => $p->kode_pertanyaan,
                    'teks' => $p->teks_pertanyaan,
                    'tipe' => $p->tipe,
                    'asal' => $asal,
                    'is_already_added' => in_array($p->id, $existingUnivQuestionIds, true),
                    'options' => $p->opsi->map(fn ($o) => [
                        'label' => $o->label,
                        'score' => $o->nilai_skor,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        $totalTracerQuestions = $subpertanyaans->filter(function ($q) {
            return $q['tampil_di'] !== 'profil' && $q['tampil_di'] !== 'profile';
        })->count();

        $profileCodes = array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES);

        $totalProfileQuestions = $subpertanyaans->filter(function ($q) use ($profileCodes) {
            $codeUpper = strtoupper($q['kode_pertanyaan'] ?? '');

            return in_array($codeUpper, $profileCodes, true)
                || $q['tampil_di'] === 'profil'
                || $q['tampil_di'] === 'profile'
                || $q['tampil_di'] === 'both';
        })->count();

        // 9. Render komponen Vue SuperAdmin/Pertanyaan/Index
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
            'bankQuestions' => $bankQuestions,
            'initialTab' => $request->query('tab', 'sections'),
            'stats' => [
                'total_kuesioners' => $kuesioners->count(),
                'total_sections' => $sections->count(),
                'total_tracer_questions' => $totalTracerQuestions,
                'total_profile_questions' => $totalProfileQuestions,
                'total_questions' => $subpertanyaans->count(),
                'total_bank_questions' => count($bankQuestions),
            ],
        ]);
    }
}
