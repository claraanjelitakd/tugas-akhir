<?php

namespace App\Services\Kuesioner;

use App\Models\AlumniProfileSummary;
use App\Models\Biodata;
use App\Models\RefSubpertanyaan2021;
use App\Models\Tracer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KelengkapanTracerService
 *
 * Fungsi:
 * Mengaudit status kelengkapan data seorang alumni secara komprehensif, mencakup:
 * 1. Kelengkapan Profil (Data Pribadi, Data Akademik, Data Perusahaan, Data Atasan, dan Posisi Jabatan).
 * 2. Kelengkapan Kuesioner Wajib (Ditentukan secara dinamis berdasarkan kolom `wajib = 1` di tabel `ref_subpertanyaan2021`).
 *
 * Pertanyaan dengan `wajib = 0` bersifat OPSIONAL dan tidak membatalkan kelengkapan status alumni.
 */
class KelengkapanTracerService
{
    /**
     * Kode pertanyaan yang dikelola di modul Profil (/alumni/profile),
     * sehingga dievaluasi oleh evaluasiProfil() dan bukan evaluasiKuesionerWajib().
     */
    public const PROFILE_MANAGED_CODES = [
        'F1', 'F2A', 'F2B', 'F2C', 'F2D',
        'BIO_TEMPAT_LAHIR', 'BIO_TANGGAL_LAHIR', 'BIO_JK', 'BIO_TGL_LULUS', 'BIO_JUDUL_TA',
        'F5A1', 'F5A2', 'F510', 'F5B', 'F5C', 'F5D',
        'F2E', 'F2E1', 'F2E2', 'F2E3', 'F2F', 'F2G', 'F2H',
        'F11', 'F505', 'BIO_PEND_TINGKAT', 'BIO_BIDANG_DIVISI',
    ];

    /**
     * Mengambil seluruh butir pertanyaan kuesioner yang berstatus WAJIB (wajib = 1) dari database.
     *
     * @return array<int, string>
     */
    public static function getMandatoryQuestionCodes(): array
    {
        $profileCodesUpper = array_map('strtoupper', self::PROFILE_MANAGED_CODES);

        return RefSubpertanyaan2021::where('wajib', true)
            ->where('type', '!=', 'header')
            ->where(function ($query) {
                $query->whereNull('tampil_di')
                    ->orWhereNotIn('tampil_di', ['profil', 'profile']);
            })
            ->whereNotIn(DB::raw('UPPER(kode_pertanyaan)'), $profileCodesUpper)
            ->pluck('kode_pertanyaan')
            ->all();
    }

    /**
     * Memeriksa apakah suatu kode pertanyaan berstatus Wajib (wajib = 1).
     *
     * @param  string  $code  Kode pertanyaan (misal: 'F1', 'F8', 'F17a1', dll.)
     */
    public static function isMandatoryQuestion(string $code): bool
    {
        $question = RefSubpertanyaan2021::where('kode_pertanyaan', $code)->first();

        return (bool) ($question?->wajib && $question?->type !== 'header');
    }

