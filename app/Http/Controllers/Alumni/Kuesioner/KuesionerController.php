<?php

namespace App\Http\Controllers\Alumni\Kuesioner;

use App\Http\Controllers\Controller;
use App\Models\Kuesioner;
use App\Models\QuestionMapping;
use App\Models\Tracer;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * KuesionerController
 *
 * Fungsi: Menampilkan formulir kuesioner tracer study kepada alumni.
 * Tanggung Jawab:
 * 1. Memvalidasi profil biodata alumni yang sedang login.
 * 2. Melakukan sinkronisasi data profil alumni ke tabel respon kuesioner.
 * 3. Mengambil seksi kuesioner aktif mulai dari seksi 3 (Waktu Mulai Mencari Kerja).
 * 4. Mempersiapkan jawaban awal (prefilled answers) dari data tracer yang sudah tersimpan
 *    atau data pemetaan (QuestionMapping) profil alumni.
 */
class KuesionerController extends Controller
{
    /**
     * Menampilkan Halaman Kuesioner Tracer Study Alumni
     *
     * @return Response
     */
    public function tampilkanKuesioner()
    {
        // 1. Ambil data pengguna dan relasi profil biodata
        $pengguna = Auth::user();
        $biodata = $pengguna->biodata;

        if (! $biodata) {
            abort(403, 'Profil Biodata tidak ditemukan.');
        }

        $biodata->load(['dataAkademik', 'yudisium', 'perusahaan.propinsi', 'perusahaan.kabupaten', 'atasan', 'user']);

        // 2. Sinkronisasi otomatis data profil (Identitas & Perusahaan/Atasan) ke tabel tracer
        KuesionerSyncService::syncProfileResponses($biodata);

        // 3. Ambil data kuesioner aktif: Saring seksi kuesioner universitas (Seksi 1 Identitas & Biodata dikelola di Profil Alumni)
        $kuesioner = Kuesioner::where('is_active', true)
            ->with(['sections' => function ($query) {
                $query->where(function ($secQ) {
                    $secQ->whereNull('kode_kelompok')
                        ->orWhere('kode_kelompok', '!=', '1');
                })
                    ->orderBy('order', 'asc')
                    ->with(['subpertanyaans' => function ($qQuery) {
                        $qQuery->where(function ($subQ) {
                            $subQ->where(function ($w) {
                                $w->whereNull('tampil_di')
                                    ->orWhereNotIn('tampil_di', ['profil', 'profile']);
                            })->where(function ($w) {
                                $w->whereNull('kelompok')
                                    ->orWhere('kelompok', '!=', 'BIO');
                            });
                        })
                            ->orderBy('order', 'asc')
                            ->with('detils');
                    }]);
            }])
            ->first();

        if ($kuesioner) {
            // Saring hanya seksi yang memiliki pertanyaan aktif untuk kuesioner
            $filteredSections = $kuesioner->sections->filter(function ($section) {
                return $section->subpertanyaans->isNotEmpty();
            })->values();
            $kuesioner->setRelation('sections', $filteredSections);
        }

        // 4. Jika tidak ada kuesioner yang aktif, kembalikan tampilan dengan pesan error
        if (! $kuesioner) {
            return Inertia::render('Alumni/Kuesioner', [
                'error' => 'Tidak ada kuesioner aktif saat ini.',
                'questionnaire' => null,
                'kuesioner' => null,
                'initialAnswers' => [],
            ]);
        }

        // 5. Ambil data respon jawaban yang sudah tersimpan sebelumnya di tabel tracer
        $jawabanTersimpan = Tracer::where('biodata_id', $biodata->id)
            ->get()
            ->keyBy('question_id');

        // 6. Ambil data gabungan profil alumni dari database view untuk prefill otomatis
        $autofillData = DB::table('v_alumni_kuesioner_autofill')->where('user_id', $pengguna->id)->first();

        // 7. Merakit data jawaban awal ($jawabanAwal) terstruktur per butir pertanyaan
        $jawabanAwal = [];
        if ($kuesioner) {
            foreach ($kuesioner->sections as $bagian) {
                foreach ($bagian->subpertanyaans as $pertanyaan) {
                    if (isset($jawabanTersimpan[$pertanyaan->id])) {
                        $saved = $jawabanTersimpan[$pertanyaan->id];

                        if (in_array($pertanyaan->type, ['checkbox', 'multiple_choice'])) {
                            $arr = is_array($saved->answer_json) ? $saved->answer_json : [];
                            $cleanArr = [];
                            $customText = '';

                            // Cari opsi database yang merupakan opsi lainnya jika ada
                            $otherOption = $pertanyaan->detils->first(function ($opt) {
                                $t = strtolower($opt->option_text);

                                return str_contains($t, 'lainnya') || str_contains($t, 'tuliskan') || str_contains($t, '...');
                            });

                            foreach ($arr as $item) {
                                if (is_string($item) && str_starts_with($item, 'Lainnya: ')) {
                                    $customText = trim(substr($item, 9));
                                    if ($otherOption) {
                                        $cleanArr[] = $otherOption->option_text;
                                    } else {
                                        $cleanArr[] = 'Lainnya';
                                    }
                                } else {
                                    $cleanArr[] = $item;
                                }
                            }

                            $jawabanAwal[$pertanyaan->id] = $cleanArr;
                            if (! empty($customText)) {
                                $jawabanAwal[$pertanyaan->id.'_custom'] = $customText;
                            }
                        } elseif (in_array($pertanyaan->type, ['single_choice', 'radio', 'dropdown', 'searchable_select'])) {
                            $textVal = $saved->answer_text ?? ($saved->answer ?? '');
                            if (str_starts_with($textVal, 'Lainnya: ')) {
                                $customText = trim(substr($textVal, 9));
                                $otherOption = $pertanyaan->detils->first(function ($opt) {
                                    $t = strtolower($opt->option_text);

                                    return str_contains($t, 'lainnya') || str_contains($t, 'tuliskan') || str_contains($t, '...');
                                });
                                $jawabanAwal[$pertanyaan->id] = $otherOption ? $otherOption->option_text : 'Lainnya';
                                if (! empty($customText)) {
                                    $jawabanAwal[$pertanyaan->id.'_custom'] = $customText;
                                }
                            } else {
                                $matchedOpt = $pertanyaan->detils->first(function ($opt) use ($textVal) {
                                    $t = strtolower(trim((string) $textVal));
                                    $optText = strtolower(trim((string) $opt->option_text));
                                    $optCode = strtolower(trim((string) ($opt->kode_opsi ?? '')));

                                    return $t === $optText
                                        || $t === $optCode
                                        || ($t === 'pekerja' && str_contains($optText, 'bekerja'))
                                        || ($t === 'bekerja' && str_contains($optText, 'bekerja'))
                                        || ($t === 'mencari kerja' && str_contains($optText, 'mencari kerja'))
                                        || ($t === 'belum memungkinkan bekerja' && str_contains($optText, 'belum memungkinkan'));
                                });

                                $jawabanAwal[$pertanyaan->id] = $matchedOpt ? $matchedOpt->option_text : $textVal;
                            }
                        } elseif (in_array($pertanyaan->type, ['radio_input', 'radio_text'])) {
                            $jsonVal = is_array($saved->answer_json) ? $saved->answer_json : [];
                            $textVal = $saved->answer_text ?? '';

                            $inputsObj = [];
                            foreach ($pertanyaan->detils as $o) {
                                $inputsObj[$o->id] = '';
                            }

                            if (! empty($jsonVal)) {
                                $pilihan = $jsonVal['pilihan'] ?? ($jsonVal['selected'] ?? '');
                                $input = $jsonVal['input'] ?? ($jsonVal['text'] ?? '');
                                $rawInputs = is_array($jsonVal['inputs'] ?? null) ? $jsonVal['inputs'] : [];

                                $matchedOpt = $pertanyaan->detils->first(fn ($o) => strcasecmp($o->option_text, $pilihan) === 0);
                                if ($matchedOpt) {
                                    $inputsObj[$matchedOpt->id] = (string) ($rawInputs[$matchedOpt->id] ?? $input);
                                }

                                $jawabanAwal[$pertanyaan->id] = [
                                    'selected' => $matchedOpt ? $matchedOpt->option_text : $pilihan,
                                    'input' => $matchedOpt ? $inputsObj[$matchedOpt->id] : $input,
                                    'inputs' => $inputsObj,
                                ];
                            } elseif (! empty($textVal)) {
                                $matchedOption = null;
                                $matchedInput = '';

                                foreach ($pertanyaan->detils as $o) {
                                    if (str_starts_with($textVal, $o->option_text)) {
                                        $matchedOption = $o->option_text;
                                        $remainder = trim(substr($textVal, strlen($o->option_text)));
                                        $matchedInput = ltrim($remainder, ': ');
                                        $inputsObj[$o->id] = $matchedInput;
                                        break;
                                    }
                                }

                                $jawabanAwal[$pertanyaan->id] = [
                                    'selected' => $matchedOption ?? $textVal,
                                    'input' => $matchedInput,
                                    'inputs' => $inputsObj,
                                ];
                            } else {
                                $jawabanAwal[$pertanyaan->id] = [
                                    'selected' => '',
                                    'input' => '',
                                    'inputs' => $inputsObj,
                                ];
                            }
                        } elseif (in_array($pertanyaan->type, ['matrix', 'matrix_dual', 'multiple_textbox'])) {
                            if (! empty($saved->answer_json) && is_array($saved->answer_json)) {
                                $jawabanAwal[$pertanyaan->id] = $saved->answer_json;
                            } elseif (! empty($saved->answer_text)) {
                                $decoded = json_decode($saved->answer_text, true);
                                $jawabanAwal[$pertanyaan->id] = is_array($decoded) ? $decoded : [];
                            } else {
                                $jawabanAwal[$pertanyaan->id] = [];
                            }
                        } elseif ($pertanyaan->type === 'multiple_number') {
                            $jsonVal = is_array($saved->answer_json) ? $saved->answer_json : [];
                            $formatted = [];
                            foreach ($jsonVal as $k => $v) {
                                if ($k === 'total') {
                                    continue;
                                }
                                $numVal = is_numeric($v) ? (float) $v : 0;
                                $formatted[$k] = (int) $numVal;
                            }

                            // Fallback jika belum ada jawaban di tracer tetapi profil memiliki data gaji
                            $gajiVal = ! empty($autofillData->F505) ? $autofillData->F505 : ($biodata->gaji ?? null);
                            if (empty($formatted) && ! empty($gajiVal)) {
                                $firstOpt = $pertanyaan->detils->first();
                                $optCode = $firstOpt ? ($firstOpt->kode_opsi ?: ($firstOpt->code ?: $firstOpt->id)) : 'F5051';
                                $gajiNum = (float) $gajiVal;
                                $formatted[$optCode] = (int) $gajiNum;
                            }

                            $jawabanAwal[$pertanyaan->id] = $formatted;
                        } else {
                            $kode = strtoupper($pertanyaan->code ?? $pertanyaan->kode_pertanyaan ?? '');
                            $val = $saved->answer_text ?? '';
                            if ($kode === 'F18B' && ! empty($biodata->perguruan_tinggi)) {
                                $val = $biodata->perguruan_tinggi;
                            } elseif ($kode === 'F18C' && ! empty($biodata->pendidikan_prodi)) {
                                $val = $biodata->pendidikan_prodi;
                            }
                            $jawabanAwal[$pertanyaan->id] = $val;
                        }
                    } else {
                        // Belum ada jawaban tersimpan sebelumnya, lakukan penyiapan nilai default
                        if (in_array($pertanyaan->type, ['checkbox', 'multiple_choice', 'matrix', 'matrix_dual', 'multiple_textbox'])) {
                            $jawabanAwal[$pertanyaan->id] = [];
                        } elseif ($pertanyaan->type === 'multiple_number') {
                            $formatted = [];
                            $gajiVal = ! empty($autofillData->F505) ? $autofillData->F505 : ($biodata->gaji ?? null);
                            if (! empty($gajiVal)) {
                                $firstOpt = $pertanyaan->detils->first();
                                $optCode = $firstOpt ? ($firstOpt->kode_opsi ?: ($firstOpt->code ?: $firstOpt->id)) : 'F5051';
                                $gajiNum = (float) $gajiVal;
                                $formatted[$optCode] = (int) $gajiNum;
                            }
                            $jawabanAwal[$pertanyaan->id] = $formatted;
                        } elseif (in_array($pertanyaan->type, ['radio_input', 'radio_text'])) {
                            $inputsObj = [];
                            foreach ($pertanyaan->detils as $o) {
                                $inputsObj[$o->id] = '';
                            }
                            $jawabanAwal[$pertanyaan->id] = [
                                'selected' => '',
                                'input' => '',
                                'inputs' => $inputsObj,
                            ];
                        } else {
                            $kode = strtoupper($pertanyaan->code ?? $pertanyaan->kode_pertanyaan ?? '');
                            $val = $autofillData->$kode ?? ($autofillData->{strtolower($kode)} ?? '');

                            // Autofill profil untuk Studi Lanjut F18
                            if (empty($val)) {
                                if ($kode === 'F18B') {
                                    $val = $biodata->perguruan_tinggi ?? '';
                                } elseif ($kode === 'F18C') {
                                    $val = $biodata->pendidikan_prodi ?? '';
                                }
                            }

                            // Normalisasi F8 jika ada data kategori_pekerjaan di biodata
                            if ($kode === 'F8' && ! empty($val)) {
                                $matchedOpt = $pertanyaan->detils->first(function ($opt) use ($val) {
                                    return strcasecmp($opt->option_text, $val) === 0 || str_starts_with(strtolower($opt->option_text), strtolower($val));
                                });
                                $val = $matchedOpt ? $matchedOpt->option_text : $val;
                            }

                            $jawabanAwal[$pertanyaan->id] = $val;
                        }
                    }
                }
            }
        }

        return Inertia::render('Alumni/Kuesioner', [
            'kuesioner' => $kuesioner,
            'questionnaire' => $kuesioner,
            'initialAnswers' => $jawabanAwal,
            'questionnaireStorageKey' => 'alumni_'.$pengguna->id.'_kuesioner_'.$kuesioner->id,
            'error' => null,
        ]);
    }
}
