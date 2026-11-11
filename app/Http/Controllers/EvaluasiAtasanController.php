<?php

namespace App\Http\Controllers;

use App\Mail\KonfirmasiEvaluasiAtasanTerkirimMail;
use App\Models\Atasan;
use App\Models\EvaluasiAtasan;
use App\Models\KuesionerJawaban;
use App\Models\KuesionerOpsi;
use App\Models\KuesionerPertanyaan;
use App\Models\KuesionerSection;
use App\Models\KuesionerSectionPertanyaan;
use App\Models\Perusahaan;
use App\Services\Alumni\AdminAlumniProfileService;
use App\Services\Kuesioner\KuesionerSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * EvaluasiAtasanController
 *
 * Mengelola halaman kuesioner evaluasi pengguna lulusan (atasan langsung) UKDW
 * yang diakses secara publik melalui tautan bertoken tanpa autentikasi login.
 * Profil alumni dan formulir evaluasi atasan memakai master `perusahaan` yang
 * sama; metadata master hanya dibaca oleh atasan, sedangkan karakteristik
 * yang dilaporkan disimpan sebagai jawaban pada instrumen kuesioner atasan.
 */
class EvaluasiAtasanController extends Controller
{
    /**
     * Menampilkan halaman informasi apabila pengguna mengakses rute dasar tanpa token.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Public/EvaluasiAtasanNotFound', [
            'message' => 'Gunakan tautan evaluasi pribadi yang dikirimkan ke alamat email Anda.',
        ]);
    }

    /**
     * Menampilkan halaman formulir evaluasi atasan berdasarkan token unik.
     *
     * Mengambil data profil alumni, perusahaan terkait, kontak atasan, referensi wilayah,
     * serta butir-butir pertanyaan evaluasi aktif.
     */
    public function show(string $token): Response
    {
        $evaluasi = EvaluasiAtasan::where('token', $token)
            ->with([
                'biodata.prodi.fakultas',
                'biodata.perusahaan.kabupaten',
                'biodata.perusahaan.propinsi',
                'perusahaan.kabupaten',
                'perusahaan.propinsi',
                'atasan',
                'respons.pertanyaan',
            ])
            ->first();

        if (! $evaluasi) {
            return Inertia::render('Public/EvaluasiAtasanNotFound', [
                'status' => 'not_found',
                'title' => 'Tautan Evaluasi Belum Teridentifikasi',
                'message' => 'Tautan formulir evaluasi tidak ditemukan atau kode token tidak valid.',
            ]);
        }

        if ($evaluasi->is_submitted) {
            $biodata = $evaluasi->biodata;
            $atasan = $evaluasi->atasan;
            $perusahaan = $evaluasi->perusahaan ?? $biodata?->perusahaan;

            $alumniName = $biodata?->nama ?? $biodata?->dataAkademik?->nama ?? 'Alumni UKDW';
            $supervisorName = $atasan?->nama ?? 'Bapak/Ibu Pimpinan / Atasan';
            $companyName = $perusahaan?->nama_perusahaan ?? 'Perusahaan';

            return Inertia::render('Public/EvaluasiAtasanNotFound', [
                'status' => 'submitted',
                'title' => 'Evaluasi Kinerja Lulusan Telah Selesai',
                'message' => "Terima kasih yang sebesar-besarnya kepada {$supervisorName} di {$companyName} atas partisipasi dan evaluasi obyektif yang telah diberikan terhadap alumni kami, {$alumniName}. Respon evaluasi telah tersimpan secara resmi di sistem Tracer Study UKDW.",
            ]);
        }

        $biodata = $evaluasi->biodata;
        $atasan = $evaluasi->atasan;
        $perusahaan = $evaluasi->perusahaan ?? $biodata?->perusahaan;

        $alumniName = $biodata?->nama ?? $biodata?->dataAkademik?->nama ?? 'Alumni UKDW';
        $alumniProdi = $biodata?->prodi?->nama_prodi ?? $biodata?->dataAkademik?->program_studi ?? '-';
        $alumniFakultas = $biodata?->prodi?->fakultas?->nama_fakultas ?? '-';
        $namaPerusahaan = $perusahaan?->nama_perusahaan ?? '';
        $alamatPerusahaan = $perusahaan?->alamat ?? '';
        $homepagePerusahaan = $perusahaan?->homepage ?? '';
        $telpPerusahaan = $perusahaan?->no_telp_fax ?? '';
        $bentukPerusahaan = $perusahaan?->bentuk_perusahaan ?? '';
        $skalaPerusahaan = $perusahaan?->skala ?? '';
        $jenisPerusahaan = $perusahaan?->jenis_perusahaan ?? '';
        $jenisPerusahaanLainnya = $perusahaan?->jenis_perusahaan_lainnya ?? '';
        $jenisLokasi = $perusahaan?->jenis_lokasi ?? 'Dalam Negeri';
        $kodeNegaraPerusahaan = $perusahaan?->kode_negara ?? ($jenisLokasi === 'Luar Negeri' ? '' : 'ID');
        $kodeProvinsi = $perusahaan?->kode_provinsi ?? '';
        $kodeKabupaten = $perusahaan?->kode_kabupaten ?? '';
        $kodePosPerusahaan = $perusahaan?->kode_pos ?? ($biodata?->zipcode ?? '');

        $lokasiWilayah = $jenisLokasi === 'Luar Negeri'
            ? ($kodeNegaraPerusahaan ?: 'Luar Negeri')
            : ($perusahaan?->kabupaten?->nama_kabupaten
                ? ($perusahaan?->propinsi?->nama_provinsi ? $perusahaan->kabupaten->nama_kabupaten.', '.$perusahaan->propinsi->nama_provinsi : $perusahaan->kabupaten->nama_kabupaten)
                : ($perusahaan?->propinsi?->nama_provinsi ?? ''));

        $lokasiPerusahaan = $lokasiWilayah ?: ($perusahaan?->alamat ?: '');

        $kategoriPekerjaan = $biodata?->kategori_pekerjaan
            ?: ($biodata?->posisi_wiraswasta ? 'Wiraswasta' : ($biodata?->posisi_jabatan ? 'Pekerja / Karyawan' : ''));

        $posisiJabatan = $biodata?->posisi_jabatan ?: ($biodata?->posisi_wiraswasta ?? '');
        $bidangPekerjaan = $biodata?->bidang_pekerjaan ?? '';
        $pendidikanTingkat = $biodata?->pendidikan_tingkat ?? '';
        $perguruanTinggi = $biodata?->perguruan_tinggi ?? '';
        $pendidikanProdi = $biodata?->pendidikan_prodi ?? '';
        $jenisPerusahaanLabel = ($jenisPerusahaan === 'Lainnya' && ! empty($jenisPerusahaanLainnya))
            ? "Lainnya ({$jenisPerusahaanLainnya})"
            : $jenisPerusahaan;

        // Ambil daftar butir pertanyaan instrumen evaluasi atasan yang aktif dari master table
        $atasanSectionIds = KuesionerSection::where('target', 'atasan')->pluck('id')->all();
        $pertanyaanList = KuesionerSectionPertanyaan::with(['pertanyaan.opsi', 'section'])
            ->whereIn('section_id', $atasanSectionIds)
            ->where('is_active', true)
            ->whereHas('pertanyaan', fn ($q) => $q->where('is_active', true))
            ->orderBy('order', 'asc')
            ->get()
            ->map(function (KuesionerSectionPertanyaan $placement): ?array {
                $question = $placement->pertanyaan;
                if (! $question) {
                    return null;
                }

                $step = match ((int) $placement->section?->order) {
                    1 => 'instansi',
                    2 => 'karakteristik',
                    3 => 'penilaian',
                    default => 'penilaian',
                };

                return [
                    'id' => $question->id,
                    'kode' => $question->kode,
                    'full_kode' => $question->kode_pertanyaan,
                    'label' => $question->label_pertanyaan,
                    'aspek' => $question->aspek,
                    'deskripsi' => $question->keterangan ?? '',
                    'tipe' => $question->tipe,
                    'langkah' => $step,
                    'order' => (int) $placement->order,
                    'is_active' => (bool) $question->is_active,
                    'is_company_field' => (bool) $question->is_company_field,
                    'pilihan_jawaban' => $question->opsi->pluck('label')->values()->all(),
                ];
            })
            ->filter()
            ->values();

        // Map jawaban yang sudah ada jika formulir sudah pernah diisi
        $existingResponses = [];
        $existingCatatan = '';
        foreach ($evaluasi->respons as $resp) {
            if ($resp->pertanyaan) {
                $existingResponses[$resp->pertanyaan->kode] = $resp->nilai;
            }
            if (! empty($resp->catatan) && empty($existingCatatan)) {
                $existingCatatan = $resp->catatan;
            }
        }

        $sections = KuesionerSection::where('target', 'atasan')
            ->orderBy('order')
            ->get()
            ->map(function ($sec) {
                $key = match ((int) $sec->order) {
                    1 => 'instansi',
                    2 => 'karakteristik',
                    3 => 'penilaian',
                    default => strtolower(str_replace(['SEC_EVA_', 'SEC_ATASAN_', 'SEC_'], '', $sec->kode_section)),
                };

                return [
                    'key' => $key,
                    'title' => $sec->judul,
                    'description' => $sec->deskripsi ?? '',
                    'source' => $key === 'instansi'
                        ? 'perusahaan, atasan, evaluasi_atasan'
                        : ($key === 'karakteristik' ? 'perusahaan, kuesioner_jawaban' : 'kuesioner_jawaban'),
                    'order' => (int) $sec->order,
                ];
            })->all();

        $refOptions = AdminAlumniProfileService::getRefOptions();

        return Inertia::render('Public/EvaluasiAtasanForm', [
            'token' => $token,
            'evaluasi' => $evaluasi,
            'pertanyaanList' => $pertanyaanList,
            'sections' => ! empty($sections) ? $sections : EvaluasiAtasan::STEPPER_SECTIONS,
            'existingResponses' => $existingResponses,
            'existingCatatan' => $existingCatatan,
            'alumniInfo' => [
                'nama' => $alumniName,
                'prodi' => $alumniProdi,
                'fakultas' => $alumniFakultas,
                'nim' => $biodata?->nim ?? '-',
                'foto' => $biodata?->foto,
                'posisi_jabatan' => $posisiJabatan,
                'bidang_pekerjaan' => $bidangPekerjaan,
                'kategori_pekerjaan' => $kategoriPekerjaan,
                'pendidikan_tingkat' => $pendidikanTingkat,
                'perguruan_tinggi' => $perguruanTinggi,
                'pendidikan_prodi' => $pendidikanProdi,
                'perusahaan' => $namaPerusahaan,
                'jenis_perusahaan' => $jenisPerusahaanLabel,
                'bentuk_perusahaan' => $bentukPerusahaan,
                'skala_perusahaan' => $skalaPerusahaan,
                'jenis_lokasi' => $jenisLokasi,
                'lokasi_perusahaan' => $lokasiPerusahaan,
                'domisili_head_office' => $lokasiWilayah,
                'kode_pos_perusahaan' => $kodePosPerusahaan,
                'alamat_perusahaan' => $alamatPerusahaan,
                'no_telp_fax' => $telpPerusahaan,
                'homepage' => $homepagePerusahaan,
            ],
            'initialCompany' => [
                'id' => $perusahaan?->id,
                'nama' => $namaPerusahaan,
                'jenis_lokasi' => $jenisLokasi,
                'kode_negara' => $kodeNegaraPerusahaan,
                'kode_provinsi' => $kodeProvinsi,
                'kode_kabupaten' => $kodeKabupaten,
                'kode_pos' => $kodePosPerusahaan,
                'alamat' => $alamatPerusahaan,
                'jenis_perusahaan' => $jenisPerusahaan,
                'jenis_perusahaan_lainnya' => $jenisPerusahaanLainnya,
                'no_telp_fax' => $telpPerusahaan,
                'homepage' => $homepagePerusahaan,
                'bentuk' => $bentukPerusahaan,
                'skala' => $skalaPerusahaan,
                'status_verifikasi' => $perusahaan?->status_verifikasi ?? '',
                'nama_atasan' => $atasan?->nama ?? '',
                'email_atasan' => $atasan?->email ?? '',
                'telepon_atasan' => $atasan?->telepon ?? '',
            ],
            'companyOptions' => [
                'jenis_perusahaan' => collect($refOptions['F11'] ?? [])
                    ->map(static fn (array $option): string => (string) ($option['option_text'] ?? ''))
                    ->filter()
                    ->values()
                    ->all(),
                'bentuk_perusahaan' => collect($refOptions['BIO_INST_BENTUK'] ?? [])
                    ->map(static fn (array $option): string => (string) ($option['option_text'] ?? ''))
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'isSubmitted' => (bool) $evaluasi->is_submitted,
        ]);
    }

    /**
     * Menyimpan Respon Hasil Evaluasi Kinerja Alumni dari Atasan
     */
    public function store(string $token, Request $request)
    {
        $evaluasi = EvaluasiAtasan::where('token', $token)
            ->with(['perusahaan', 'biodata.perusahaan', 'atasan'])
            ->firstOrFail();

        $atasanSectionIds = KuesionerSection::where('target', 'atasan')->pluck('id')->all();
        $activeQuestions = KuesionerPertanyaan::where('is_active', true)
            ->whereHas('sections', function ($query) use ($atasanSectionIds) {
                $query->whereIn('kuesioner_section.id', $atasanSectionIds)
                    ->where('kuesioner_section_pertanyaan.is_active', true);
            })
            ->get();
        $activeQuestionMap = $activeQuestions->flatMap(static function (KuesionerPertanyaan $question): array {
            return [
                $question->kode_pertanyaan => $question,
                $question->kode => $question,
            ];
        });
        $activeCompanyQuestions = $activeQuestions
            ->filter->is_company_field
            ->filter(fn (KuesionerPertanyaan $question): bool => in_array($question->kode, [
                'jumlah_pegawai',
                'jumlah_alumni_ukdw',
                'standar_gaji_pertama',
            ], true));

        $companyFieldRules = [];
        foreach ($activeCompanyQuestions as $question) {
            $companyFieldRules[$question->kode] = 'required|string|max:150';
        }

        $validated = $request->validate(array_merge([
            // Bagian I: Informasi Perusahaan (dibaca dari master Perusahaan)
            'nama_perusahaan' => 'required|string|max:255',
            'alamat_lengkap' => 'required|string',
            'jenis_lokasi' => 'nullable|string|in:Dalam Negeri,Luar Negeri',
            'kode_negara' => 'nullable|string|size:2|exists:ref_negara,kode_negara',
            'kode_provinsi' => 'nullable|exists:propinsi,kode_provinsi',
            'kode_kabupaten' => 'nullable|exists:kabupaten,kode_kabupaten',
            'kode_pos' => 'required|string|max:15',
            'jenis_perusahaan' => 'required|string|max:100',
            'jenis_perusahaan_lainnya' => 'nullable|string|max:150',
            'no_telp_fax' => 'nullable|string|max:100',
            'homepage' => ['nullable', 'string', 'max:150'],
            'bentuk_perusahaan' => 'nullable|string|max:100',
            'skala_perusahaan' => ['nullable', 'string', 'max:100'],
            'jumlah_pegawai' => 'nullable|string|max:100',

            // Bagian Karier & Studi Lanjut Alumni (Tersimpan ke tabel Biodata)
            'bidang_pekerjaan' => 'nullable|string|max:150',
            'posisi_jabatan' => 'nullable|string|max:150',
            'is_melanjutkan_studi' => 'nullable|boolean',
            'pendidikan_tingkat' => 'nullable|string|max:100',
            'perguruan_tinggi' => 'nullable|string|max:255',
            'pendidikan_prodi' => 'nullable|string|max:255',

            // Karakteristik yang dijawab atasan (disimpan sebagai jawaban kuesioner)
            'jumlah_alumni_ukdw' => 'nullable|string|max:100',
            'standar_gaji_pertama' => 'nullable|string|max:100',

            // Data Atasan/Pimpinan (Tersimpan ke tabel Atasan)
            'nama_atasan' => 'nullable|string|max:255',
            'email_atasan' => 'nullable|email|max:255',
            'telepon_atasan' => 'nullable|string|max:100',

            // Bagian II: Jawaban Kuesioner Evaluasi (Termasuk Kesiapan Kerja & 12 Aspek Kinerja)
            'jawaban' => 'required|array|min:1',
            'jawaban.*' => 'required|string|max:100',
            'catatan_lainnya' => 'nullable|string|max:2000',
        ], $companyFieldRules));

        $evaluationQuestions = $activeQuestions->reject->is_company_field->keyBy('kode');
        $submittedCodes = collect(array_keys($validated['jawaban']));
        if ($submittedCodes->diff($evaluationQuestions->keys())->isNotEmpty()
            || $evaluationQuestions->keys()->diff($submittedCodes)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'jawaban' => 'Jawaban harus sesuai dengan seluruh pertanyaan evaluasi yang aktif.',
            ]);
        }

