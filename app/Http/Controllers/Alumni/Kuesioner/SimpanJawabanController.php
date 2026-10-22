<?php

namespace App\Http\Controllers\Alumni\Kuesioner;

use App\Http\Controllers\Controller;
use App\Models\Atasan;
use App\Models\Perusahaan;
use App\Models\QuestionMapping;
use App\Models\RefSubpertanyaan2021;
use App\Models\Tracer;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SimpanJawabanController
 *
 * Fungsi: Menangani proses validasi dan penyimpanan array jawaban kuesioner alumni ke database
 * serta melakukan sinkronisasi otomatis ke profil biodata dan data QuestionMapping.
 *
 * Fitur:
 * 1. Menerima payload jawaban dari Frontend (single choice, multiple choice, matriks, text, number).
 * 2. Menyimpan jawaban ke tabel `tracer`.
 * 3. Memperbarui tabel transaksional `biodata`, `perusahaan`, dan `atasan` jika butir pertanyaan
 *    terhubung ke mapping profil. Mapping akademik hanya diarahkan ke kolom biodata yang setara.
 * 4. Menjalankan sinkronisasi terpadu melalui `KuesionerSyncService`.
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
    public function store(Request $request)
    {
        return $this->simpanJawabanKuesioner($request);
    }

    /**
     * Mengonversi input jawaban apa pun ke bentuk string yang bersih dan aman dari exception array-to-string.
     *
     * @param  mixed  $value  Nilai input (array, object, string, number, dll.)
     * @return string Nilai string representasi jawaban
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
     *
     * @return RedirectResponse
     */
    public function simpanJawabanKuesioner(Request $request)
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

        // 3. Filter key numerik (ID subpertanyaan) dan muat model data pertanyaan dari database
        $kumpulanIdPertanyaan = array_filter(array_keys($jawabanMasuk), 'is_numeric');

        $subpertanyaanModels = RefSubpertanyaan2021::whereIn('id', $kumpulanIdPertanyaan)
            ->with('detils')
            ->get()
            ->keyBy('id');

        // 4. Ambil aturan pemetaan kolom khusus (QuestionMapping) untuk sinkronisasi otomatis
        $pemetaan = QuestionMapping::whereIn('table_name', ['biodata', 'biodatas', 'alumnis', 'data_akademik', 'data_akademiks', 'perusahaan', 'companies', 'atasan', 'atasans'])
            ->whereIn('question_id', $kumpulanIdPertanyaan)
            ->get()
            ->keyBy('question_id');

        // 5. Penampung data update tabel profil terkait
        $dataUpdateBiodata = [];
        $dataUpdatePerusahaan = [];
        $dataUpdateAtasan = [];

        $kolomBiodata = $biodata->getFillable();
        $kolomPerusahaan = (new Perusahaan)->getFillable();
        $kolomAtasan = (new Atasan)->getFillable();

        foreach ($jawabanMasuk as $idPertanyaan => $jawaban) {
            // Lewati key string _custom karena diproses langsung bersama ID pertanyaan induknya
            if (is_string($idPertanyaan) && str_ends_with($idPertanyaan, '_custom')) {
                continue;
            }

            if (! is_numeric($idPertanyaan)) {
                continue;
            }

            $subpertanyaan = $subpertanyaanModels[$idPertanyaan] ?? null;
            if (! $subpertanyaan) {
                continue;
            }

            $customText = isset($jawabanMasuk[$idPertanyaan.'_custom']) ? trim((string) $jawabanMasuk[$idPertanyaan.'_custom']) : null;

            $answerText = null;
            $answerJson = null;

            switch ($subpertanyaan->type) {
                case 'multiple_choice':
                case 'checkbox':
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
                        } else {
                            $answerText = $textVal;
                        }
                    }
                    break;

                case 'radio_input':
                case 'radio_text':
                    if (is_array($jawaban)) {
                        $pilihan = $jawaban['pilihan'] ?? ($jawaban['selected'] ?? null);
                        $input = $jawaban['input'] ?? ($jawaban['text'] ?? null);
                        if (is_scalar($pilihan) && trim((string) $pilihan) !== '') {
                            $pilihanStr = trim((string) $pilihan);

                            $cleanInputs = [];
                            foreach ($subpertanyaan->detils as $o) {
                                $cleanInputs[$o->id] = '';
                            }

                            $matchedOpt = $subpertanyaan->detils->first(fn ($o) => strcasecmp($o->option_text, $pilihanStr) === 0);
                            $inputStr = '';
                            if ($matchedOpt) {
                                $rawVal = $jawaban['inputs'][$matchedOpt->id] ?? $input;
                                $inputStr = (is_scalar($rawVal) && trim((string) $rawVal) !== '') ? trim((string) $rawVal) : '';
                                $cleanInputs[$matchedOpt->id] = $inputStr;
                            } else {
                                $inputStr = (is_scalar($input) && trim((string) $input) !== '') ? trim((string) $input) : '';
                            }

                            $answerText = $inputStr !== '' ? $pilihanStr.': '.$inputStr : $pilihanStr;
                            $answerJson = [
                                'selected' => $pilihanStr,
                                'input' => $inputStr,
                                'inputs' => $cleanInputs,
                            ];
                        }
                    } elseif (is_scalar($jawaban) && ! is_bool($jawaban) && trim((string) $jawaban) !== '') {
                        $answerText = trim((string) $jawaban);
                    }
                    break;

                case 'matrix':
                case 'matrix_dual':
                case 'multiple_textbox':
                    if (is_array($jawaban)) {
                        $answerJson = $jawaban;
                        $answerText = json_encode($jawaban, JSON_UNESCAPED_UNICODE);
                    } elseif (is_scalar($jawaban) && ! is_bool($jawaban) && trim((string) $jawaban) !== '') {
                        $answerText = trim((string) $jawaban);
                    }
                    break;

                case 'multiple_number':
                    if (is_array($jawaban)) {
                        $total = 0;
                        $processed = [];
                        $textParts = [];
                        $optMap = $subpertanyaan->detils->keyBy(function ($d) {
                            return (string) ($d->kode_opsi ?: ($d->code ?: $d->id));
                        });

                        foreach ($jawaban as $optKey => $val) {
                            if (is_array($val)) {
                                continue;
                            }
                            $numVal = is_numeric($val) ? (float) $val : 0;
                            // Jika nilai diinput ribuan (< 1.000.000 dan > 0), kalikan 1000
                            if ($numVal > 0 && $numVal < 1000000) {
                                $numVal = $numVal * 1000;
                            }
                            $processed[$optKey] = (int) $numVal;
                            $total += (int) $numVal;

                            $optLabel = $optMap[(string) $optKey]->option_text ?? ($subpertanyaan->detils->firstWhere('id', $optKey)?->option_text ?? $optKey);
                            $textParts[] = $optLabel.': Rp '.number_format($numVal, 0, ',', '.');
                        }
                        $processed['total'] = (int) $total;
                        $textParts[] = 'Total Pendapatan: Rp '.number_format($total, 0, ',', '.');

                        $answerJson = $processed;
                        $answerText = implode(', ', $textParts);
                    }
                    break;

                default:
                    $textVal = $this->flattenToString($jawaban);
                    if ($textVal !== '') {
                        $answerText = $textVal;
                    }
                    if (is_array($jawaban)) {
                        $answerJson = $jawaban;
                    }
                    break;
            }

            if ($answerText === null && $answerJson === null) {
                Tracer::where('biodata_id', $biodata->id)
                    ->where(function ($q) use ($subpertanyaan) {
                        $q->where('question_id', $subpertanyaan->id)
                            ->orWhere('kode_pertanyaan', $subpertanyaan->kode_pertanyaan);
                    })
                    ->delete();

                continue;
            }

            // Pastikan tidak ada data duplikat dengan kode_pertanyaan yang sama sebelum updateOrCreate
            Tracer::where('biodata_id', $biodata->id)
                ->where('kode_pertanyaan', $subpertanyaan->kode_pertanyaan)
                ->where('question_id', '!=', $subpertanyaan->id)
                ->delete();

            Tracer::updateOrCreate(
                [
                    'biodata_id' => $biodata->id,
                    'question_id' => $subpertanyaan->id,
                ],
                [
                    'nim' => $biodata->nim,
                    'kelompok' => $subpertanyaan->kelompok,
                    'kode_pertanyaan' => $subpertanyaan->kode_pertanyaan,
                    'subpertanyaan' => $subpertanyaan->subpertanyaan,
                    'answer' => $answerText,
                    'answer_json' => $answerJson,
                    'keterangan' => $subpertanyaan->keterangan,
                    'tahun_lulus' => $biodata->tahun_lulus ?? $biodata->dataAkademik?->tahun_lulus,
                ]
            );

            // Jika pertanyaan adalah F8, sinkronisasikan nilai kategori_pekerjaan di tabel biodata
            if ($subpertanyaan->kode_pertanyaan === 'F8' && ! empty($answerText)) {
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

            // Jika ada relasi mapping yang resmi tercatat di database, sinkronisasi datanya ke tabel terkait
            if (isset($pemetaan[$idPertanyaan]) && $answerText !== null) {
                $kolom = $pemetaan[$idPertanyaan]->column_name;
                $tabel = $pemetaan[$idPertanyaan]->table_name;

                if (in_array($tabel, ['data_akademik', 'data_akademiks']) && in_array($kolom, $kolomBiodata)) {
                    $dataUpdateBiodata[$kolom] = $answerText;
                } elseif (in_array($tabel, ['biodata', 'biodatas', 'alumnis']) && in_array($kolom, $kolomBiodata)) {
                    $dataUpdateBiodata[$kolom] = $answerText;
                } elseif (in_array($tabel, ['perusahaan', 'companies']) && in_array($kolom, $kolomPerusahaan)) {
                    if ($kolom === 'skala' && ! in_array(trim($answerText), self::COMPANY_SCALE_VALUES, true)) {
                        continue;
                    }

                    $dataUpdatePerusahaan[$kolom] = $answerText;
                } elseif (in_array($tabel, ['atasan', 'atasans']) && in_array($kolom, $kolomAtasan)) {
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
                    'nama' => $namaAtasan,
                    'email' => $emailAtasan,
                    'telepon' => $teleponAtasan,
                ]);
            } elseif (! empty($namaAtasan) || ! empty($emailAtasan)) {
                $atasan = Atasan::create([
                    'nama' => $namaAtasan,
                    'email' => $emailAtasan,
                    'telepon' => $teleponAtasan,
                ]);
                $biodata->update(['atasan_id' => $atasan->id]);
            }
        }

        // Pastikan jawaban profil (F1..F2H) selalu tersinkronisasi di tabel tracer
        KuesionerSyncService::syncProfileResponses($biodata);

        return redirect()->back()->with('success', 'Jawaban berhasil disimpan.');
    }
}
