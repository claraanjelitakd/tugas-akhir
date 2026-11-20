<?php

namespace App\Http\Controllers\AdminProdi\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Jobs\SendBlastReminderEmailJob;
use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use App\Models\Kabupaten;
use App\Models\LinkedinSyncResult;
use App\Models\Perusahaan;
use App\Models\Propinsi;
use App\Models\RefNegara;
use App\Models\User;
use App\Services\Alumni\AdminAlumniProfileService;
use App\Services\Alumni\AlumniDirectoryQueryService;
use App\Services\Alumni\LinkedinTraceHelper;
use App\Services\Export\AlumniProdiMultiSheetExcelExporter;
use App\Services\Export\AlumniTracerExcelExporter;
use App\Services\Kuesioner\KelengkapanTracerService;
use App\Services\Kuesioner\KuesionerSyncService;
use App\Services\Kuesioner\UnifiedDetailAlumniService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DaftarAlumniProdiController
 *
 * Menampilkan direktori mahasiswa & alumni khusus untuk Program Studi yang login,
 * serta detail profil dan audit kelengkapan kuesioner terpadu.
 */
class DaftarAlumniProdiController extends Controller
{
    /**
     * Tampilkan Direktori Mahasiswa & Alumni Khusus Prodi
     */
    public function index(Request $request, AlumniDirectoryQueryService $directoryService): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('prodi');
        $prodi = $user->prodi;
        $prodiId = $user->kode_prodi;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Program Studi.');
        }

        $data = $directoryService->getDirectoryData($request, [
            'kode_prodi' => $prodiId,
            'server_pagination' => true,
        ]);

        $data['user'] = $user;
        $data['prodi'] = $prodi;

        return Inertia::render('AdminProdi/Alumni/Index', $data);
    }

    /**
     * Tampilkan Halaman Detail Profil, Kuesioner Universitas & Kuesioner Prodi Alumni
     */
    public function show(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('prodi');
        $prodi = $user->prodi;
        $prodiId = $user->kode_prodi;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Program Studi.');
        }

        $isReadOnly = $request->routeIs('prodi.analytics.alumni.show');

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
            $alumniQuery->where('kode_prodi', $prodiId);
        }

        $alumni = $alumniQuery->findOrFail($id);

        if (! $isReadOnly) {
            KuesionerSyncService::syncProfileResponses($alumni);
        }

        $evaluasi = KelengkapanTracerService::evaluasiKelengkapanTotal($alumni);

        // Ambil data section dan jawaban kuesioner terpadu
        $univSectionsWithAnswers = UnifiedDetailAlumniService::getUnivSectionsWithAnswers($alumni);
        $prodiData = UnifiedDetailAlumniService::getProdiSectionsWithAnswers($alumni);
        $prodiSectionsWithAnswers = $prodiData['sections'];
        $prodiEvaluasi = $prodiData['evaluasi'];

        // Data Form Profil Lengkap
        $formData = AdminAlumniProfileService::buildFormData($alumni);

        $propinsis = Propinsi::orderBy('nama_provinsi', 'asc')->get();
        $kabupatens = Kabupaten::orderBy('nama_kabupaten', 'asc')->get();
        $negaras = RefNegara::orderBy('nama_negara', 'asc')->get();
        $refOptions = AdminAlumniProfileService::getRefOptions();
        $profileQuestions = KuesionerSyncService::getDynamicProfileQuestions();
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

        return Inertia::render('AdminProdi/Alumni/Show', [
            'user' => $user,
            'prodi' => $prodi,
            'biodata' => $alumni,
            'alumni' => $alumni,
            'evaluasi' => $evaluasi,
            'evaluasiAtasan' => $evaluasiAtasanData,
            'pertanyaanEvaluasiAtasan' => $pertanyaanEvaluasiAtasan,
            'sections' => $univSectionsWithAnswers,
            'univSections' => $univSectionsWithAnswers,
            'prodiSections' => $prodiSectionsWithAnswers,
            'prodiEvaluasi' => $prodiEvaluasi,
            'formData' => $formData,
            'propinsis' => $propinsis,
            'provinces' => $propinsis,
            'kabupatens' => $kabupatens,
            'negaras' => $negaras,
            'refOptions' => $refOptions,
            'profileQuestions' => $profileQuestions,
            'perusahaans' => $perusahaans,
            'companies' => $perusahaans,
            'linkedinSyncResult' => $latestSync,
            'linkedinTraceMapping' => $linkedinTraceMapping,
            'linkedinHistory' => $linkedinHistory,
            'readonly' => $isReadOnly,
        ]);
    }

    /**
     * Update Profil Mahasiswa oleh Admin Prodi
     */
    public function updateProfile(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $prodiId = $user->kode_prodi;

        $biodata = Biodata::where('kode_prodi', $prodiId)->findOrFail($id);

        // Fix: gunakan only() dengan allowlist agar mencegah mass assignment
        AdminAlumniProfileService::updateProfile($biodata, $request->only(AdminAlumniProfileService::allowedProfileFields()));

        return redirect()->back()->with('success', 'Data profil mahasiswa berhasil diperbarui oleh Admin Program Studi.');
    }

    /**
     * Download Excel (.xls) Semua Butir Pertanyaan & Jawaban per Alumni untuk Prodi
     */
    public function exportExcel(int $id): StreamedResponse
    {
        $user = Auth::user();
        $prodiId = $user->kode_prodi;

        // Pastikan alumni berada di prodi yang sama
        Biodata::where('kode_prodi', $prodiId)->findOrFail($id);

        return AlumniTracerExcelExporter::download($id);
    }

    /**
     * Unduh Berkas Excel Multi-Sheet Komprehensif Khusus Program Studi yang Login
     */
    public function exportExcelProdi(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $prodiId = $user->kode_prodi;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Program Studi.');
        }

        $tahun = $request->input('tahun');
        $semester = $request->input('semester');

        return AlumniProdiMultiSheetExcelExporter::download($prodiId, $tahun, $semester);
    }

    /**
     * Kirim Email Pengingat Massal ke Alumni Prodi yang Belum Selesai (Berdasarkan Filter Aktif via Antrean)
     */
    public function blastEmail(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $prodiId = $user->kode_prodi;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Program Studi.');
        }

        $tahun = $request->input('tahun');
        $semester = $request->input('semester');
        $search = $request->input('search');

        $query = DB::table('v_alumni_audit_rekap')
            ->where('kode_prodi', $prodiId)
            ->where('is_complete_total', 0);

        if ($tahun && $tahun !== 'all') {
            $tahunNormalized = preg_match('/(\d{4})/', (string) $tahun, $matches) ? $matches[1] : $tahun;
            $query->where(function ($q) use ($tahun, $tahunNormalized) {
                $q->where('tahun_lulus', $tahunNormalized)
                    ->orWhere('tahun_lulus', $tahun);
            });
        }

        if ($semester && $semester !== 'all') {
            $query->where('tahun_akademik_lulus', 'like', "%{$semester}%");
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        $biodataIds = $query->pluck('biodata_id')->filter()->all();

        if (empty($biodataIds)) {
            return back()->with('info', 'Tidak ditemukan alumni program studi yang belum menyelesaikan kuesioner pada filter aktif terpilih.');
        }

        SendBlastReminderEmailJob::dispatch($biodataIds, Auth::id(), [
            'tahun' => $tahun ?: 'Semua',
            'semester' => $semester ?: 'Semua',
            'kode_prodi' => $prodiId,
            'search' => $search ?: null,
        ]);

        return back()->with('success', 'Email pengingat tracer study untuk '.count($biodataIds).' alumni program studi berhasil dijadwalkan ke dalam antrean sistem.');
    }

    /**
     * Kirim Email Pengingat Kuesioner ke Satu Alumni Tertentu (Flash Notifikasi)
     */
    public function sendReminderEmail(Request $request, int|string $id): RedirectResponse
    {
        $user = Auth::user();
        $prodiId = $user->kode_prodi;

        if (! $prodiId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Program Studi.');
        }

        $alumni = Biodata::where('kode_prodi', $prodiId)
            ->with(['user', 'dataAkademik'])
            ->findOrFail($id);

        $targetEmail = $alumni->email_pribadi ?: $alumni->user?->email;

        if (! $targetEmail || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', "Gagal: Alumni {$alumni->nama} ({$alumni->nim}) belum memiliki alamat email yang valid di sistem.");
        }

        try {
            Mail::to($targetEmail)->queue(new PengingatKuesionerAlumniMail($alumni));
        } catch (\Throwable $e) {
            // Tangani kegagalan SMTP jika offline/sandbox
        }

        return back()->with('success', "Email pengingat kuesioner berhasil dikirim ke {$alumni->nama} ({$targetEmail}).");
    }
}
