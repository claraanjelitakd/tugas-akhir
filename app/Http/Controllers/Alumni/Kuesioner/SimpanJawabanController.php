<?php

namespace App\Http\Controllers\Alumni\Kuesioner;

use App\Http\Controllers\Controller;
use App\Models\Atasan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\Perusahaan;
use App\Models\QuestionMapping;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SimpanJawabanController
 *
 * Menangani validasi dan persistensi array jawaban kuesioner tracer study alumni
 * ke basis data terpadu (kuesioner_jawaban) serta menyinkronkan atribut profil
 * biodata, perusahaan, dan kontak atasan pengguna lulusan.
 */
class SimpanJawabanController extends Controller
{
    /**
     * Nilai yang diizinkan oleh enum perusahaan.skala.
     *
     * @var array<int, string>
     */
    private const COMPANY_SCALE_VALUES = [
        'Lokal',
        'Regional/Lokal',
        'Nasional',
        'Internasional',
    ];

    /**
     * Simpan respon kuesioner tracer study
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->simpanJawabanKuesioner($request);
    }

    /**
     * Mengonversi input jawaban apa pun ke bentuk string yang bersih dan representatif.
     */
    private function flattenToString(mixed $value): string
    {
        if (is_array($value)) {
            // 1. Jika memiliki struktur radio_input (selected / pilihan & input / text)
            if (isset($value['selected']) || isset($value['pilihan'])) {
                $sel = $value['selected'] ?? ($value['pilihan'] ?? '');
                $inp = $value['input'] ?? ($value['text'] ?? '');
                if (is_scalar($sel) && trim((string) $sel) !== '') {
                    $selStr = trim((string) $sel);

                    return (is_scalar($inp) && trim((string) $inp) !== '')
                        ? $selStr.': '.trim((string) $inp)
                        : $selStr;
                }
            }

            // 2. Jika merupakan array umum, ekstrak secara rekursif
            $parts = [];
            foreach ($value as $item) {
                $str = $this->flattenToString($item);
                if ($str !== '') {
                    $parts[] = $str;
                }
            }

            return implode(', ', array_unique($parts));
        }

        // 3. Jika skalar (string / number / float) dan bukan boolean
        if (is_scalar($value) && ! is_bool($value)) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * Method pemroses utama penyimpanan jawaban kuesioner alumni
     */
    public function simpanJawabanKuesioner(Request $request): RedirectResponse
    {
        // 1. Validasi keberadaan profil alumni
        $pengguna = $request->user();
        $biodata = $pengguna->biodata;

        if (! $biodata) {
            return redirect()->back()->with('error', 'Data biodata alumni tidak ditemukan.');
        }

        // 2. Ambil data array jawaban dari request form
        $jawabanMasuk = $request->input('answers', []);
        if (empty($jawabanMasuk) || ! is_array($jawabanMasuk)) {
            return redirect()->back()->with('error', 'Tidak ada data jawaban yang dikirim.');
        }

        // 3. Filter key numerik (ID butir pertanyaan)
        $kumpulanIdPertanyaan = array_filter(array_keys($jawabanMasuk), 'is_numeric');

        $pertanyaanModels = KuesionerPertanyaan::whereIn('id', $kumpulanIdPertanyaan)
            ->with(['opsi', 'sections'])
            ->get()
            ->keyBy('id');

        // 4. Ambil aturan pemetaan kolom profil (QuestionMapping) jika relevan
        $pemetaan = QuestionMapping::whereIn('table_name', ['biodata', 'biodatas', 'alumnis', 'data_akademik', 'data_akademiks', 'perusahaan', 'companies', 'atasan', 'atasans'])
            ->whereIn('question_id', $kumpulanIdPertanyaan)
            ->get()
            ->keyBy('question_id');

        // 5. Penampung pembaruan relasi profil alumni
        $dataUpdateBiodata = [];
        $dataUpdatePerusahaan = [];
        $dataUpdateAtasan = [];

        $kolomBiodata = $biodata->getFillable();
        $kolomPerusahaan = (new Perusahaan)->getFillable();
        $kolomAtasan = (new Atasan)->getFillable();

        $defaultUnivSection = KuesionerSection::where('target', 'univ')->first();

        foreach ($jawabanMasuk as $idPertanyaan => $jawaban) {
            if (is_string($idPertanyaan) && str_ends_with($idPertanyaan, '_custom')) {
                continue;
            }

            if (! is_numeric($idPertanyaan)) {
                continue;
            }

            $pertanyaan = $pertanyaanModels[$idPertanyaan] ?? null;
            if (! $pertanyaan) {
                continue;
            }

            $customText = isset($jawabanMasuk[$idPertanyaan.'_custom']) ? trim((string) $jawabanMasuk[$idPertanyaan.'_custom']) : null;

            $answerText = null;
            $answerJson = null;

            /**
             * Jawaban memakai dua wadah kanonik: jawaban_teks untuk nilai scalar
             * atau representasi baca, dan jawaban_json untuk data terstruktur.
             * Tipe master menentukan normalisasi sebelum kedua wadah tersebut
             * diisi; tipe angka tetap perlu divalidasi/cast saat dianalisis.
             */
            switch ($pertanyaan->tipe) {
                case 'multiple_choice':
                case 'checkbox':
                case 'multiple_choice_text':
                    if (is_array($jawaban)) {
                        $processedArray = [];
                        foreach ($jawaban as $item) {
                            $textItem = $this->flattenToString($item);
                            if ($textItem === '') {
                                continue;
                            }
                            $isLainnya = str_contains(strtolower($textItem), 'lainnya')
                                || str_contains(strtolower($textItem), 'tuliskan')
                                || str_contains(strtolower($textItem), '...')
                                || str_starts_with($textItem, 'Lainnya: ');

                            if ($isLainnya && ! empty($customText)) {
                                $processedArray[] = 'Lainnya: '.$customText;
                            } else {
                                $processedArray[] = $textItem;
                            }
                        }
                        if (! empty($processedArray)) {
                            $answerJson = array_values(array_unique($processedArray));
                            $answerText = implode(', ', $answerJson);
                        }
                    } elseif (is_scalar($jawaban) && ! is_bool($jawaban) && trim((string) $jawaban) !== '') {
                        $textVal = trim((string) $jawaban);
                        $answerJson = [$textVal];
                        $answerText = $textVal;
                    }
                    break;

                case 'rating_5':
                case 'single_choice':
                case 'radio':
                case 'dropdown':
                case 'searchable_select':
                    $textVal = $this->flattenToString($jawaban);

                    if ($textVal !== '') {
                        $isLainnya = str_contains(strtolower($textVal), 'lainnya')
                            || str_contains(strtolower($textVal), 'tuliskan')
                            || str_contains(strtolower($textVal), '...')
                            || str_starts_with($textVal, 'Lainnya: ');

                        if ($isLainnya && ! empty($customText)) {
                            $answerText = 'Lainnya: '.$customText;
                            $answerJson = ['selected' => $textVal, 'custom' => $customText];
                        } else {
                            $answerText = $textVal;
                        }
                    }
                    break;

                case 'radio_input':
                case 'radio_text':
                    if (is_array($jawaban)) {
                        $selected = trim((string) ($jawaban['selected'] ?? ($jawaban['pilihan'] ?? '')));
                        $inputVal = trim((string) ($jawaban['input'] ?? ($jawaban['text'] ?? '')));
                        $inputsMap = is_array($jawaban['inputs'] ?? null) ? $jawaban['inputs'] : [];

                        if (! empty($selected)) {
                            $answerJson = [
                                'selected' => $selected,
                                'input' => $inputVal,
                                'inputs' => $inputsMap,
                            ];
                            $answerText = ! empty($inputVal) ? "{$selected}: {$inputVal}" : $selected;
                        }
                    } elseif (is_scalar($jawaban) && ! is_bool($jawaban) && trim((string) $jawaban) !== '') {
                        $answerText = trim((string) $jawaban);
                        $answerJson = ['selected' => $answerText, 'input' => ''];
                    }
                    break;

                case 'matrix':
                case 'matrix_dual':
                case 'multiple_textbox':
                    if (is_array($jawaban)) {
                        $answerJson = $jawaban;
                        $answerText = json_encode($jawaban, JSON_UNESCAPED_UNICODE);
                    } elseif (is_string($jawaban) && trim($jawaban) !== '') {
                        $decoded = json_decode($jawaban, true);
                        if (is_array($decoded)) {
                            $answerJson = $decoded;
                            $answerText = $jawaban;
                        } else {
                            $answerText = trim($jawaban);
                        }
                    }
                    break;

                case 'multiple_number':
                    if (is_array($jawaban)) {
                        $formattedNumbers = [];
                        $total = 0;
                        $opsiList = $pertanyaan->opsi->keyBy('kode_opsi');
                        $parts = [];

                        foreach ($jawaban as $k => $v) {
                            if ($k === 'total') {
                                continue;
                            }
                            $num = is_numeric($v) ? (float) $v : 0;
                            if ($num > 0 && $num < 1000000) {
                                $num *= 1000;
                            }
                            $intNum = (int) $num;
                            $formattedNumbers[$k] = $intNum;
                            $total += $intNum;

                            $optLabel = $opsiList->get($k)?->label ?? $k;
                            $parts[] = "{$optLabel}: Rp ".number_format($intNum, 0, ',', '.');
                        }

                        $formattedNumbers['total'] = $total;
                        $parts[] = 'Total Pendapatan: Rp '.number_format($total, 0, ',', '.');

                        $answerJson = $formattedNumbers;
                        $answerText = implode(', ', $parts);
                    } elseif (is_numeric($jawaban)) {
                        $num = (float) $jawaban;
                        if ($num > 0 && $num < 1000000) {
                            $num *= 1000;
                        }
                        $intNum = (int) $num;
                        $answerText = (string) $intNum;
                        $answerJson = ['value' => $intNum, 'total' => $intNum];
                    }
                    break;

                case 'date':
                case 'text':
                case 'textarea':
                case 'number':
                default:
                    $strVal = $this->flattenToString($jawaban);
                    if ($strVal !== '') {
                        $answerText = $strVal;
                    }
                    break;
            }

            // Tentukan section tujuan
            $targetSectionId = $pertanyaan->sections->first()?->id ?? $defaultUnivSection?->id;

            // Cari opsi pencocokan
            $opsi = null;
            $kodeOpsiResult = null;

            if (! empty($answerText)) {
                $opsi = $pertanyaan->opsi->first(fn ($o) => strcasecmp($o->label, $answerText) === 0 || $o->kode_opsi === $answerText);
                $kodeOpsiResult = $opsi?->kode_opsi;
            }

            if (empty($kodeOpsiResult) && ! empty($answerJson) && is_array($answerJson)) {
                $matchingCodes = $pertanyaan->opsi
                    ->filter(fn ($o) => in_array($o->label, $answerJson, true) || in_array($o->kode_opsi, $answerJson, true))
                    ->pluck('kode_opsi')
                    ->filter()
                    ->values()
                    ->all();
                if (! empty($matchingCodes)) {
                    $kodeOpsiResult = json_encode($matchingCodes, JSON_UNESCAPED_UNICODE);
                }
            }

            // Simpan langsung ke kuesioner_jawaban terpadu
            KuesionerJawaban::updateOrCreate(
                [
                    'responden_type' => 'alumni',
                    'biodata_id' => $biodata->id,
                    'pertanyaan_id' => $pertanyaan->id,
                ],
                [
                    'section_id' => $targetSectionId,
                    'evaluasi_atasan_id' => null,
                    'kode_pertanyaan' => $pertanyaan->kode_pertanyaan,
                    'opsi_id' => $opsi?->id,
                    'kode_opsi' => $kodeOpsiResult,
                    'jawaban_teks' => $answerText,
                    'jawaban_json' => $answerJson,
                ]
            );

            // Jika pertanyaan adalah F8, sinkronkan kategori_pekerjaan
            $kodeUpper = strtoupper(trim((string) $pertanyaan->kode_pertanyaan));
            if ($kodeUpper === 'F8' && ! empty($answerText)) {
                $ansLower = strtolower($answerText);
                if (str_contains($ansLower, 'bekerja')) {
                    $dataUpdateBiodata['kategori_pekerjaan'] = 'Pekerja';
                } elseif (str_contains($ansLower, 'wiraswasta')) {
                    $dataUpdateBiodata['kategori_pekerjaan'] = 'Wiraswasta';
                } elseif (str_contains($ansLower, 'melanjutkan pendidikan')) {
                    $dataUpdateBiodata['kategori_pekerjaan'] = 'Melanjutkan Pendidikan';
                } elseif (str_contains($ansLower, 'belum memungkinkan')) {
                    $dataUpdateBiodata['kategori_pekerjaan'] = 'Belum Memungkinkan Bekerja';
                } elseif (str_contains($ansLower, 'mencari kerja')) {
                    $dataUpdateBiodata['kategori_pekerjaan'] = 'Mencari Kerja';
                }
            }

            // Sinkronisasi pemetaan kolom tabel lain
            if (isset($pemetaan[$idPertanyaan]) && $answerText !== null) {
                $kolom = $pemetaan[$idPertanyaan]->column_name;
                $tabel = $pemetaan[$idPertanyaan]->table_name;

                if (in_array($tabel, ['data_akademik', 'data_akademiks', 'biodata', 'biodatas', 'alumnis'], true) && in_array($kolom, $kolomBiodata, true)) {
                    $dataUpdateBiodata[$kolom] = $answerText;
                } elseif (in_array($tabel, ['perusahaan', 'companies'], true) && in_array($kolom, $kolomPerusahaan, true)) {
                    if ($kolom === 'skala' && ! in_array(trim($answerText), self::COMPANY_SCALE_VALUES, true)) {
                        continue;
                    }
                    $dataUpdatePerusahaan[$kolom] = $answerText;
                } elseif (in_array($tabel, ['atasan', 'atasans'], true) && in_array($kolom, $kolomAtasan, true)) {
                    $dataUpdateAtasan[$kolom] = $answerText;
                }
            }
        }

        if (! empty($dataUpdateBiodata)) {
            $biodata->update($dataUpdateBiodata);
        }

        if (! empty($dataUpdatePerusahaan)) {
            if ($biodata->perusahaan_id && $biodata->perusahaan) {
                $biodata->perusahaan->update($dataUpdatePerusahaan);
            } elseif (! empty($dataUpdatePerusahaan['nama_perusahaan'])) {
                $comp = Perusahaan::create($dataUpdatePerusahaan);
                $biodata->update(['perusahaan_id' => $comp->id]);
            }
        }

        if (! empty($dataUpdateAtasan)) {
            $namaAtasan = ! empty($dataUpdateAtasan['nama']) ? $dataUpdateAtasan['nama'] : ($biodata->atasan?->nama ?? 'Atasan');
            $emailAtasan = ! empty($dataUpdateAtasan['email']) ? $dataUpdateAtasan['email'] : ($biodata->atasan?->email ?? null);
            $teleponAtasan = ! empty($dataUpdateAtasan['telepon']) ? $dataUpdateAtasan['telepon'] : ($biodata->atasan?->telepon ?? null);

            if ($biodata->atasan_id && $biodata->atasan) {
                $biodata->atasan->update([
                    'perusahaan_id' => $biodata->perusahaan_id ?: $biodata->atasan->perusahaan_id,
                    'nama' => $namaAtasan,
                    'email' => $emailAtasan,
                    'telepon' => $teleponAtasan,
                ]);
            } elseif (! empty($namaAtasan) || ! empty($emailAtasan)) {
                $atasan = Atasan::create([
                    'perusahaan_id' => $biodata->perusahaan_id,
                    'nama' => $namaAtasan,
                    'email' => $emailAtasan,
                    'telepon' => $teleponAtasan,
                ]);
                $biodata->update(['atasan_id' => $atasan->id]);
            }
        }

        // Sinkronisasi field profil ke kuesioner_jawaban
        KuesionerSyncService::syncProfileResponses($biodata);

        return redirect()->back()->with('success', 'Jawaban berhasil disimpan.');
    }
}