        DB::transaction(function () use ($token, $validated, $activeQuestionMap, $atasanSectionIds, $request): void {
            $lockedEvaluation = EvaluasiAtasan::where('token', $token)->lockForUpdate()->firstOrFail();
            if ($lockedEvaluation->is_submitted) {
                throw ValidationException::withMessages([
                    'jawaban' => 'Evaluasi ini sudah pernah dikirim dan tidak dapat diubah kembali.',
                ]);
            }

            $lockedEvaluation->load(['perusahaan', 'biodata.perusahaan', 'atasan']);

            $perusahaan = $lockedEvaluation->perusahaan ?? $lockedEvaluation->biodata?->perusahaan;
            $jenisLokasi = $validated['jenis_lokasi'] ?? ($perusahaan?->jenis_lokasi ?? 'Dalam Negeri');
            $kodeNegara = $jenisLokasi === 'Luar Negeri'
                ? (! empty($validated['kode_negara']) && strtoupper($validated['kode_negara']) !== 'ID' ? strtoupper($validated['kode_negara']) : $perusahaan?->kode_negara)
                : 'ID';

            if ($jenisLokasi === 'Luar Negeri' && empty($kodeNegara)) {
                throw ValidationException::withMessages([
                    'kode_negara' => 'Kode negara ISO wajib dipilih untuk perusahaan luar negeri.',
                ]);
            }

            $perusahaanData = [
                'alamat' => filled($validated['alamat_lengkap'] ?? null) ? trim($validated['alamat_lengkap']) : null,
                'no_telp_fax' => filled($validated['no_telp_fax'] ?? null) ? trim($validated['no_telp_fax']) : null,
                'homepage' => filled($validated['homepage'] ?? null) ? trim($validated['homepage']) : null,
                'bentuk_perusahaan' => filled($validated['bentuk_perusahaan'] ?? null) ? trim($validated['bentuk_perusahaan']) : null,
                'jenis_lokasi' => $jenisLokasi,
                'kode_negara' => $kodeNegara,
            ];

            if ($request->has('kode_provinsi') || $jenisLokasi === 'Luar Negeri') {
                $perusahaanData['kode_provinsi'] = $jenisLokasi === 'Luar Negeri' ? null : ($validated['kode_provinsi'] ?? null);
            }
            if ($request->has('kode_kabupaten') || $jenisLokasi === 'Luar Negeri') {
                $perusahaanData['kode_kabupaten'] = $jenisLokasi === 'Luar Negeri' ? null : ($validated['kode_kabupaten'] ?? null);
            }
            if ($request->has('kode_pos')) {
                $perusahaanData['kode_pos'] = $validated['kode_pos'] ?? null;
            }
            if ($request->has('jenis_perusahaan')) {
                $perusahaanData['jenis_perusahaan'] = filled($validated['jenis_perusahaan'] ?? null)
                    ? trim($validated['jenis_perusahaan'])
                    : null;
                $perusahaanData['jenis_perusahaan_lainnya'] = ($perusahaanData['jenis_perusahaan'] ?? null) === 'Lainnya'
                    ? ($validated['jenis_perusahaan_lainnya'] ?? null)
                    : null;
            }
            if (! empty($validated['skala_perusahaan'])) {
                $perusahaanData['skala'] = $validated['skala_perusahaan'];
            }

            if (! $perusahaan) {
                $perusahaan = Perusahaan::create([
                    ...$perusahaanData,
                    'nama_perusahaan' => trim($validated['nama_perusahaan']),
                    'skala' => $validated['skala_perusahaan'] ?? 'Nasional',
                    'status_verifikasi' => 'Menunggu Verifikasi',
                    'created_by_user_id' => $lockedEvaluation->biodata?->user_id,
                    'created_by_prodi_id' => $lockedEvaluation->biodata?->prodi_id,
                ]);
            } else {
                // Master perusahaan dikelola admin. Respon atasan tidak pernah
                // menimpa metadata, baik statusnya pending maupun terverifikasi.
            }

            if ($lockedEvaluation->biodata) {
                $biodataUpdates = [
                    'perusahaan_id' => $perusahaan->id,
                ];

                if ($request->has('bidang_pekerjaan')) {
                    $biodataUpdates['bidang_pekerjaan'] = ! empty($validated['bidang_pekerjaan']) ? trim($validated['bidang_pekerjaan']) : null;
                }
                if ($request->has('posisi_jabatan')) {
                    $biodataUpdates['posisi_jabatan'] = ! empty($validated['posisi_jabatan']) ? trim($validated['posisi_jabatan']) : null;
                }
                // Sinkronisasi data studi lanjut jika dikirim dari form atasan
                if ($request->has('is_melanjutkan_studi') || $request->has('pendidikan_tingkat') || $request->has('perguruan_tinggi') || $request->has('pendidikan_prodi')) {
                    $isStudi = $request->boolean('is_melanjutkan_studi', ! empty($validated['pendidikan_tingkat']) || ! empty($validated['perguruan_tinggi']) || ! empty($validated['pendidikan_prodi']));
                    $biodataUpdates['pendidikan_tingkat'] = $isStudi && ! empty($validated['pendidikan_tingkat']) ? trim($validated['pendidikan_tingkat']) : null;
                    $biodataUpdates['perguruan_tinggi'] = $isStudi && ! empty($validated['perguruan_tinggi']) ? trim($validated['perguruan_tinggi']) : null;
                    $biodataUpdates['pendidikan_prodi'] = $isStudi && ! empty($validated['pendidikan_prodi']) ? trim($validated['pendidikan_prodi']) : null;
                }

                $lockedEvaluation->biodata->update($biodataUpdates);
            }

            $lockedEvaluation->perusahaan_id = $perusahaan->id;

            $questionSectionMap = KuesionerSectionPertanyaan::query()
                ->with('section:id,order')
                ->whereIn('section_id', $atasanSectionIds)
                ->where('is_active', true)
                ->get()
                ->sortBy(static fn (KuesionerSectionPertanyaan $placement): array => [
                    $placement->section?->order ?? PHP_INT_MAX,
                    $placement->order,
                ])
                ->groupBy('pertanyaan_id')
                ->map(static fn ($placements): ?int => $placements->first()?->section_id);
            $responsesToStore = $validated['jawaban'];
            foreach (['jumlah_pegawai', 'jumlah_alumni_ukdw', 'standar_gaji_pertama'] as $code) {
                if ($activeQuestionMap->has($code)) {
                    $responsesToStore[$code] = $validated[$code] ?? null;
                }
            }

            foreach ($responsesToStore as $code => $value) {
                $pertanyaan = $activeQuestionMap->get($code)
                    ?? KuesionerPertanyaan::whereIn('kode_pertanyaan', ['EVA_'.$code, $code])->first();

                if ($pertanyaan) {
                    $opsi = KuesionerOpsi::where('pertanyaan_id', $pertanyaan->id)
                        ->where('label', $value)
                        ->first();

                    KuesionerJawaban::updateOrCreate(
                        [
                            'responden_type' => 'atasan',
                            'evaluasi_atasan_id' => $lockedEvaluation->id,
                            'pertanyaan_id' => $pertanyaan->id,
                        ],
                        [
                            'section_id' => $questionSectionMap->get($pertanyaan->id) ?: ($atasanSectionIds[0] ?? 1),
                            'kode_pertanyaan' => $pertanyaan->kode_pertanyaan,
                            'opsi_id' => $opsi?->id,
                            'kode_opsi' => $opsi?->kode_opsi,
                            'jawaban_teks' => $value,
                            'jawaban_json' => ! empty($validated['catatan_lainnya']) ? ['catatan' => $validated['catatan_lainnya']] : null,
                        ]
                    );
                }
            }

            $atasanData = array_filter([
                'nama' => $validated['nama_atasan'] ?? null,
                'email' => $validated['email_atasan'] ?? null,
                'telepon' => $validated['telepon_atasan'] ?? null,
            ], static fn (?string $value): bool => $value !== null && trim($value) !== '');

            if ($atasanData !== []) {
                if ($perusahaan?->id) {
                    $atasanData['perusahaan_id'] = $perusahaan->id;
                }

                $atasan = $lockedEvaluation->atasan;
                if ($atasan) {
                    $atasan->update($atasanData);
                } else {
                    $atasan = Atasan::create($atasanData);
                    $lockedEvaluation->atasan_id = $atasan->id;
                }

                if ($lockedEvaluation->biodata && $lockedEvaluation->biodata->atasan_id !== $atasan->id) {
                    $lockedEvaluation->biodata->update(['atasan_id' => $atasan->id]);
                }
            }

            if ($lockedEvaluation->biodata) {
                KuesionerSyncService::syncProfileResponses($lockedEvaluation->biodata->fresh(['perusahaan', 'atasan', 'dataAkademik']));
            }

            $lockedEvaluation->update([
                'is_submitted' => true,
                'submitted_at' => now(),
            ]);

            // Kirim email tanda terima / konfirmasi jawaban langsung ke alamat email atasan
            $targetEmail = $validated['email_atasan'] ?? ($lockedEvaluation->atasan?->email ?? null);
            if (! empty($targetEmail) && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $biodataFresh = $lockedEvaluation->biodata?->fresh(['perusahaan', 'atasan', 'dataAkademik', 'prodi']);
                    $evaluasiFresh = $lockedEvaluation->fresh(['perusahaan', 'atasan']);
                    if ($biodataFresh && $evaluasiFresh) {
                        Mail::to($targetEmail)->send(new KonfirmasiEvaluasiAtasanTerkirimMail($biodataFresh, $evaluasiFresh));
                        Log::info("Email bukti penerimaan evaluasi terkirim ke atasan: {$targetEmail} | Token: {$token}");
                    }
                } catch (\Throwable $e) {
                    Log::error("Gagal mengirim email tanda terima evaluasi ke atasan ({$targetEmail}): ".$e->getMessage());
                }
            }
        });

        return redirect()->back()->with('success', 'Terima kasih! Respon evaluasi kinerja lulusan UKDW berhasil disimpan.');
    }
}
