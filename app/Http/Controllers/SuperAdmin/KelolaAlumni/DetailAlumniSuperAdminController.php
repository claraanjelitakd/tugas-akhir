<?php

namespace App\Http\Controllers\SuperAdmin\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\Kabupaten;
use App\Models\LinkedinSyncResult;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Services\Alumni\AdminAlumniProfileService;
use App\Services\Alumni\LinkedinTraceHelper;
use App\Services\Export\AlumniTracerExcelExporter;
use App\Services\Kuesioner\KelengkapanTracerService;
use App\Services\Kuesioner\KuesionerSyncService;
use App\Services\Kuesioner\UnifiedDetailAlumniService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DetailAlumniSuperAdminController
 *
 * Menampilkan detail profil dan respon kuesioner terpadu tracer study bagi Super Admin.
 */
class DetailAlumniSuperAdminController extends Controller
{
    /**
     * Tampilkan Halaman Detail Profil & Kuesioner Alumni
     */
    public function show(int|string $id): Response
    {
        $alumni = Biodata::with([
            'dataAkademik.yudisium',
            'yudisium',
            'perusahaan.propinsi',
            'perusahaan.kabupaten',
            'atasan',
            'user',
            'prodi',
            'latestLinkedinSyncResult.reviewer',
        ])->findOrFail($id);

        KuesionerSyncService::syncProfileResponses($alumni);

        $evaluasi = KelengkapanTracerService::evaluasiKelengkapanTotal($alumni);

        // Ambil data section dan respon terpadu
        $sectionsWithAnswers = UnifiedDetailAlumniService::getUnivSectionsWithAnswers($alumni);
        $prodiData = UnifiedDetailAlumniService::getProdiSectionsWithAnswers($alumni);
        $prodiSectionsWithAnswers = $prodiData['sections'];
        $prodiEvaluasi = $prodiData['evaluasi'];

        // Susun Data Form Profil Lengkap
        $formData = AdminAlumniProfileService::buildFormData($alumni);

        $propinsis = Propinsi::orderBy('nama_provinsi', 'asc')->get();
        $kabupatens = Kabupaten::orderBy('nama_kabupaten', 'asc')->get();
        $negaras = RefNegara::orderBy('nama_negara', 'asc')->get();
        $refOptions = AdminAlumniProfileService::getRefOptions();
        $perusahaans = Perusahaan::select('id', 'nama_perusahaan', 'jenis_lokasi', 'kode_negara', 'kode_provinsi', 'kode_kabupaten', 'alamat', 'kode_pos', 'homepage', 'no_telp_fax', 'skala', 'bentuk_perusahaan', 'jenis_perusahaan', 'jenis_perusahaan_lainnya', 'status_verifikasi')->get();

        $latestSync = $alumni->latestLinkedinSyncResult;
        $linkedinTraceMapping = $this->buildLinkedinTraceMapping($alumni, $latestSync);
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

        return Inertia::render('SuperAdmin/Alumni/Show', [
            'biodata' => $alumni,
            'alumni' => $alumni,
            'evaluasi' => $evaluasi,
            'evaluasiAtasan' => $evaluasiAtasanData,
            'pertanyaanEvaluasiAtasan' => $pertanyaanEvaluasiAtasan,
            'sections' => $sectionsWithAnswers,
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
        ]);
    }

    /**
     * Membangun Baris Pemetaan Audit Trace Hasil Scraping LinkedIn ke Basis Data
     */
    public function buildLinkedinTraceMapping(Biodata $alumni, ?LinkedinSyncResult $syncResult): array
    {
        return LinkedinTraceHelper::buildTraceMapping($alumni, $syncResult);
    }

    /**
     * Memperbarui Data Profil Alumni oleh Super Admin
     */
    public function updateProfile(Request $request, int|string $id): RedirectResponse
    {
        $biodata = Biodata::findOrFail($id);
        AdminAlumniProfileService::updateProfile($biodata, $request->all());

        return redirect()->back()->with('success', 'Data profil mahasiswa berhasil diperbarui oleh Super Admin.');
    }

    /**
     * Download Excel (.xls) Semua Butir Pertanyaan & Jawaban per Alumni
     */
    public function exportExcel(int|string $id): StreamedResponse
    {
        return AlumniTracerExcelExporter::download($id);
    }
}
