<?php

namespace App\Http\Controllers;

use App\Mail\KonfirmasiEvaluasiAtasanTerkirimMail;
use App\Models\Atasan;
use App\Models\EvaluasiAtasan;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\Perusahaan;
use App\Models\ResponEvaluasiAtasan;
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
 * Mendukung sinkronisasi dua arah atribut perusahaan antara profil alumni dan
 * formulir evaluasi atasan pada tabel master `perusahaan`.
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
        $jumlahPegawai = $perusahaan?->jumlah_pegawai ?? '';
        $jenisPerusahaan = $perusahaan?->jenis_perusahaan ?? '';
        $jenisPerusahaanLainnya = $perusahaan?->jenis_perusahaan_lainnya ?? '';
        $jenisLokasi = $perusahaan?->jenis_lokasi ?? 'Dalam Negeri';
        $negaraPerusahaan = $perusahaan?->negara ?? ($jenisLokasi === 'Luar Negeri' ? '' : 'Indonesia');
        $propinsiId = $perusahaan?->propinsi_id ?? '';
        $kabupatenId = $perusahaan?->kabupaten_id ?? '';
        $kodePosPerusahaan = $perusahaan?->kode_pos ?? ($biodata?->zipcode ?? '');

        $lokasiWilayah = $jenisLokasi === 'Luar Negeri'
            ? ($negaraPerusahaan ?: 'Luar Negeri')
            : ($perusahaan?->kabupaten?->nama_kabupaten
                ? ($perusahaan?->propinsi?->nama_propinsi ? $perusahaan->kabupaten->nama_kabupaten.', '.$perusahaan->propinsi->nama_propinsi : $perusahaan->kabupaten->nama_kabupaten)
                : ($perusahaan?->propinsi?->nama_propinsi ?? ''));

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
        $pertanyaanList = PertanyaanEvaluasiAtasan::where('is_active', true)
            ->orderBy('order', 'asc')
            ->get();

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

        return Inertia::render('Public/EvaluasiAtasanForm', [
            'token' => $token,
            'evaluasi' => $evaluasi,
            'pertanyaanList' => $pertanyaanList,
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
                'kode_pos_perusahaan' => $kodePosPerusahaan,
                'alamat_perusahaan' => $alamatPerusahaan,
                'no_telp_fax' => $telpPerusahaan,
                'homepage' => $homepagePerusahaan,
            ],
            'initialCompany' => [
                'id' => $perusahaan?->id,
                'nama' => $namaPerusahaan,
                'jenis_lokasi' => $jenisLokasi,
                'negara' => $negaraPerusahaan,
                'propinsi_id' => $propinsiId,
                'kabupaten_id' => $kabupatenId,
                'kode_pos' => $kodePosPerusahaan,
                'alamat' => $alamatPerusahaan,
                'jenis_perusahaan' => $jenisPerusahaan,
                'jenis_perusahaan_lainnya' => $jenisPerusahaanLainnya,
                'no_telp_fax' => $telpPerusahaan,
                'homepage' => $homepagePerusahaan,
                'bentuk' => $bentukPerusahaan,
                'skala' => $skalaPerusahaan,
                'jumlah_pegawai' => $jumlahPegawai,
                'nama_atasan' => $atasan?->nama ?? '',
                'email_atasan' => $atasan?->email ?? '',
                'telepon_atasan' => $atasan?->telepon ?? '',
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

        $validated = $request->validate([
            // Bagian I: Informasi Perusahaan (Tersimpan ke tabel Perusahaan & tersinkron ke profil alumni)
            'nama_perusahaan' => 'required|string|max:255',
            'alamat_lengkap' => 'required|string',
            'jenis_lokasi' => 'nullable|string|in:Dalam Negeri,Luar Negeri',
            'negara' => 'nullable|string|max:100',
            'propinsi_id' => 'nullable|exists:propinsi,id',
            'kabupaten_id' => 'nullable|exists:kabupaten,id',
            'kode_pos' => 'nullable|string|max:15',
            'jenis_perusahaan' => 'nullable|string|max:100',
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

            // Karakteristik Keberadaan Alumni UKDW (Opsional / Backward Compatibility)
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
        ]);

        $activeQuestions = PertanyaanEvaluasiAtasan::where('is_active', true)->get()->keyBy('kode');
        $submittedCodes = collect(array_keys($validated['jawaban']));
        if ($submittedCodes->diff($activeQuestions->keys())->isNotEmpty()
            || $activeQuestions->keys()->diff($submittedCodes)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'jawaban' => 'Jawaban harus sesuai dengan seluruh pertanyaan evaluasi yang aktif.',
            ]);
        }

        DB::transaction(function () use ($token, $validated, $activeQuestions, $request): void {
            $lockedEvaluation = EvaluasiAtasan::where('token', $token)->lockForUpdate()->firstOrFail();
            if ($lockedEvaluation->is_submitted) {
                throw ValidationException::withMessages([
                    'jawaban' => 'Evaluasi ini sudah pernah dikirim dan tidak dapat diubah kembali.',
                ]);
            }

            $lockedEvaluation->load(['perusahaan', 'biodata.perusahaan', 'atasan']);

            $perusahaan = $lockedEvaluation->perusahaan ?? $lockedEvaluation->biodata?->perusahaan;
            $jenisLokasi = $validated['jenis_lokasi'] ?? ($perusahaan?->jenis_lokasi ?? 'Dalam Negeri');
            $negara = $jenisLokasi === 'Luar Negeri'
                ? (! empty($validated['negara']) && strtolower($validated['negara']) !== 'indonesia' ? $validated['negara'] : ($perusahaan?->negara ?: 'Singapura'))
                : 'Indonesia';

            $perusahaanData = [
                'nama_perusahaan' => $validated['nama_perusahaan'],
                'alamat' => $validated['alamat_lengkap'],
                'no_telp_fax' => $validated['no_telp_fax'] ?? ($perusahaan?->no_telp_fax ?? null),
                'homepage' => $validated['homepage'] ?? ($perusahaan?->homepage ?? null),
                'bentuk_perusahaan' => $validated['bentuk_perusahaan'] ?? ($perusahaan?->bentuk_perusahaan ?? null),
                'jumlah_pegawai' => $validated['jumlah_pegawai'] ?? ($perusahaan?->jumlah_pegawai ?? null),
                'jenis_lokasi' => $jenisLokasi,
                'negara' => $negara,
            ];

            if ($request->has('propinsi_id') || $jenisLokasi === 'Luar Negeri') {
                $perusahaanData['propinsi_id'] = $jenisLokasi === 'Luar Negeri' ? null : ($validated['propinsi_id'] ?? null);
            }
            if ($request->has('kabupaten_id') || $jenisLokasi === 'Luar Negeri') {
                $perusahaanData['kabupaten_id'] = $jenisLokasi === 'Luar Negeri' ? null : ($validated['kabupaten_id'] ?? null);
            }
            if ($request->has('kode_pos')) {
                $perusahaanData['kode_pos'] = $validated['kode_pos'] ?? null;
            }
            if ($request->has('jenis_perusahaan')) {
                $perusahaanData['jenis_perusahaan'] = $validated['jenis_perusahaan'] ?? null;
                $perusahaanData['jenis_perusahaan_lainnya'] = ($validated['jenis_perusahaan'] ?? null) === 'Lainnya'
                    ? ($validated['jenis_perusahaan_lainnya'] ?? null)
                    : null;
            }
            if (! empty($validated['skala_perusahaan'])) {
                $perusahaanData['skala'] = $validated['skala_perusahaan'];
            }

            if (! $perusahaan) {
                $perusahaan = Perusahaan::create([
                    ...$perusahaanData,
                    'skala' => $validated['skala_perusahaan'] ?? 'Nasional',
                    'status_verifikasi' => 'Menunggu Verifikasi',
                    'created_by_user_id' => $lockedEvaluation->biodata?->user_id,
                    'created_by_prodi_id' => $lockedEvaluation->biodata?->prodi_id,
                ]);
            } else {
                // Selalu perbarui data perusahaan dari form atasan agar tersinkronisasi dua arah dengan profil alumni
                $perusahaan->update($perusahaanData);
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
                if ($request->has('kode_pos') && ! empty($validated['kode_pos'])) {
                    $biodataUpdates['zipcode'] = trim($validated['kode_pos']);
                }
                if ($request->has('jenis_perusahaan') && ! empty($validated['jenis_perusahaan'])) {
                    $biodataUpdates['jenis_pekerjaan'] = $validated['jenis_perusahaan'];
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

            foreach ($validated['jawaban'] as $code => $value) {
                ResponEvaluasiAtasan::create([
                    'evaluasi_atasan_id' => $lockedEvaluation->id,
                    'pertanyaan_id' => $activeQuestions->get($code)->id,
                    'nilai' => $value,
                    'catatan' => $validated['catatan_lainnya'] ?? null,
                ]);
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
                'jumlah_alumni_ukdw' => $validated['jumlah_alumni_ukdw'] ?? null,
                'standar_gaji_pertama' => $validated['standar_gaji_pertama'] ?? null,
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
