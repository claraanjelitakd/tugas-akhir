<?php

namespace App\Http\Controllers\SuperAdmin\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Models\Atasan;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\Kabupaten;
use App\Models\Kuesioner;
use App\Models\LinkedinSyncResult;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\Perusahaan;
use App\Models\ProdiQuestionSection;
use App\Models\ProdiResponse;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Models\Tracer;
use App\Services\Alumni\AdminAlumniProfileService;
use App\Services\Alumni\LinkedinTraceHelper;
use App\Services\Export\AlumniTracerExcelExporter;
use App\Services\Kuesioner\KelengkapanTracerService;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DetailAlumniSuperAdminController
 *
 * Fungsi:
 * Menampilkan halaman detail mandiri untuk profil mahasiswa/alumni sekaligus audit jawaban
 * kuesioner tracer study bagi Super Admin.
 *
 * Fitur:
 * 1. Rekap profil lengkap (Data Pribadi, Data Akademik, Yudisium, Orang Tua, Perusahaan, Atasan).
 * 2. Form lengkap pengeditan profil oleh Super Admin untuk membantu alumni memperbarui datanya.
 * 3. Tabulasi jawaban kuesioner tracer study per section dengan hitungan belum dijawab.
 * 4. Indikator butir pertanyaan Wajib vs Opsional secara visual.
 * 5. Tab 4: Trace pemetaan hasil scraping LinkedIn (Apify mentah vs target database vs nilai aktual) dan histori staging.
 */
