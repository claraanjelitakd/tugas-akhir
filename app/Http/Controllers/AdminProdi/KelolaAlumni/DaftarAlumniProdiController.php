<?php

namespace App\Http\Controllers\AdminProdi\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Mail\PengingatKuesionerAlumniMail;
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
use App\Services\Alumni\AlumniDirectoryQueryService;
use App\Services\Alumni\LinkedinTraceHelper;
use App\Services\Export\AlumniTracerExcelExporter;
use App\Services\Kuesioner\KelengkapanTracerService;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DaftarAlumniProdiController
 *
 * Fungsi:
 * Menampilkan direktori mahasiswa & alumni khusus untuk Program Studi yang login,
 * menggunakan Database View (v_alumni_audit_rekap) dengan kecepatan tinggi tanpa N+1 query.
 */
class DaftarAlumniProdiController extends Controller
{
    /**
     * Tampilkan Direktori Mahasiswa & Alumni Khusus Prodi
     */
    public function index(Request $request, AlumniDirectoryQueryService $directoryService): Response
    {
        $user = Auth::user()->load('prodi');
        $prodi = $user->prodi;
        $prodiId = $user->prodi_id;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Program Studi.');
        }

        $data = $directoryService->getDirectoryData($request, [
            'prodi_id' => $prodiId,
        ]);

        $data['user'] = $user;
        $data['prodi'] = $prodi;

        return Inertia::render('AdminProdi/Alumni/Index', $data);
    }

    /**
     * Tampilkan Halaman Detail Profil, Kuesioner Universitas & Kuesioner Prodi Alumni
     */
    public function show(int $id): Response
    {
        $user = Auth::user()->load('prodi');
        $prodi = $user->prodi;
        $prodiId = $user->prodi_id;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Program Studi.');
        }

        $alumni = Biodata::with([
            'dataAkademik.yudisium',
            'yudisium',
            'perusahaan.propinsi',
            'perusahaan.kabupaten',
            'atasan',
            'user',
            'prodi',
            'latestLinkedinSyncResult.reviewer',
        ])
            ->where('prodi_id', $prodiId)
            ->findOrFail($id);

        KuesionerSyncService::syncProfileResponses($alumni);

        $evaluasi = KelengkapanTracerService::evaluasiKelengkapanTotal($alumni);

        // 1. Kuesioner Universitas
        $savedUnivResponses = Tracer::where('biodata_id', $alumni->id)
            ->get()
            ->keyBy('question_id');

        $kuesionerUniv = Kuesioner::where('is_active', true)
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

        $univSectionsWithAnswers = [];
        if ($kuesionerUniv) {
            foreach ($kuesionerUniv->sections as $section) {
                $subpertanyaansList = [];

                foreach ($section->subpertanyaans as $subpertanyaan) {
                    $resp = $savedUnivResponses->get($subpertanyaan->id);
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

                $unansweredMandatoryCount = count(array_filter($subpertanyaansList, function ($item) {
                    return $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header'];
                }));
                $unansweredOptionalCount = count(array_filter($subpertanyaansList, function ($item) {
                    return ! $item['is_mandatory'] && ! $item['is_answered'] && ! $item['is_header'];
                }));

                $univSectionsWithAnswers[] = [
                    'id' => $section->id,
                    'section' => $section->section,
                    'title' => $section->title ?: $section->section,
                    'order' => $section->order,
                    'subpertanyaans' => $subpertanyaansList,
                    'unanswered_mandatory_count' => $unansweredMandatoryCount,
                    'unanswered_optional_count' => $unansweredOptionalCount,
                ];
            }
        }

        // 2. Kuesioner Khusus Program Studi
        $prodiSections = ProdiQuestionSection::where('prodi_id', $prodiId)
            ->with(['questions' => function ($qQuery) {
                $qQuery->orderBy('order', 'asc')->with('options');
            }])
            ->orderBy('order', 'asc')
            ->get();

        $savedProdiResponses = ProdiResponse::where('biodata_id', $alumni->id)
            ->get()
            ->keyBy('prodi_question_id');

        $prodiSectionsWithAnswers = [];
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

        // 3. Data Form Profil Lengkap
        $formData = AdminAlumniProfileService::buildFormData($alumni);

        $propinsis = Propinsi::orderBy('nama_provinsi', 'asc')->get();
        $kabupatens = Kabupaten::orderBy('nama_kabupaten', 'asc')->get();
        $negaras = RefNegara::orderBy('nama_negara', 'asc')->get();
        $refOptions = AdminAlumniProfileService::getRefOptions();
        $perusahaans = Perusahaan::select('id', 'nama_perusahaan', 'jenis_lokasi', 'negara', 'propinsi_id', 'kabupaten_id', 'alamat', 'kode_pos', 'skala', 'jenis_perusahaan', 'jenis_perusahaan_lainnya', 'status_verifikasi')->get();

        $latestSync = $alumni->latestLinkedinSyncResult;
        $linkedinTraceMapping = LinkedinTraceHelper::buildTraceMapping($alumni, $latestSync);
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

        return Inertia::render('AdminProdi/Alumni/Show', [
            'user' => $user,
            'prodi' => $prodi,
            'biodata' => $alumni,
            'alumni' => $alumni,
            'evaluasi' => $evaluasi,
            'evaluasiAtasan' => $evaluasiAtasanData,
            'pertanyaanEvaluasiAtasan' => $pertanyaanEvaluasiAtasan,
            'sections' => $univSectionsWithAnswers,
            'univSections' => $univSectionsWithAnswers,
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
     * Update Profil Mahasiswa oleh Admin Prodi
     */
    public function updateProfile(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $prodiId = $user->prodi_id;

        $biodata = Biodata::where('prodi_id', $prodiId)->findOrFail($id);

        AdminAlumniProfileService::updateProfile($biodata, $request->all());

        return redirect()->back()->with('success', 'Data profil mahasiswa berhasil diperbarui oleh Admin Program Studi.');
    }

    /**
     * Download Excel (.xls) Semua Butir Pertanyaan & Jawaban per Alumni untuk Prodi
     */
    public function exportExcel(int $id): StreamedResponse
    {
        $user = Auth::user();
        $prodiId = $user->prodi_id;

        // Pastikan alumni berada di prodi yang sama
        Biodata::where('prodi_id', $prodiId)->findOrFail($id);

        return AlumniTracerExcelExporter::download($id);
    }

    /**
     * Kirim Email Pengingat Kuesioner ke Satu Alumni Tertentu (Flash Notifikasi)
     */
    public function sendReminderEmail(Request $request, $id): RedirectResponse
    {
        $user = Auth::user();
        $alumni = Biodata::where('prodi_id', $user->prodi_id)
            ->with(['user', 'dataAkademik'])
            ->findOrFail($id);

        $targetEmail = $alumni->email_pribadi ?: $alumni->user?->email;

        if (! $targetEmail || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', "Gagal: Alumni {$alumni->nama} ({$alumni->nim}) belum memiliki alamat email yang valid di sistem.");
        }

        try {
            Mail::to($targetEmail)->send(new PengingatKuesionerAlumniMail($alumni));
        } catch (\Throwable $e) {
            // Tangani kegagalan SMTP jika offline/sandbox
        }

        return back()->with('success', "Email pengingat kuesioner berhasil dikirim ke {$alumni->nama} ({$targetEmail}).");
    }
}
