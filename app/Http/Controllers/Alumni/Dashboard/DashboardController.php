<?php

namespace App\Http\Controllers\Alumni\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\ProdiQuestion;
use App\Models\ProdiResponse;
use App\Services\Kuesioner\KelengkapanTracerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * DashboardController
 *
 * Fungsi: Menampilkan halaman utama (Dashboard) untuk pengguna dengan peran (role) Alumni.
 * Tujuan: Menyajikan ringkasan aktivitas alumni, persentase kelengkapan profil biodata dan kuesioner tracer study.
 */
class DashboardController extends Controller
{
    /**
     * Menampilkan Halaman Dashboard Alumni
     *
     * Hubungan dengan Frontend:
     * Method ini adalah pasangan dari file: resources/js/Pages/Alumni/Dashboard.vue
     * Data yang ada di dalam array Inertia::render(...) akan otomatis diterima oleh Dashboard.vue
     */
    public function tampilkanDashboard(Request $request)
    {
        $user = $request->user();
        $biodata = Biodata::where('user_id', $user->id)->first();

        $profilePercentage = 0;
        $profileCompleted = false;
        $profileFilledCount = 0;
        $profileTotalCount = 23;
        $profileMissingFields = [];

        $questionnairePercentage = 0;
        $questionnaireCompleted = false;
        $questionnaireAnsweredCount = 0;
        $questionnaireTotalCount = 61;
        $questionnaireMissing = [];

        $prodiQuestionsCount = 0;
        $prodiAnsweredCount = 0;
        $prodiCompleted = false;

        if ($biodata) {
            $biodata->load(['prodi.fakultas', 'dataAkademik', 'yudisium', 'perusahaan', 'atasan']);
            $eval = KelengkapanTracerService::evaluasiKelengkapanTotal($biodata);

            $profilePercentage = $eval['profile']['percentage'] ?? 0;
            $profileCompleted = $eval['profile']['is_complete'] ?? false;
            $profileMissingFields = $eval['profile']['missing_fields'] ?? [];
            $profileTotalCount = $eval['profile']['total_fields'] ?? 30;
            $profileFilledCount = $eval['profile']['filled_count'] ?? max(0, $profileTotalCount - count($profileMissingFields));

            $questionnairePercentage = $eval['questionnaire']['percentage'] ?? 0;
            $questionnaireCompleted = $eval['questionnaire']['is_complete'] ?? false;
            $questionnaireAnsweredCount = $eval['questionnaire']['answered_count'] ?? 0;
            $questionnaireTotalCount = $eval['questionnaire']['total_mandatory'] ?? 61;
            $questionnaireMissing = $eval['questionnaire']['missing_questions'] ?? [];

            // Evaluasi Kuesioner Program Studi
            if ($biodata->prodi_id) {
                $prodiQIds = ProdiQuestion::where('prodi_id', $biodata->prodi_id)
                    ->where('type', '!=', 'header')
                    ->pluck('id');
                $prodiQuestionsCount = $prodiQIds->count();
                if ($prodiQuestionsCount > 0) {
                    $prodiAnsweredCount = ProdiResponse::where('biodata_id', $biodata->id)
                        ->whereIn('prodi_question_id', $prodiQIds)
                        ->where(function ($q) {
                            $q->where(function ($sub) {
                                $sub->whereNotNull('answer_text')
                                    ->where(DB::raw('TRIM(answer_text)'), '!=', '');
                            })->orWhere(function ($sub) {
                                $sub->whereNotNull('answer_json')
                                    ->where('answer_json', '!=', '')
                                    ->where('answer_json', '!=', '[]')
                                    ->where('answer_json', '!=', '{}');
                            });
                        })
                        ->distinct('prodi_question_id')
                        ->count('prodi_question_id');
                    $prodiCompleted = ($prodiAnsweredCount >= $prodiQuestionsCount);
                }
            }
        }

        $prodiPercentage = $prodiQuestionsCount > 0
            ? (int) round(($prodiAnsweredCount / $prodiQuestionsCount) * 100)
            : null;

        $stages = [$profilePercentage, $questionnairePercentage];
        if ($prodiQuestionsCount > 0) {
            $stages[] = $prodiPercentage;
        }
        $overallPercentage = (int) round(array_sum($stages) / count($stages));
        $isOverallComplete = $profileCompleted && $questionnaireCompleted && ($prodiQuestionsCount === 0 || $prodiCompleted);

        $evaluasiAtasan = $biodata ? EvaluasiAtasan::where('biodata_id', $biodata->id)->first() : null;
        $supervisorName = $biodata?->atasan?->nama ?? $biodata?->nama_atasan;
        $supervisorEmail = $biodata?->atasan?->email ?? $biodata?->email_atasan;
        $companyName = $biodata?->perusahaan?->nama_perusahaan ?? $biodata?->nama_perusahaan;
        $hasSupervisor = ! empty($supervisorEmail);
        $evaluasiSubmitted = (bool) ($evaluasiAtasan?->is_submitted ?? false);

        return Inertia::render('Alumni/Dashboard', [
            'user' => $user,
            'biodata' => $biodata,
            'alumni' => $biodata,
            'profilePercentage' => $profilePercentage,
            'profileCompleted' => $profileCompleted,
            'profileFilledCount' => $profileFilledCount,
            'profileTotalCount' => $profileTotalCount,
            'profileMissingFields' => $profileMissingFields,
            'questionnairePercentage' => $questionnairePercentage,
            'questionnaireCompleted' => $questionnaireCompleted,
            'questionnaireAnsweredCount' => $questionnaireAnsweredCount,
            'questionnaireTotalCount' => $questionnaireTotalCount,
            'questionnaireMissing' => $questionnaireMissing,
            'prodiQuestionsCount' => $prodiQuestionsCount,
            'prodiAnsweredCount' => $prodiAnsweredCount,
            'prodiCompleted' => $prodiCompleted,
            'prodiPercentage' => $prodiPercentage,
            'overallPercentage' => $overallPercentage,
            'isOverallComplete' => $isOverallComplete,
            'hasSupervisor' => $hasSupervisor,
            'evaluasiSubmitted' => $evaluasiSubmitted,
            'evaluasiToken' => $evaluasiAtasan?->token,
            'supervisorEmail' => $supervisorEmail,
            'supervisorName' => $supervisorName,
            'companyName' => $companyName,
        ]);
    }
}
