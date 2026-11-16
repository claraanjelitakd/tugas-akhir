<?php

namespace App\Http\Controllers\Alumni\Kuesioner;

use App\Http\Controllers\Controller;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerSection;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller untuk Kuesioner Khusus Program Studi bagi Alumni.
 *
 * Mengelola pemuatan bagian instrumen prodi, butir soal bank soal prodi,
 * dan penyimpanan respon alumni langsung ke tabel terpadu `kuesioner_jawaban`.
 */
class KuesionerProdiController extends Controller
{
    /**
     * Menampilkan halaman kuesioner khusus program studi alumni beserta jawaban awal.
     */
    public function tampilkanKuesionerProdi(): InertiaResponse
    {
        $user = Auth::user();
        $biodata = $user->biodata;

        if (! $biodata || ! $biodata->prodi_id) {
            return Inertia::render('Alumni/KuesionerProdi', [
                'prodi' => null,
                'sections' => [],
                'questions' => [],
                'initialAnswers' => [],
                'message' => 'Profil Anda belum terhubung dengan Program Studi.',
            ]);
        }

        $biodata->load(['dataAkademik', 'yudisium', 'user', 'prodi']);
        $prodi = $biodata->prodi;

        // Profil/akademik tetap menjadi sumber kanonik field identitas prodi.
        KuesionerSyncService::syncProdiResponses($biodata);

        // Ambil section kuesioner khusus prodi aktif
        $rawSections = KuesionerSection::where('target', 'prodi')
            ->where('prodi_id', $biodata->prodi_id)
            ->where('is_active', true)
            ->with(['pertanyaan' => function ($query) {
                $query->where('kuesioner_pertanyaan.is_active', true)
                    ->wherePivot('is_active', true)
                    ->with('opsi');
            }])
            ->orderBy('order', 'asc')
            ->get();

        $sectionsFormatted = [];
        $questionsList = [];

        foreach ($rawSections as $sec) {
            $secQuestions = [];

            foreach ($sec->pertanyaan as $p) {
                $qItem = [
                    'id' => $p->id,
                    'section_id' => $sec->id,
                    'code' => $p->kode_pertanyaan,
                    'question_label' => $p->label_pertanyaan,
                    'question_text' => $p->teks_pertanyaan,
                    'type' => $p->tipe,
                    'is_required' => (bool) ($p->pivot->wajib ?? true),
                    'order' => (int) ($p->pivot->order ?? 0),
                    'options' => $p->opsi->map(fn ($opt) => [
                        'id' => $opt->id,
                        'option_text' => $opt->label,
                        'kode_opsi' => $opt->kode_opsi,
                        'jump_to' => $opt->lompat_ke_pertanyaan_id,
                    ])->values()->all(),
                ];

                $secQuestions[] = $qItem;
                $questionsList[] = $qItem;
            }

            $sectionsFormatted[] = [
                'id' => $sec->id,
                'title' => $sec->judul,
                'description' => $sec->deskripsi,
                'order' => $sec->order,
                'questions' => $secQuestions,
            ];
        }

        // Ambil riwayat respon kuesioner prodi alumni dari kuesioner_jawaban
        $questionIds = array_column($questionsList, 'id');
        $existingResponses = KuesionerJawaban::where('biodata_id', $biodata->id)
            ->where('responden_type', 'alumni')
            ->whereIn('pertanyaan_id', $questionIds)
            ->get()
            ->keyBy('pertanyaan_id');

        // Ambil data autofill alumni
        $autofill = DB::table('v_alumni_kuesioner_autofill')
            ->where('biodata_id', $biodata->id)
            ->first();

        $namaAkademik = $autofill->F2A ?? $biodata->nama ?? $biodata->user?->name ?? '';
        $nimAkademik = $autofill->F1 ?? $biodata->nim ?? '';
        $tahunLulusAkademik = $biodata->dataAkademik?->tahun_lulus
            ?? $biodata->tahun_lulus
            ?? (isset($autofill->BIO_TGL_LULUS) ? substr((string) $autofill->BIO_TGL_LULUS, 0, 4) : '');

        $initialAnswers = [];
        foreach ($questionsList as $q) {
            $qId = $q['id'];
            $resp = $existingResponses->get($qId);
            $identityField = KuesionerSyncService::resolveAcademicIdentityField((string) $q['question_text']);

            $autoVal = null;
            if ($identityField === 'nama') {
                $autoVal = $namaAkademik;
            } elseif ($identityField === 'nim') {
                $autoVal = $nimAkademik;
            } elseif ($identityField === 'tahun kelulusan') {
                $autoVal = $tahunLulusAkademik;
            }

            if ($resp) {
                $ansText = $resp->jawaban_teks ?? '';
                $ansJson = is_array($resp->jawaban_json) ? $resp->jawaban_json : [];

                if ($q['type'] === 'multiple_choice' || $q['type'] === 'checkbox') {
                    if (isset($ansJson['selected'])) {
                        $initialAnswers[$qId] = (array) $ansJson['selected'];
                        $initialAnswers[$qId.'_custom'] = (string) ($ansJson['custom'] ?? '');
                    } else {
                        $initialAnswers[$qId] = $ansJson ?: (array) $ansText;
                    }
                } elseif ($q['type'] === 'radio_input' || $q['type'] === 'radio_text') {
                    $initialAnswers[$qId] = [
                        'selected' => $ansText,
                        'input' => $ansJson['input'] ?? '',
                        'inputs' => $ansJson['inputs'] ?? [],
                    ];
                } else {
                    $val = $ansText;
                    if (isset($ansJson['custom'])) {
                        $val = (string) ($ansJson['selected'] ?? $val);
                        $initialAnswers[$qId.'_custom'] = (string) $ansJson['custom'];
                    }
                    $initialAnswers[$qId] = $autoVal !== null && $autoVal !== ''
                        ? (string) $autoVal
                        : $val;
                }
            } else {
                if ($autoVal !== null && $autoVal !== '') {
                    $initialAnswers[$qId] = (string) $autoVal;

                    // Sinkronisasi otomatis nilai identitas jika ada
                    KuesionerJawaban::updateOrCreate(
                        [
                            'responden_type' => 'alumni',
                            'biodata_id' => $biodata->id,
                            'pertanyaan_id' => $qId,
                        ],
                        [
                            'section_id' => $q['section_id'],
                            'evaluasi_atasan_id' => null,
                            'kode_pertanyaan' => $q['code'],
                            'opsi_id' => null,
                            'kode_opsi' => null,
                            'jawaban_teks' => (string) $autoVal,
                            'jawaban_json' => null,
                        ]
                    );
                } else {
                    if ($q['type'] === 'multiple_choice' || $q['type'] === 'checkbox') {
                        $initialAnswers[$qId] = [];
                    } elseif ($q['type'] === 'radio_input' || $q['type'] === 'radio_text') {
                        $initialAnswers[$qId] = [
                            'selected' => '',
                            'input' => '',
                            'inputs' => [],
                        ];
                    } else {
                        $initialAnswers[$qId] = '';
                    }
                }
            }
        }

        return Inertia::render('Alumni/KuesionerProdi', [
            'biodata' => $biodata,
            'alumni' => $biodata,
            'prodi' => $prodi,
            'sections' => $sectionsFormatted,
            'questions' => $questionsList,
            'initialAnswers' => $initialAnswers,
        ]);
    }