class DetailAlumniSuperAdminController extends Controller
{
    /**
     * Tampilkan Halaman Detail Profil & Kuesioner Alumni
     *
     * @param  int|string  $id
     * @return Response
     */
    public function show($id)
    {
        $alumni = Biodata::with([
            'dataAkademik.yudisium',
            'dataAkademik.orangTua',
            'yudisium',
            'orangTua',
            'perusahaan.propinsi',
            'perusahaan.kabupaten',
            'atasan',
            'user',
            'prodi',
            'latestLinkedinSyncResult.reviewer',
        ])->findOrFail($id);

        // Pastikan respon profil tersinkron ke tabel responses
        KuesionerSyncService::syncProfileResponses($alumni);

        // Ambil evaluasi kelengkapan terpadu
        $evaluasi = KelengkapanTracerService::evaluasiKelengkapanTotal($alumni);

        // Ambil seluruh jawaban kuesioner alumni
        $savedResponses = Tracer::where('biodata_id', $alumni->id)
            ->get()
            ->keyBy('question_id');

        // Ambil seluruh section dan pertanyaan dari kuesioner aktif (Seksi 1 Identitas dikelola di Tab 1 Detail Profile)
        $kuesioner = Kuesioner::where('is_active', true)
            ->with(['sections' => function ($secQuery) {
                $secQuery->where(function ($sq) {
                    $sq->whereNull('kode_kelompok')
                        ->orWhere('kode_kelompok', '!=', '1');
                })
                    ->orderBy('order', 'asc')
                    ->with(['subpertanyaans' => function ($qQuery) {
                        $qQuery->where(function ($w) {
                            $w->whereNull('tampil_di')
                                ->orWhereNotIn('tampil_di', ['profil', 'profile']);
                        })->where(function ($w) {
                            $w->whereNull('kelompok')
                                ->orWhere('kelompok', '!=', 'BIO');
                        })
                            ->orderBy('order', 'asc')
                            ->with('detils');
                    }]);
            }])
            ->first();

        // Susun struktur data section & jawaban terformat untuk Frontend
        $sectionsWithAnswers = [];

        if ($kuesioner) {
            foreach ($kuesioner->sections as $section) {
                $subpertanyaansList = [];

                foreach ($section->subpertanyaans as $subpertanyaan) {
                    $resp = $savedResponses->get($subpertanyaan->id);
                    $isMandatory = KelengkapanTracerService::isMandatoryQuestion($subpertanyaan->kode_pertanyaan);

                    $hasAnswer = false;
                    $displayAnswer = null;

                    if ($resp) {
                        if (! empty($resp->answer_text) && trim((string) $resp->answer_text) !== '') {
                            $hasAnswer = true;
                            $displayAnswer = (string) $resp->answer_text;
                        } elseif (! empty($resp->answer) && trim((string) $resp->answer) !== '') {
                            $hasAnswer = true;
                            $displayAnswer = (string) $resp->answer;
                        } elseif (is_array($resp->answer_json) && count($resp->answer_json) > 0) {
                            $hasAnswer = true;
                            $displayAnswer = implode(', ', $resp->answer_json);
                        }
                    }

                    $isHeader = in_array($subpertanyaan->type, ['header', 'section_header']);

                    $subpertanyaansList[] = [
                        'id' => $subpertanyaan->id,
                        'kode_pertanyaan' => $subpertanyaan->kode_pertanyaan,
                        'subpertanyaan' => $subpertanyaan->subpertanyaan,
                        'type' => $subpertanyaan->type,
                        'kelompok' => $subpertanyaan->kelompok,
                        'is_mandatory' => $isMandatory,
                        'is_header' => $isHeader,
                        'is_answered' => $hasAnswer,
                        'answer' => $displayAnswer,
                        'answer_json' => $resp?->answer_json,
                        'detils' => $subpertanyaan->detils,
                    ];
                }

                // Hitung berapa pertanyaan wajib dan opsional di section ini yang belum dijawab
                $unansweredMandatoryCount = count(array_filter($subpertanyaansList, function ($item) {
                    return $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header'];
                }));

                $unansweredOptionalCount = count(array_filter($subpertanyaansList, function ($item) {
                    return ! $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header'];
                }));

                $answeredCount = count(array_filter($subpertanyaansList, function ($item) {
                    return $item['is_answered'] && ! $item['is_header'];
                }));

                $totalQuestionsCount = count(array_filter($subpertanyaansList, function ($item) {
                    return ! $item['is_header'];
                }));

                $sectionsWithAnswers[] = [
                    'id' => $section->id,
                    'section' => $section->section,
                    'title' => $section->title ?: $section->section,
                    'order' => $section->order,
                    'subpertanyaans' => $subpertanyaansList,
                    'unanswered_mandatory_count' => $unansweredMandatoryCount,
                    'unanswered_optional_count' => $unansweredOptionalCount,
                    'answered_count' => $answeredCount,
                    'total_questions_count' => $totalQuestionsCount,
                ];
            }
        }

        // Susun Data Form Profil Lengkap menggunakan AdminAlumniProfileService
        $formData = AdminAlumniProfileService::buildFormData($alumni);

        // 7. Muat Kuesioner Khusus Program Studi jika alumni terafiliasi prodi
        $prodiSectionsWithAnswers = [];
        $prodiEvaluasi = [
            'is_complete' => false,
            'percentage' => 0,
            'answered_count' => 0,
            'total_questions' => 0,
        ];

        if ($alumni->prodi_id) {
            $prodiSections = ProdiQuestionSection::where('prodi_id', $alumni->prodi_id)
                ->with(['questions' => function ($qQuery) {
                    $qQuery->orderBy('order', 'asc')->with('options');
                }])
                ->orderBy('order', 'asc')
                ->get();

            $savedProdiResponses = ProdiResponse::where('biodata_id', $alumni->id)
                ->get()
                ->keyBy('prodi_question_id');

            $totalProdiQuestions = 0;
            $totalProdiAnswered = 0;

            foreach ($prodiSections as $pSection) {
                $pQuestionsList = [];

                foreach ($pSection->questions as $pQuestion) {
                    $isHeader = in_array($pQuestion->type, ['header', 'section_header']);
                    $pResp = $savedProdiResponses->get($pQuestion->id);

                    $hasAnswer = false;
                    $displayAnswer = null;

                    if ($pResp && ! $isHeader) {
                        if (! empty($pResp->answer_text) && trim((string) $pResp->answer_text) !== '') {
                            $hasAnswer = true;
                            $displayAnswer = (string) $pResp->answer_text;
                        } elseif (is_array($pResp->answer_json) && count($pResp->answer_json) > 0) {
                            $hasAnswer = true;
                            $displayAnswer = implode(', ', $pResp->answer_json);
                        }
                    }

                    if (! $isHeader) {
                        $totalProdiQuestions++;
                        if ($hasAnswer) {
                            $totalProdiAnswered++;
                        }
                    }

                    $pQuestionsList[] = [
                        'id' => $pQuestion->id,
                        'code' => $pQuestion->code,
                        'question_text' => $pQuestion->question_text,
                        'type' => $pQuestion->type,
                        'is_header' => $isHeader,
                        'is_mandatory' => (bool) $pQuestion->is_required,
                        'is_answered' => $hasAnswer,
                        'answer_text' => $displayAnswer,
                        'options' => $pQuestion->options->map(function ($opt) {
                            return [
                                'id' => $opt->id,
                                'text' => $opt->option_text,
                            ];
                        }),
                    ];
                }

                $unansweredProdiCount = count(array_filter($pQuestionsList, function ($item) {
                    return $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header'];
                }));

                $prodiSectionsWithAnswers[] = [
                    'id' => $pSection->id,
                    'title' => $pSection->title,
                    'description' => $pSection->description,
                    'order' => $pSection->order,
                    'questions' => $pQuestionsList,
                    'unanswered_mandatory_count' => $unansweredProdiCount,
                ];
            }

            $prodiPercentage = $totalProdiQuestions > 0
                ? round(($totalProdiAnswered / $totalProdiQuestions) * 100)
                : 0;

            $prodiEvaluasi = [
                'is_complete' => ($totalProdiQuestions > 0 && $totalProdiAnswered >= $totalProdiQuestions),
                'percentage' => $prodiPercentage,
                'answered_count' => $totalProdiAnswered,
                'total_questions' => $totalProdiQuestions,
            ];
        }

        $propinsis = Propinsi::orderBy('nama_provinsi', 'asc')->get();
        $kabupatens = Kabupaten::orderBy('nama_kabupaten', 'asc')->get();
        $negaras = RefNegara::orderBy('nama_negara', 'asc')->get();
        $refOptions = AdminAlumniProfileService::getRefOptions();
        $perusahaans = Perusahaan::select('id', 'nama_perusahaan', 'jenis_lokasi', 'negara', 'propinsi_id', 'kabupaten_id', 'alamat', 'kode_pos', 'skala', 'jenis_perusahaan', 'jenis_perusahaan_lainnya', 'status_verifikasi')->get();

        $latestSync = $alumni->latestLinkedinSyncResult;
        $linkedinTraceMapping = $this->buildLinkedinTraceMapping($alumni, $latestSync);
        $linkedinHistory = LinkedinSyncResult::where('biodata_id', $alumni->id)
            ->with('reviewer')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        // Ambil Data Evaluasi Atasan (Pengguna Lulusan)
        $evaluasiAtasan = EvaluasiAtasan::where('biodata_id', $alumni->id)
            ->with(['atasan', 'perusahaan.kabupaten', 'perusahaan.propinsi', 'respons.pertanyaan'])
            ->latest('updated_at')
            ->first();

        $evaluasiAtasanData = $evaluasiAtasan?->toAdminAuditArray();

        $pertanyaanEvaluasiAtasan = PertanyaanEvaluasiAtasan::where('is_active', true)
            ->orderBy('order', 'asc')
            ->get();

        return Inertia::render('SuperAdmin/Alumni/Show', [
            'biodata' => $alumni,
            'alumni' => $alumni,
            'evaluasi' => $evaluasi,
            'evaluasiAtasan' => $evaluasiAtasanData,
            'pertanyaanEvaluasiAtasan' => $pertanyaanEvaluasiAtasan,
            'sections' => $sectionsWithAnswers,
            'prodiSections' => $prodiSectionsWithAnswers,
            'prodiEvaluasi' => $prodiEvaluasi,
            'formData' => $formData,
            'propinsis' => $propinsis,
            'provinces' => $propinsis,
            'kabupatens' => $kabupatens,
            'negaras' => $negaras,
            'refOptions' => $refOptions,
            'perusahaans' => $perusahaans,
            'companies' => $perusahaans,
            'linkedinSyncResult' => $latestSync,
            'linkedinTraceMapping' => $linkedinTraceMapping,
            'linkedinHistory' => $linkedinHistory,
        ]);
    }

    /**
     * Membangun Baris Pemetaan Audit Trace Hasil Scraping LinkedIn ke Basis Data
     */
    public function buildLinkedinTraceMapping(Biodata $alumni, ?LinkedinSyncResult $syncResult): array
    {
        return LinkedinTraceHelper::buildTraceMapping($alumni, $syncResult);
    }

    /**
     * Memperbarui Data Profil Alumni oleh Super Admin
     *
     * @param  int|string  $id
     * @return RedirectResponse
     */
    public function updateProfile(Request $request, $id)
    {
        $biodata = Biodata::findOrFail($id);
        AdminAlumniProfileService::updateProfile($biodata, $request->all());

        return redirect()->back()->with('success', 'Data profil mahasiswa berhasil diperbarui oleh Super Admin.');
    }

    /**
     * Download Excel / CSV Semua Butir Pertanyaan & Jawaban per Alumni
     *
     * @param  int|string  $id
     * @return StreamedResponse
     */
    /**
     * Download Excel (.xls) Semua Butir Pertanyaan & Jawaban per Alumni
     *
     * @param  int|string  $id
     * @return StreamedResponse
     */
    public function exportExcel($id)
    {
        return AlumniTracerExcelExporter::download($id);
    }
}