    /**
     * Evaluasi kelengkapan profil biodata alumni.
     *
     * @return array Shape: ['is_complete' => bool, 'percentage' => int, 'filled_count' => int, 'total_fields' => int, 'missing_fields' => array]
     */
    public static function evaluasiProfil(Biodata $biodata): array
    {
        $biodata->refresh();
        $biodata->load(['dataAkademik', 'perusahaan.propinsi', 'perusahaan.kabupaten', 'atasan', 'user', 'prodi']);

        // Utamakan pembacaan dari Database View `v_alumni_profile_summary`
        $summary = $biodata->profileSummary ?? AlumniProfileSummary::where('biodata_id', $biodata->id)->first();
        $dataAkademik = $biodata->dataAkademik;
        $perusahaan = $biodata->perusahaan;
        $atasan = $biodata->atasan;

        $fields = [
            // Data Pribadi & Kontak
            'Nama Lengkap' => $summary?->nama ?? ($dataAkademik?->nama ?? $biodata->user?->name),
            'NIM' => $summary?->nim ?? $biodata->nim,
            'Tempat Lahir' => $summary?->tempat_lahir ?? $dataAkademik?->tempat_lahir,
            'Tanggal Lahir' => $summary?->tanggal_lahir ?? $dataAkademik?->tanggal_lahir,
            'Jenis Kelamin' => $summary?->jenis_kelamin ?? $dataAkademik?->jenis_kelamin,
            'Nomor Telepon/HP' => $summary?->nomor_telepon ?? $biodata->nomor_telepon,
            'Email Pribadi' => $summary?->email_pribadi ?? ($biodata->email_pribadi ?? $biodata->user?->email),
            'Alamat Domisili Saat Ini' => $summary?->alamat ?? $biodata->alamat_saat_ini,

            // Data Akademik
            'IPK Kelulusan' => $summary?->ipk ?? $dataAkademik?->ip_kumulatif,
            'Tahun Kelulusan' => $summary?->tahun_lulus ? (string) $summary->tahun_lulus : ($dataAkademik?->tahun_lulus ? (string) $dataAkademik->tahun_lulus : $dataAkademik?->tahun_akademik_lulus),

            // Data Perusahaan & Atasan
            'Nama Perusahaan' => $summary?->nama_perusahaan ?? $perusahaan?->nama_perusahaan,
            'Alamat Perusahaan' => $summary?->alamat_perusahaan ?? $perusahaan?->alamat,
            'Skala Perusahaan' => $summary?->skala_perusahaan ?? $perusahaan?->skala,
            'Provinsi Perusahaan' => $summary?->propinsi_id ?? $perusahaan?->propinsi_id,
            'Kabupaten Perusahaan' => $summary?->kabupaten_id ?? $perusahaan?->kabupaten_id,
            'Nama Atasan' => $summary?->nama_atasan ?? $atasan?->nama,
            'Email Atasan' => $summary?->email_atasan ?? $atasan?->email,
            'Nomor Telepon Atasan' => $summary?->telepon_atasan ?? $atasan?->telepon,
            'Posisi Jabatan' => $summary?->posisi_jabatan ?? $biodata->posisi_jabatan,

            // Data Media Sosial & Profesional Alumni (Tabel biodata)
            'Keahlian (Skills)' => $summary?->skills ?? $biodata->skills,
            'Pengalaman Kerja (Experience)' => $summary?->experience ?? $biodata->experience,
            'LinkedIn Profil URL' => $summary?->linkedin_url ?? $biodata->linkedin_url,
            'LinkedIn Username' => $summary?->linkedin_username ?? $biodata->linkedin_username,
            'Instagram Profil URL' => $summary?->instagram_url ?? $biodata->instagram_url,
            'Facebook Profil URL' => $summary?->facebook_url ?? $biodata->facebook_url,
            'Kode Pos Perusahaan (Zipcode)' => $summary?->kode_pos ?? $biodata->zipcode,
        ];

        $dynamicProfileQuestions = KuesionerSyncService::getDynamicProfileQuestions()
            ->filter(fn (RefSubpertanyaan2021 $question): bool => (bool) $question->wajib);
        $dynamicProfileResponses = Tracer::where('biodata_id', $biodata->id)
            ->whereIn('question_id', $dynamicProfileQuestions->modelKeys())
            ->get()
            ->keyBy('question_id');

        foreach ($dynamicProfileQuestions as $question) {
            $response = $dynamicProfileResponses->get($question->id);
            $fields['Pertanyaan Profil: '.$question->subpertanyaan] = $response?->answer;
        }

        $isTeologi = ($biodata->prodi?->kode_prodi === '31' || substr((string) $biodata->nim, 0, 2) === '31');
        if ($isTeologi) {
            $fields['Jenis Pekerjaan (Gerejawi)'] = $biodata->jenis_pekerjaan;
        }

        $missing = [];
        foreach ($fields as $label => $val) {
            if ($val === null || trim((string) $val) === '') {
                $missing[] = $label;
            }
        }

        $totalFields = count($fields);
        $filledFields = $totalFields - count($missing);
        $percentage = (int) round(($filledFields / $totalFields) * 100);

        return [
            'is_complete' => empty($missing),
            'percentage' => $percentage,
            'filled_count' => $filledFields,
            'total_fields' => $totalFields,
            'missing_fields' => $missing,
        ];
    }