    /**
     * Menyimpan jawaban kuesioner prodi alumni langsung ke tabel terpadu kuesioner_jawaban.
     */
    public function simpanJawaban(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $biodata = $user->biodata;

        if (! $biodata || ! $biodata->prodi_id) {
            return redirect()->back()->with('error', 'Profil alumni tidak valid.');
        }

        $autofill = DB::table('v_alumni_kuesioner_autofill')
            ->where('biodata_id', $biodata->id)
            ->first();

        $jawabanMasuk = $request->input('answers', []);

        // Ambil seluruh section kuesioner prodi yang aktif beserta butir pertanyaannya
        $prodiSections = KuesionerSection::where('target', 'prodi')
            ->where('prodi_id', $biodata->prodi_id)
            ->where('is_active', true)
            ->with(['pertanyaan' => function ($query) {
                $query->where('kuesioner_pertanyaan.is_active', true)
                    ->wherePivot('is_active', true)
                    ->with('opsi');
            }])
            ->get();

        $pertanyaanMap = [];
        foreach ($prodiSections as $sec) {
            foreach ($sec->pertanyaan as $p) {
                $p->current_section_id = $sec->id;
                $pertanyaanMap[$p->id] = $p;
            }
        }

        foreach ($pertanyaanMap as $qId => $q) {
            $jawaban = $jawabanMasuk[$qId] ?? null;
            $customText = isset($jawabanMasuk[$qId.'_custom']) ? trim((string) $jawabanMasuk[$qId.'_custom']) : '';
            $answerValue = null;
            $answerJson = null;

            if (is_array($jawaban)) {
                if ($q->tipe === 'multiple_choice' || $q->tipe === 'checkbox') {
                    $cleaned = array_values(array_filter($jawaban));
                    if (! empty($cleaned)) {
                        $answerJson = $customText !== ''
                            ? ['selected' => $cleaned, 'custom' => $customText]
                            : $cleaned;
                        $displayItems = array_map(function ($item) use ($customText) {
                            $lower = strtolower(trim((string) $item));
                            if ($customText !== '' && ($lower === 'other' || str_contains($lower, 'lainnya') || str_contains($lower, 'tuliskan') || str_contains($lower, '...'))) {
                                return "{$item} ({$customText})";
                            }

                            return $item;
                        }, $cleaned);
                        $answerValue = implode(', ', $displayItems);
                    }
                } elseif ($q->tipe === 'radio_input' || $q->tipe === 'radio_text') {
                    $selected = is_array($jawaban) ? trim((string) ($jawaban['selected'] ?? '')) : trim((string) $jawaban);
                    $inputVal = is_array($jawaban) ? trim((string) ($jawaban['input'] ?? '')) : '';
                    if ($selected !== '') {
                        $answerJson = ['selected' => $selected, 'input' => $inputVal];
                        $answerValue = ! empty($inputVal) ? "{$selected}: {$inputVal}" : $selected;
                    }
                } elseif (! empty($jawaban)) {
                    $answerJson = $jawaban;
                    $answerValue = json_encode($jawaban, JSON_UNESCAPED_UNICODE);
                }
            } elseif ($jawaban !== null && trim((string) $jawaban) !== '') {
                $answerValue = trim((string) $jawaban);
                if ($q->tipe === 'radio_input' || $q->tipe === 'radio_text') {
                    $answerJson = ['selected' => $answerValue, 'input' => ''];
                } elseif (($q->tipe === 'single_choice' || $q->tipe === 'radio') && $customText !== '') {
                    $lower = strtolower($answerValue);
                    if ($lower === 'other' || str_contains($lower, 'lainnya') || str_contains($lower, 'tuliskan') || str_contains($lower, '...')) {
                        $answerJson = ['selected' => $answerValue, 'custom' => $customText];
                        $answerValue = "{$answerValue} ({$customText})";
                    }
                }
            }

            // Autofill identitas jika butir identitas kosong
            $qText = strtolower(trim((string) $q->teks_pertanyaan));
            $isIdentityQuestion = in_array($qText, ['nama', 'nama lengkap', 'nim', 'nomor induk mahasiswa', 'tahun kelulusan', 'tahun lulus'], true);

            if (($answerValue === null || $answerValue === '') && $isIdentityQuestion) {
                if ($qText === 'nama' || $qText === 'nama lengkap') {
                    $answerValue = $autofill->F2A ?? $biodata->nama ?? $biodata->user?->name;
                } elseif ($qText === 'nim' || $qText === 'nomor induk mahasiswa') {
                    $answerValue = $autofill->F1 ?? $biodata->nim;
                } elseif ($qText === 'tahun kelulusan' || $qText === 'tahun lulus') {
                    $answerValue = $autofill->BIO_TGL_LULUS ?? $biodata->tahun_lulus;
                }
            }

            if ($answerValue !== null && $answerValue !== '') {
                $opsi = $q->opsi->first(fn ($o) => strcasecmp($o->label, $answerValue) === 0 || $o->kode_opsi === $answerValue);
                $kodeOpsiResult = $opsi?->kode_opsi;

                if (empty($kodeOpsiResult) && ! empty($answerJson) && is_array($answerJson)) {
                    $matchingCodes = $q->opsi
                        ->filter(fn ($o) => in_array($o->label, (array) $answerJson, true) || in_array($o->kode_opsi, (array) $answerJson, true))
                        ->pluck('kode_opsi')
                        ->filter()
                        ->values()
                        ->all();
                    if (! empty($matchingCodes)) {
                        $kodeOpsiResult = json_encode($matchingCodes, JSON_UNESCAPED_UNICODE);
                    }
                }

                KuesionerJawaban::updateOrCreate(
                    [
                        'responden_type' => 'alumni',
                        'biodata_id' => $biodata->id,
                        'pertanyaan_id' => $q->id,
                    ],
                    [
                        'section_id' => $q->current_section_id ?? $q->pivot?->section_id,
                        'evaluasi_atasan_id' => null,
                        'kode_pertanyaan' => $q->kode_pertanyaan,
                        'opsi_id' => $opsi?->id,
                        'kode_opsi' => $kodeOpsiResult,
                        'jawaban_teks' => $answerValue,
                        'jawaban_json' => $answerJson,
                    ]
                );
            } else {
                KuesionerJawaban::where('responden_type', 'alumni')
                    ->where('biodata_id', $biodata->id)
                    ->where('pertanyaan_id', $q->id)
                    ->delete();
            }
        }

        if ($request->boolean('stay_on_page')) {
            return redirect()->back()->with('success', 'Progres jawaban kuesioner program studi berhasil disimpan.');
        }

        return redirect('/alumni/dashboard')->with('success', 'Jawaban Kuesioner Program Studi berhasil disimpan. Terima kasih!');
    }
}
