<?php

namespace App\Http\Controllers\AdminBiroTiga\KelolaAlumni;

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
 * DetailAlumniController (Biro 3)
 *
 * Fungsi:
 * Menampilkan profil rinci alumni, rekam jejak akademik, serta audit jawaban kuesioner tracer study
 * (Universitas dan Program Studi) secara komprehensif, dilengkapi fitur sinkronisasi LinkedIn.
 */
class DetailAlumniController extends Controller
{
    /**
     * Tampilkan Detail Profil & Kuesioner Alumni
     *
     * @param  int|string  $id
     */
    public function tampilkanDetailAlumni($id): Response
    {
        $alumni = Biodata::with([
            'dataAkademik.yudisium',
            'yudisium',
            'perusahaan.propinsi',
            'perusahaan.kabupaten',
            'atasan',
            'user',
            'prodi',
        ])->findOrFail($id);

        // Pastikan respon profil tersinkron ke tabel responses
        KuesionerSyncService::syncProfileResponses($alumni);

        // Ambil evaluasi kelengkapan terpadu
        $evaluasi = KelengkapanTracerService::evaluasiKelengkapanTotal($alumni);

        // Ambil data section dan jawaban kuesioner terpadu
        $sectionsWithAnswers = UnifiedDetailAlumniService::getUnivSectionsWithAnswers($alumni);
        $prodiData = UnifiedDetailAlumniService::getProdiSectionsWithAnswers($alumni);
        $prodiSectionsWithAnswers = $prodiData['sections'];
        $prodiEvaluasi = $prodiData['evaluasi'];

        // Susun Data Form Profil Lengkap menggunakan AdminAlumniProfileService
        $formData = AdminAlumniProfileService::buildFormData($alumni);

        $propinsis = Propinsi::orderBy('nama_provinsi', 'asc')->get();
        $kabupatens = Kabupaten::orderBy('nama_kabupaten', 'asc')->get();
        $negaras = RefNegara::orderBy('nama_negara', 'asc')->get();
        $refOptions = AdminAlumniProfileService::getRefOptions();
        $perusahaans = Perusahaan::select('id', 'nama_perusahaan', 'jenis_lokasi', 'kode_negara', 'kode_provinsi', 'kode_kabupaten', 'alamat', 'kode_pos', 'homepage', 'no_telp_fax', 'skala', 'bentuk_perusahaan', 'jenis_perusahaan', 'jenis_perusahaan_lainnya', 'status_verifikasi')->get();

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

        return Inertia::render('AdminBiroTiga/AlumniShow', [
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
     * Update Data Profil Alumni oleh Admin Biro 3 (Read-Only Guard)
     */
    public function updateProfile(Request $request, $id): RedirectResponse
    {
        return redirect()->back()->with('warning', 'Akses ditolak: Admin Biro 3 hanya memiliki hak akses baca (analisis dan komunikasi), tidak memiliki wewenang untuk mengubah data profil alumni.');
    }

    /**
     * Ekspor Data Jawaban Kuesioner ke Excel (.xls) Terformat
     *
     * @param  int|string  $id
     */
    public function exportExcel($id): StreamedResponse
    {
        return AlumniTracerExcelExporter::download($id);
    }
}