    /**
     * Menghitung daftar kode atau ID pertanyaan yang dilewati/disembunyikan oleh logika percabangan (jump_to / IF).
     *
     * @param  Collection  $allQuestions
     * @param  Collection  $responses
     * @return array<string|int, bool>
     */
    public static function getSkippedQuestionCodes($allQuestions, $responses): array
    {
        $skipped = [];
        $questionsList = $allQuestions->values();
        $total = $questionsList->count();

        if ($total === 0) {
            return $skipped;
        }

        // Map kode pertanyaan (uppercase) ke indeks urutan
        $codeToIndex = [];
        $codeToQuestion = [];
        foreach ($questionsList as $idx => $q) {
            $code = strtoupper(trim((string) $q->kode_pertanyaan));
            if ($code !== '') {
                $codeToIndex[$code] = $idx;
                $codeToQuestion[$code] = $q;
            }
        }

        for ($i = 0; $i < $total; $i++) {
            $q = $questionsList[$i];
            $code = strtoupper(trim((string) $q->kode_pertanyaan));

            // Jika pertanyaan ini sudah dilewati oleh percabangan sebelumnya
            if (isset($skipped[$code]) || isset($skipped[$q->id])) {
                continue;
            }

            $resp = $responses->get($code) ?? $responses->get((string) $q->id);
            if (! $resp) {
                continue;
            }

            $rawAnswer = $resp->answer ?? $resp->answer_text;
            $jsonAnswer = $resp->answer_json;

            // Aturan Berbasis Status Pekerjaan F8 (Standar Tracer Study Kemendikbud Dikti)
            if ($code === 'F8') {
                $ansStr = strtolower(trim((string) $rawAnswer));

                // Status 4: Melanjutkan Pendidikan -> lewati pertanyaan pekerjaan/mencari kerja dan F10
                if (str_contains($ansStr, 'melanjutkan pendidikan') || $ansStr === '4') {
                    $workCodes = ['F10', 'F3', 'F4', 'F504', 'F502', 'F505', 'F506', 'F6', 'F7', 'F7A', 'F14', 'F15', 'F16'];
                    foreach ($workCodes as $wc) {
                        if (isset($codeToQuestion[$wc])) {
                            $skipped[$wc] = true;
                            $skipped[$codeToQuestion[$wc]->id] = true;
                        }
                    }
                }
                // Status 2: Belum Memungkinkan Bekerja -> lewati studi lanjut dan pertanyaan pekerjaan (F10 tetap dijawab)
                elseif (str_contains($ansStr, 'belum memungkinkan') || $ansStr === '2') {
                    $skipCodes = ['F18', 'F12', 'F18A', 'F18B', 'F18C', 'F18D', 'F3', 'F4', 'F504', 'F502', 'F505', 'F506', 'F6', 'F7', 'F7A', 'F14', 'F15', 'F16'];
                    foreach ($skipCodes as $sc) {
                        if (isset($codeToQuestion[$sc])) {
                            $skipped[$sc] = true;
                            $skipped[$codeToQuestion[$sc]->id] = true;
                        }
                    }
                }
                // Status 5: Tidak Kerja tetapi sedang mencari kerja -> lewati studi lanjut & pertanyaan sedang bekerja (F10 tetap dijawab)
                elseif (str_contains($ansStr, 'sedang mencari kerja') || $ansStr === '5' || str_contains($ansStr, 'mencari kerja')) {
                    $skipCodes = ['F18', 'F12', 'F18A', 'F18B', 'F18C', 'F18D', 'F504', 'F502', 'F505', 'F506', 'F14', 'F15', 'F16'];
                    foreach ($skipCodes as $sc) {
                        if (isset($codeToQuestion[$sc])) {
                            $skipped[$sc] = true;
                            $skipped[$codeToQuestion[$sc]->id] = true;
                        }
                    }
                }
                // Status 1 & 3: Bekerja / Wiraswasta -> lewati F10 dan studi lanjut (F18)
                else {
                    foreach (['F10', 'F18', 'F12', 'F18A', 'F18B', 'F18C', 'F18D'] as $sc) {
                        if (isset($codeToQuestion[$sc])) {
                            $skipped[$sc] = true;
                            $skipped[$codeToQuestion[$sc]->id] = true;
                        }
                    }
                }

                continue;
            }

            // Aturan Khusus F504:
            // Jika Ya -> sembunyikan F506 (pencarian > 6 bulan)
            // Jika Tidak -> sembunyikan F502 & F505 (pencarian <= 6 bulan & gaji)
            if ($code === 'F504') {
                $ansStr = strtolower(trim((string) $rawAnswer));
                if ($ansStr === 'ya' || $ansStr === '1' || str_starts_with($ansStr, 'ya')) {
                    if (isset($codeToQuestion['F506'])) {
                        $skipped['F506'] = true;
                        $skipped[$codeToQuestion['F506']->id] = true;
                    }
                } elseif ($ansStr === 'tidak' || $ansStr === '2' || str_starts_with($ansStr, 'tidak')) {
                    foreach (['F502', 'F505'] as $wc) {
                        if (isset($codeToQuestion[$wc])) {
                            $skipped[$wc] = true;
                            $skipped[$codeToQuestion[$wc]->id] = true;
                        }
                    }
                }

                continue;
            }

            $options = $q->detils ?? collect();
            if ($options->isEmpty()) {
                continue;
            }

            $selectedOption = null;

            if (! empty($rawAnswer)) {
                $ansStr = strtolower(trim((string) $rawAnswer));
                $selectedOption = $options->first(function ($opt) use ($ansStr) {
                    $optText = strtolower(trim((string) $opt->option_text));
                    $optCode = strtolower(trim((string) ($opt->kode_opsi ?? '')));

                    return $optText === $ansStr || $optCode === $ansStr || ($ansStr !== '' && str_starts_with($ansStr, $optText));
                });
            } elseif (is_array($jsonAnswer)) {
                $sel = $jsonAnswer['selected'] ?? ($jsonAnswer['pilihan'] ?? null);
                if ($sel) {
                    $selStr = strtolower(trim((string) $sel));
                    $selectedOption = $options->first(function ($opt) use ($selStr) {
                        $optText = strtolower(trim((string) $opt->option_text));
                        $optCode = strtolower(trim((string) ($opt->kode_opsi ?? '')));

                        return $optText === $selStr || $optCode === $selStr;
                    });
                }
            }

            // Jika opsi yang dipilih memiliki instruksi jump_to
            if ($selectedOption && ! empty($selectedOption->jump_to)) {
                $targetCode = strtoupper(trim((string) $selectedOption->jump_to));
                $targetIdx = $codeToIndex[$targetCode] ?? null;

                if ($targetIdx !== null && $targetIdx > $i) {
                    for ($j = $i + 1; $j < $targetIdx; $j++) {
                        $skippedQ = $questionsList[$j];
                        $skipped[strtoupper(trim((string) $skippedQ->kode_pertanyaan))] = true;
                        $skipped[$skippedQ->id] = true;
                    }
                }
            }
        }

        return $skipped;
    }

