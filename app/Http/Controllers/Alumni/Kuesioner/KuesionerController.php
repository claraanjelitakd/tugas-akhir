<?php

namespace App\Http\Controllers\Alumni\Kuesioner;

use App\Http\Controllers\Controller;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Services\Kuesioner\KelengkapanTracerService;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * KuesionerController
 *
 * Menampilkan formulir kuesioner tracer study universitas kepada alumni
 * menggunakan skema bank soal terpadu (KuesionerSection, KuesionerPertanyaan,
 * KuesionerOpsi, dan KuesionerJawaban).
 */
class KuesionerController extends Controller
{
    /**
     * Menampilkan Halaman Kuesioner Tracer Study Alumni
     */
    public function tampilkanKuesioner(): Response
    {
        // 1. Ambil data pengguna dan relasi profil biodata
        $pengguna = Auth::user();
        $biodata = $pengguna->biodata;

        if (! $biodata) {
            abort(403, 'Profil Biodata tidak ditemukan.');
        }

        $biodata->load(['dataAkademik', 'yudisium', 'perusahaan.propinsi', 'perusahaan.kabupaten', 'atasan', 'user']);

        // 2. Sinkronisasi otomatis data profil (Identitas & Perusahaan/Atasan) ke kuesioner_jawaban
        KuesionerSyncService::syncProfileResponses($biodata);

        // 3. Ambil seksi kuesioner universitas aktif beserta butir pertanyaan dan opsi
        $sections = KuesionerSection::where('target', 'univ')
            ->where('is_active', true)
            ->with(['pertanyaan' => function ($query) {
                $query->where('kuesioner_pertanyaan.is_active', true)
                    ->wherePivot('is_active', true)
                    ->with('opsi');
            }])
            ->orderBy('order', 'asc')
            ->get();

        $profileCodesUpper = array_map('strtoupper', KelengkapanTracerService::PROFILE_MANAGED_CODES);

        // Transformasi ke struktur yang diharapkan Frontend Kuesioner.vue
        $formattedSections = [];
        foreach ($sections as $sec) {
            $subpertanyaans = [];

            foreach ($sec->pertanyaan as $pertanyaan) {
                $codeUpper = strtoupper(trim((string) $pertanyaan->kode_pertanyaan));
                $pivotTampilDi = strtolower(trim((string) ($pertanyaan->pivot->tampil_di ?? '')));

                // Saring pertanyaan yang dikelola di modul profil
                if (in_array($codeUpper, $profileCodesUpper, true) || in_array($pivotTampilDi, ['profil', 'profile'], true)) {
                    continue;
                }

                $isMandatory = (bool) ($pertanyaan->pivot->wajib ?? true);

                $subpertanyaans[] = [
                    'id' => $pertanyaan->id,
                    'kode_pertanyaan' => $pertanyaan->kode_pertanyaan,
                    'label_pertanyaan' => $pertanyaan->label_pertanyaan,
                    'subpertanyaan' => $pertanyaan->teks_pertanyaan,
                    'type' => $pertanyaan->tipe,
                    'wajib' => $isMandatory,
                    'is_mandatory' => $isMandatory,
                    'is_optional' => ! $isMandatory,
                    'optional' => ! $isMandatory,
                    'is_header' => in_array($pertanyaan->tipe, ['header', 'section_header'], true),
                    'order' => $pertanyaan->pivot->order ?? 0,
                    'detils' => $pertanyaan->opsi->map(fn ($opt) => [
                        'id' => $opt->id,
                        'option_text' => $opt->label,
                        'kode_opsi' => $opt->kode_opsi,
                        'jump_to' => $opt->lompat_ke_pertanyaan_id,
                    ])->values()->all(),
                ];
            }

            if (! empty($subpertanyaans)) {
                $formattedSections[] = [
                    'id' => $sec->id,
                    'order' => $sec->order,
                    'title' => $sec->judul ?: ('Bagian '.$sec->order),
                    'section' => $sec->kode_section,
                    'description' => $sec->deskripsi,
                    'subpertanyaans' => $subpertanyaans,
                ];
            }
        }

        if (empty($formattedSections)) {
            return Inertia::render('Alumni/Kuesioner', [
                'error' => 'Tidak ada kuesioner aktif saat ini.',
                'questionnaire' => null,
                'kuesioner' => null,
                'initialAnswers' => [],
            ]);
        }

        $kuesionerPayload = [
            'id' => $sections->first()?->id ?? 1,
            'title' => 'Tracer Study Universitas Kristen Duta Wacana',
            'sections' => $formattedSections,
        ];

        // 4. Ambil respon jawaban alumni yang tersimpan di kuesioner_jawaban
        $jawabanTersimpan = KuesionerJawaban::where('biodata_id', $biodata->id)
            ->where('responden_type', 'alumni')
            ->get()
            ->keyBy('pertanyaan_id');

        // 5. Data autofill untuk prefill jawaban
        $autofillData = DB::table('v_alumni_kuesioner_autofill')->where('user_id', $pengguna->id)->first();

        // 6. Rakit nilai awal ($jawabanAwal)
        $jawabanAwal = [];
        foreach ($formattedSections as $bagian) {
            foreach ($bagian['subpertanyaans'] as $pertanyaan) {
                $pId = $pertanyaan['id'];
                $pType = $pertanyaan['type'];
                $pCode = strtoupper($pertanyaan['kode_pertanyaan'] ?? '');
                $options = collect($pertanyaan['detils'] ?? []);

                if (isset($jawabanTersimpan[$pId])) {
                    $saved = $jawabanTersimpan[$pId];
                    $savedText = $saved->jawaban_teks ?? '';
                    $savedJson = is_array($saved->jawaban_json) ? $saved->jawaban_json : [];

                    if (in_array($pType, ['checkbox', 'multiple_choice', 'multiple_choice_text'], true)) {
                        $cleanArr = [];
                        $customText = '';

                        $otherOption = $options->first(function ($opt) {
                            $t = strtolower($opt['option_text']);

                            return str_contains($t, 'lainnya') || str_contains($t, 'tuliskan') || str_contains($t, '...');
                        });

                        $rawItems = ! empty($savedJson) ? $savedJson : (! empty($savedText) ? array_map('trim', explode(',', $savedText)) : []);

                        foreach ($rawItems as $item) {
                            if (is_string($item) && str_starts_with($item, 'Lainnya: ')) {
                                $customText = trim(substr($item, 9));
                                $cleanArr[] = $otherOption ? $otherOption['option_text'] : 'Lainnya';
                            } else {
                                $cleanArr[] = $item;
                            }
                        }

                        $jawabanAwal[$pId] = $cleanArr;
                        if (! empty($customText)) {
                            $jawabanAwal[$pId.'_custom'] = $customText;
                        }
                    } elseif (in_array($pType, ['single_choice', 'radio', 'dropdown', 'searchable_select'], true)) {
                        if (str_starts_with($savedText, 'Lainnya: ')) {
                            $customText = trim(substr($savedText, 9));
                            $otherOption = $options->first(function ($opt) {
                                $t = strtolower($opt['option_text']);

                                return str_contains($t, 'lainnya') || str_contains($t, 'tuliskan') || str_contains($t, '...');
                            });
                            $jawabanAwal[$pId] = $otherOption ? $otherOption['option_text'] : 'Lainnya';
                            if (! empty($customText)) {
                                $jawabanAwal[$pId.'_custom'] = $customText;
                            }
                        } else {
                            $matchedOpt = $options->first(function ($opt) use ($savedText) {
                                $t = strtolower(trim((string) $savedText));
                                $optText = strtolower(trim((string) $opt['option_text']));
                                $optCode = strtolower(trim((string) ($opt['kode_opsi'] ?? '')));

                                return $t === $optText
                                    || $t === $optCode
                                    || ($t === 'pekerja' && str_contains($optText, 'bekerja'))
                                    || ($t === 'bekerja' && str_contains($optText, 'bekerja'))
                                    || ($t === 'mencari kerja' && str_contains($optText, 'mencari kerja'))
                                    || ($t === 'belum memungkinkan bekerja' && str_contains($optText, 'belum memungkinkan'));
                            });

                            $jawabanAwal[$pId] = $matchedOpt ? $matchedOpt['option_text'] : $savedText;
                        }
                    } elseif (in_array($pType, ['radio_input', 'radio_text'], true)) {
                        $inputsObj = [];
                        foreach ($options as $o) {
                            $inputsObj[$o['id']] = '';
                        }

                        if (! empty($savedJson)) {
                            $pilihan = $savedJson['pilihan'] ?? ($savedJson['selected'] ?? '');
                            $input = $savedJson['input'] ?? ($savedJson['text'] ?? '');
                            $rawInputs = is_array($savedJson['inputs'] ?? null) ? $savedJson['inputs'] : [];

                            $matchedOpt = $options->first(fn ($o) => strcasecmp($o['option_text'], $pilihan) === 0);
                            if ($matchedOpt) {
                                $inputsObj[$matchedOpt['id']] = (string) ($rawInputs[$matchedOpt['id']] ?? $input);
                            }

                            $jawabanAwal[$pId] = [
                                'selected' => $matchedOpt ? $matchedOpt['option_text'] : $pilihan,
                                'input' => $matchedOpt ? $inputsObj[$matchedOpt['id']] : $input,
                                'inputs' => $inputsObj,
                            ];
                        } elseif (! empty($savedText)) {
                            $matchedOption = null;
                            $matchedInput = '';

                            foreach ($options as $o) {
                                if (str_starts_with($savedText, $o['option_text'])) {
                                    $matchedOption = $o['option_text'];
                                    $remainder = trim(substr($savedText, strlen($o['option_text'])));
                                    $matchedInput = ltrim($remainder, ': ');
                                    $inputsObj[$o['id']] = $matchedInput;
                                    break;
                                }
                            }

                            $jawabanAwal[$pId] = [
                                'selected' => $matchedOption ?? $savedText,
                                'input' => $matchedInput,
                                'inputs' => $inputsObj,
                            ];
                        } else {
                            $jawabanAwal[$pId] = [
                                'selected' => '',
                                'input' => '',
                                'inputs' => $inputsObj,
                            ];
                        }
                    } elseif (in_array($pType, ['matrix', 'matrix_dual', 'multiple_textbox'], true)) {
                        if (! empty($savedJson)) {
                            $jawabanAwal[$pId] = $savedJson;
                        } elseif (! empty($savedText)) {
                            $decoded = json_decode($savedText, true);
                            $jawabanAwal[$pId] = is_array($decoded) ? $decoded : [];
                        } else {
                            $jawabanAwal[$pId] = [];
                        }
                    } elseif ($pType === 'multiple_number') {
                        $formatted = [];
                        foreach ($savedJson as $k => $v) {
                            if ($k === 'total') {
                                continue;
                            }
                            $numVal = is_numeric($v) ? (float) $v : 0;
                            $formatted[$k] = (int) $numVal;
                        }

                        $gajiVal = ! empty($autofillData->F505) ? $autofillData->F505 : ($biodata->gaji ?? null);
                        if (empty($formatted) && ! empty($gajiVal)) {
                            $firstOpt = $options->first();
                            $optCode = $firstOpt ? ($firstOpt['kode_opsi'] ?: $firstOpt['id']) : 'F5051';
                            $formatted[$optCode] = (int) ((float) $gajiVal);
                        }

                        $jawabanAwal[$pId] = $formatted;
                    } else {
                        $val = $savedText;
                        if ($pCode === 'F18B' && ! empty($biodata->perguruan_tinggi)) {
                            $val = $biodata->perguruan_tinggi;
                        } elseif ($pCode === 'F18C' && ! empty($biodata->pendidikan_prodi)) {
                            $val = $biodata->pendidikan_prodi;
                        }
                        $jawabanAwal[$pId] = $val;
                    }
                } else {
                    // Penyiapan nilai default awal jika belum ada jawaban
                    if (in_array($pType, ['checkbox', 'multiple_choice', 'matrix', 'matrix_dual', 'multiple_textbox'], true)) {
                        $jawabanAwal[$pId] = [];
                    } elseif ($pType === 'multiple_number') {
                        $formatted = [];
                        $gajiVal = ! empty($autofillData->F505) ? $autofillData->F505 : ($biodata->gaji ?? null);
                        if (! empty($gajiVal)) {
                            $firstOpt = $options->first();
                            $optCode = $firstOpt ? ($firstOpt['kode_opsi'] ?: $firstOpt['id']) : 'F5051';
                            $formatted[$optCode] = (int) ((float) $gajiVal);
                        }
                        $jawabanAwal[$pId] = $formatted;
                    } elseif (in_array($pType, ['radio_input', 'radio_text'], true)) {
                        $inputsObj = [];
                        foreach ($options as $o) {
                            $inputsObj[$o['id']] = '';
                        }
                        $jawabanAwal[$pId] = [
                            'selected' => '',
                            'input' => '',
                            'inputs' => $inputsObj,
                        ];
                    } else {
                        $val = $autofillData->$pCode ?? ($autofillData->{strtolower($pCode)} ?? '');

                        if (empty($val)) {
                            if ($pCode === 'F18B') {
                                $val = $biodata->perguruan_tinggi ?? '';
                            } elseif ($pCode === 'F18C') {
                                $val = $biodata->pendidikan_prodi ?? '';
                            }
                        }

                        if ($pCode === 'F8' && ! empty($val)) {
                            $matchedOpt = $options->first(function ($opt) use ($val) {
                                return strcasecmp($opt['option_text'], $val) === 0 || str_starts_with(strtolower($opt['option_text']), strtolower($val));
                            });
                            $val = $matchedOpt ? $matchedOpt['option_text'] : $val;
                        }

                        $jawabanAwal[$pId] = $val;
                    }
                }
            }
        }

        return Inertia::render('Alumni/Kuesioner', [
            'kuesioner' => $kuesionerPayload,
            'questionnaire' => $kuesionerPayload,
            'initialAnswers' => $jawabanAwal,
            'questionnaireStorageKey' => 'alumni_'.$pengguna->id.'_kuesioner_'.$kuesionerPayload['id'],
            'error' => null,
        ]);
    }
}
