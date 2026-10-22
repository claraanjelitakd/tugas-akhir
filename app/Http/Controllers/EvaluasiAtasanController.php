<?php

namespace App\Http\Controllers;

use App\Models\Atasan;
use App\Models\EvaluasiAtasan;
use App\Models\PertanyaanEvaluasiAtasan;
use App\Models\ResponEvaluasiAtasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * EvaluasiAtasanController
 *
 * Fungsi: Mengelola halaman kuesioner evaluasi atasan / pengguna lulusan UKDW yang diakses secara publik via token tanpa perlu login.
 */
class EvaluasiAtasanController extends Controller
{
    /**
     * Mengarahkan akses rute publik /evaluasi-atasan ke token evaluasi yang tersedia
     */
    public function index(Request $request)
    {
        return Inertia::render('Public/EvaluasiAtasanNotFound', [
            'message' => 'Gunakan tautan evaluasi pribadi yang dikirimkan ke alamat email Anda.',
        ]);
    }

    /**
     * Menampilkan Halaman Formulir Evaluasi Atasan (Public Access via Token)
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

        if (! $evaluasi || $evaluasi->is_submitted) {
            return Inertia::render('Public/EvaluasiAtasanNotFound', [
                'message' => 'Tautan formulir evaluasi tidak ditemukan atau sudah tidak berlaku.',
            ]);
        }

        $biodata = $evaluasi->biodata;
        $atasan = $evaluasi->atasan;
        $perusahaan = $evaluasi->perusahaan ?? $biodata?->perusahaan;

        $alumniName = $biodata?->nama ?? $biodata?->dataAkademik?->nama ?? 'Alumni UKDW';
        $alumniProdi = $biodata?->prodi?->nama_prodi ?? $biodata?->dataAkademik?->program_studi ?? '-';
        $alumniFakultas = $biodata?->prodi?->fakultas?->nama_fakultas ?? '-';
        $namaPerusahaan = $perusahaan?->nama_perusahaan ?? '-';
        $alamatPerusahaan = $perusahaan?->alamat ?? '-';
        $homepagePerusahaan = $perusahaan?->homepage ?? '';
        $telpPerusahaan = $perusahaan?->no_telp_fax ?? '';
        $bentukPerusahaan = $perusahaan?->bentuk_perusahaan ?? '';
        $skalaPerusahaan = $perusahaan?->skala ?? '';
        $jumlahPegawai = $perusahaan?->jumlah_pegawai ?? '';

        $lokasiPerusahaan = $perusahaan?->kabupaten?->nama_kabupaten
            ? ($perusahaan?->propinsi?->nama_propinsi ? $perusahaan->kabupaten->nama_kabupaten.', '.$perusahaan->propinsi->nama_propinsi : $perusahaan->kabupaten->nama_kabupaten)
            : ($perusahaan?->propinsi?->nama_propinsi ?? ($perusahaan?->alamat ?: '-'));

        $kategoriPekerjaan = $biodata?->kategori_pekerjaan
            ?: ($biodata?->posisi_wiraswasta ? 'Wiraswasta' : 'Bekerja / Karyawan');

        $posisiJabatan = $biodata?->posisi_jabatan ?: '-';

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
                'kategori_pekerjaan' => $kategoriPekerjaan,
                'perusahaan' => $namaPerusahaan,
                'lokasi_perusahaan' => $lokasiPerusahaan,
            ],
            'initialCompany' => [
                'id' => $perusahaan?->id,
                'nama' => $namaPerusahaan,
                'alamat' => $alamatPerusahaan,
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
            // Bagian I: Informasi Perusahaan (Tersimpan ke tabel Perusahaan)
            'nama_perusahaan' => 'required|string|max:255',
            'alamat_lengkap' => 'required|string',
            'no_telp_fax' => 'nullable|string|max:100',
            'homepage' => 'nullable|string|max:255',
            'bentuk_perusahaan' => 'nullable|string|max:100',
            'skala_perusahaan' => 'nullable|string|max:100',
            'jumlah_pegawai' => 'nullable|string|max:100',

            // Karakteristik Keberadaan Alumni UKDW (Tersimpan ke evaluasi_atasan)
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

        DB::transaction(function () use ($token, $validated, $activeQuestions): void {
            $lockedEvaluation = EvaluasiAtasan::where('token', $token)->lockForUpdate()->firstOrFail();
            if ($lockedEvaluation->is_submitted) {
                throw ValidationException::withMessages([
                    'jawaban' => 'Evaluasi ini sudah pernah dikirim dan tidak dapat diubah kembali.',
                ]);
            }

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

            $lockedEvaluation->update([
                'jumlah_alumni_ukdw' => $validated['jumlah_alumni_ukdw'] ?? null,
                'standar_gaji_pertama' => $validated['standar_gaji_pertama'] ?? null,
                'is_submitted' => true,
                'submitted_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Terima kasih! Respon evaluasi kinerja lulusan UKDW berhasil disimpan.');
    }
}