    /**
     * Evaluasi kelengkapan kuesioner tracer study wajib bagi alumni.
     * Murni didasarkan pada butir pertanyaan dengan status wajib = 1 di tabel ref_subpertanyaan2021
     * yang berlaku pada jalur alur pengisian alumni (memperhitungkan percabangan / IF jump_to).
     *
     * @return array Shape: ['is_complete' => bool, 'percentage' => int, 'answered_count' => int, 'total_mandatory' => int, 'missing_questions' => array]
     */
    public static function evaluasiKuesionerWajib(Biodata $biodata): array
    {
        // Pastikan respon profil tersinkron ke tabel tracer
        KuesionerSyncService::syncProfileResponses($biodata);

        // Ambil ID dan respon pertanyaan alumni
        $responses = Tracer::where('biodata_id', $biodata->id)
            ->with('masterPertanyaan')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper(trim((string) ($item->masterPertanyaan?->kode_pertanyaan ?? $item->kode_pertanyaan)));
            });

        // Ambil seluruh butir pertanyaan terurut
        $allQuestions = RefSubpertanyaan2021::with('detils')
            ->orderBy('order', 'asc')
            ->get();

        $skippedCodes = self::getSkippedQuestionCodes($allQuestions, $responses);

        $profileCodesUpper = array_map('strtoupper', self::PROFILE_MANAGED_CODES);

        // Ambil seluruh butir pertanyaan wajib (wajib = 1, non-header, dan bukan bagian profil)
        $mandatoryQuestions = $allQuestions->filter(function ($q) use ($profileCodesUpper) {
            $code = strtoupper(trim((string) $q->kode_pertanyaan));
            $displayLocation = strtolower(trim((string) $q->tampil_di));

            return $q->wajib
                && $q->type !== 'header'
                && ! in_array($code, $profileCodesUpper)
                && ! in_array($displayLocation, ['profil', 'profile']);
        });

        $missing = [];
        $applicableMandatory = [];

        foreach ($mandatoryQuestions as $q) {
            $code = strtoupper(trim((string) $q->kode_pertanyaan));

            // Jika pertanyaan ini dilewati oleh alur percabangan (jump_to / IF),
            // maka dianggap sudah selesai / tidak wajib diisi alumni
            if (isset($skippedCodes[$code]) || isset($skippedCodes[$q->id])) {
                continue;
            }

            $applicableMandatory[] = $q;
            $resp = $responses->get($code) ?? $responses->get((string) $q->id);
            $hasAnswer = false;

            if ($resp) {
                $rawAnswer = $resp->answer ?? $resp->answer_text;
                if (! empty($rawAnswer) && trim((string) $rawAnswer) !== '') {
                    $hasAnswer = true;
                } elseif (is_array($resp->answer_json) && count($resp->answer_json) > 0) {
                    $hasAnswer = true;
                }
            }

            if (! $hasAnswer) {
                $missing[] = "{$q->kode_pertanyaan} ({$q->subpertanyaan})";
            }
        }

        // Evaluasi Keterisian Seluruh Butir Instrumen Relevan (Wajib + Opsional)
        $allApplicableQuestions = $allQuestions->filter(function ($q) use ($profileCodesUpper) {
            $code = strtoupper(trim((string) $q->kode_pertanyaan));
            $displayLocation = strtolower(trim((string) $q->tampil_di));

            return $q->type !== 'header'
                && ! in_array($code, $profileCodesUpper)
                && ! in_array($displayLocation, ['profil', 'profile']);
        });

        $totalApplicableQuestions = 0;
        $totalAnsweredQuestions = 0;
        $missingOptional = [];

        foreach ($allApplicableQuestions as $q) {
            $code = strtoupper(trim((string) $q->kode_pertanyaan));

            if (isset($skippedCodes[$code]) || isset($skippedCodes[$q->id])) {
                continue;
            }

            $totalApplicableQuestions++;
            $resp = $responses->get($code) ?? $responses->get((string) $q->id);
            $hasAnswer = false;

            if ($resp) {
                $rawAnswer = $resp->answer ?? $resp->answer_text;
                if (! empty($rawAnswer) && trim((string) $rawAnswer) !== '') {
                    $hasAnswer = true;
                } elseif (is_array($resp->answer_json) && count($resp->answer_json) > 0) {
                    $hasAnswer = true;
                }
            }

            if ($hasAnswer) {
                $totalAnsweredQuestions++;
            } elseif (! $q->wajib) {
                $missingOptional[] = "{$q->kode_pertanyaan} ({$q->subpertanyaan})";
            }
        }

        $totalMandatory = count($applicableMandatory);
        $missingCount = count($missing);
        $answeredCount = max(0, $totalMandatory - $missingCount);
        $percentage = $totalMandatory > 0 ? (int) round(($answeredCount / $totalMandatory) * 100) : 100;
        $overallPercentage = $totalApplicableQuestions > 0 ? (int) round(($totalAnsweredQuestions / $totalApplicableQuestions) * 100) : 100;

        $isMandatoryComplete = ($missingCount === 0);
        $isFullyComplete = ($missingCount === 0 && count($missingOptional) === 0);

        return [
            'is_complete' => $isMandatoryComplete,
            'is_mandatory_complete' => $isMandatoryComplete,
            'is_fully_complete' => $isFullyComplete,
            'percentage' => $percentage,
            'mandatory_percentage' => $percentage,
            'overall_percentage' => $overallPercentage,
            'answered_count' => $answeredCount,
            'total_mandatory' => $totalMandatory,
            'total_applicable' => $totalApplicableQuestions,
            'total_answered' => $totalAnsweredQuestions,
            'unanswered_optional_count' => count($missingOptional),
            'missing_questions' => $missing,
            'missing_optional_questions' => $missingOptional,
        ];
    }

    /**
     * Evaluasi total akhir status alumni (Profil + Kuesioner Wajib).
     *
     * @return array Shape: ['status' => string, 'is_complete' => bool, 'profile' => array, 'questionnaire' => array]
     */
    public static function evaluasiKelengkapanTotal(Biodata $biodata): array
    {
        $evalProfil = self::evaluasiProfil($biodata);
        $evalKuesioner = self::evaluasiKuesionerWajib($biodata);

        $isComplete = $evalProfil['is_complete'] && $evalKuesioner['is_complete'];

        $statusText = 'Belum Selesai';
        if ($isComplete) {
            $statusText = $evalKuesioner['is_fully_complete'] ? 'Selesai Sempurna' : 'Selesai (Wajib Dikti)';
        }

        return [
            'status' => $statusText,
            'is_complete' => $isComplete,
            'is_fully_complete' => ($isComplete && $evalKuesioner['is_fully_complete']),
            'profile' => $evalProfil,
            'questionnaire' => $evalKuesioner,
        ];
    }
}
