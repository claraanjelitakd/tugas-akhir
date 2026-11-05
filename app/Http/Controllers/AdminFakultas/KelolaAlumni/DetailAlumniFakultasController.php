<?php

namespace App\Http\Controllers\AdminFakultas\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\Kabupaten;
use App\Models\LinkedinSyncResult;
use App\Models\Perusahaan;
use App\Models\Prodi;
use App\Models\Propinsi;
use App\Models\RefFakultas;
use App\Models\RefNegara;
use App\Services\Alumni\AdminAlumniProfileService;
use App\Services\Alumni\LinkedinTraceHelper;
use App\Services\Export\AlumniTracerExcelExporter;
use App\Services\Kuesioner\KelengkapanTracerService;
use App\Services\Kuesioner\KuesionerSyncService;
use App\Services\Kuesioner\UnifiedDetailAlumniService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DetailAlumniFakultasController
 *
 * Fungsi:
 * Menampilkan detail profil dan audit kelengkapan kuesioner mahasiswa/alumni
 * dalam lingkup fakultas yang bersangkutan, serta menyediakan mode baca-saja
 * lintas fakultas yang dibuka dari analitik.
 */
class DetailAlumniFakultasController extends Controller
{
    /**
     * Tampilkan detail alumni dalam lingkup fakultas atau mode baca-saja analitik.
     *
     * Route analitik sengaja memakai endpoint GET terpisah agar akses lintas fakultas
     * tidak membuka jalur update profil. Endpoint perubahan profil tetap memakai
     * query scope fakultas dan akan menolak alumni di luar fakultas admin.
     */
    public function show(Request $request, int $id): Response
    {
        $user = Auth::user()->load('fakultas');
        $fakultasId = $user->fakultas_id;
        $isReadOnly = $request->routeIs('fakultas.analytics.alumni.show');

        abort_unless($fakultasId, 403, 'Akun Admin Fakultas belum memiliki lingkup fakultas.');

        $fakultas = $user->fakultas ?: RefFakultas::findOrFail($fakultasId);

        $prodiIdsFakultas = Prodi::where('fakultas_id', $fakultasId)->pluck('id')->all();

        $alumniQuery = Biodata::with([
            'dataAkademik.yudisium',
            'yudisium',
            'perusahaan.propinsi',
            'perusahaan.kabupaten',
            'atasan',
            'user',
            'prodi',
            'latestLinkedinSyncResult.reviewer',
        ]);

        if (! $isReadOnly) {
            $alumniQuery->whereIn('prodi_id', $prodiIdsFakultas);
        }

        $alumni = $alumniQuery->findOrFail($id);

        KuesionerSyncService::syncProfileResponses($alumni);

        $evaluasi = KelengkapanTracerService::evaluasiKelengkapanTotal($alumni);

        // Ambil data section dan jawaban kuesioner terpadu
        $univSectionsWithAnswers = UnifiedDetailAlumniService::getUnivSectionsWithAnswers($alumni);
        $prodiData = UnifiedDetailAlumniService::getProdiSectionsWithAnswers($alumni);
        $prodiSectionsWithAnswers = $prodiData['sections'];
        $prodiEvaluasi = $prodiData['evaluasi'];

        // 3. Form Profil Lengkap
        $formData = AdminAlumniProfileService::buildFormData($alumni);

        $propinsis = Propinsi::orderBy('nama_provinsi', 'asc')->get();
        $kabupatens = Kabupaten::orderBy('nama_kabupaten', 'asc')->get();
        $negaras = RefNegara::orderBy('nama_negara', 'asc')->get();
        $refOptions = AdminAlumniProfileService::getRefOptions();
        $perusahaans = Perusahaan::select('id', 'nama_perusahaan', 'jenis_lokasi', 'kode_negara', 'kode_provinsi', 'kode_kabupaten', 'alamat', 'kode_pos', 'skala', 'jenis_perusahaan', 'jenis_perusahaan_lainnya', 'status_verifikasi')->get();

        $latestSync = $alumni->latestLinkedinSyncResult;
        $linkedinTraceMapping = LinkedinTraceHelper::buildTraceMapping($alumni, $latestSync);
        $linkedinHistory = LinkedinSyncResult::where('biodata_id', $alumni->id)
            ->with('reviewer')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        // Ambil Data Evaluasi Atasan (Pengguna Lulusan)
        $evaluasiAtasan = EvaluasiAtasan::where('biodata_id', $alumni->id)
            ->with(['atasan', 'perusahaan.kabupaten', 'perusahaan.propinsi', 'respons.pertanyaan'])
            ->latest('updated_at')
            ->first();

        $evaluasiAtasanData = $evaluasiAtasan?->toAdminAuditArray();

        $pertanyaanEvaluasiAtasan = UnifiedDetailAlumniService::getPertanyaanEvaluasiAtasan();

        return Inertia::render('AdminFakultas/Alumni/Show', [
            'user' => $user,
            'fakultas' => $fakultas,
            'biodata' => $alumni,
            'alumni' => $alumni,
            'evaluasi' => $evaluasi,
            'evaluasiAtasan' => $evaluasiAtasanData,
            'pertanyaanEvaluasiAtasan' => $pertanyaanEvaluasiAtasan,
            'sections' => $univSectionsWithAnswers,
            'prodiSections' => $prodiSectionsWithAnswers,
            'prodiEvaluasi' => $prodiEvaluasi,
            'formData' => $formData,
            'propinsis' => $propinsis,
            'provinces' => $propinsis,
            'kabupatens' => $kabupatens,
            'negaras' => $negaras,
            'refOptions' => $refOptions,
            'perusahaans' => $perusahaans,
            'companies' => $perusahaans,
            'linkedinSyncResult' => $latestSync,
            'linkedinTraceMapping' => $linkedinTraceMapping,
            'linkedinHistory' => $linkedinHistory,
            'readonly' => $isReadOnly,
        ]);
    }

    /**
     * Update Profil Mahasiswa oleh Admin Fakultas
     */
    public function updateProfile(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $fakultasId = $user->fakultas_id;
        $prodiIdsFakultas = Prodi::where('fakultas_id', $fakultasId)->pluck('id')->all();

        $biodata = Biodata::whereIn('prodi_id', $prodiIdsFakultas)->findOrFail($id);

        AdminAlumniProfileService::updateProfile($biodata, $request->all());

        return redirect()->back()->with('success', 'Data profil mahasiswa berhasil diperbarui oleh Admin Fakultas.');
    }

    /**
     * Download Excel (.xls) Semua Butir Pertanyaan & Jawaban per Alumni untuk Fakultas
     */
    public function exportExcel(int $id): StreamedResponse
    {
        $user = Auth::user();
        $fakultasId = $user->fakultas_id;
        $prodiIdsFakultas = Prodi::where('fakultas_id', $fakultasId)->pluck('id')->all();

        // Pastikan alumni berada dalam lingkup fakultas admin yang login
        Biodata::whereIn('prodi_id', $prodiIdsFakultas)->findOrFail($id);

        return AlumniTracerExcelExporter::download($id);
    }
}
