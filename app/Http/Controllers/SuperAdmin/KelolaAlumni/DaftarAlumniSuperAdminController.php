<?php

namespace App\Http\Controllers\SuperAdmin\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Jobs\SendBlastReminderEmailJob;
use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\Prodi;
use App\Services\Alumni\AlumniDirectoryQueryService;
use App\Services\Export\AlumniProdiMultiSheetExcelExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DaftarAlumniSuperAdminController
 *
 * Fungsi:
 * Menampilkan daftar seluruh alumni terpadu untuk Super Admin lengkap dengan filter Prodi,
 * Tahun Kelulusan (default tahun terbaru), Semester Lulus, Pencarian Nama/NIM, serta audit status
 * kelengkapan data & evaluasi atasan, serta fitur ekspor ZIP per Prodi untuk tahun yang difilter.
 */
class DaftarAlumniSuperAdminController extends Controller
{
    /**
     * Tampilkan Halaman Index Daftar Alumni Terpadu.
     *
     * @param  Request  $request  HTTP request yang memuat parameter filter
     * @param  AlumniDirectoryQueryService  $directoryService  Layanan agregasi kueri direktori alumni
     * @return Response Inertia render halaman direktori alumni Super Admin
     */
    public function index(Request $request, AlumniDirectoryQueryService $directoryService): Response
    {
        $data = $directoryService->getDirectoryData($request, [
            'server_pagination' => true,
        ]);
        $data['prodis'] = Prodi::orderBy('kode_prodi', 'asc')->get();

        return Inertia::render('SuperAdmin/Alumni/Index', $data);
    }

    /**
     * Unduh Berkas Excel Multi-Sheet Komprehensif per Program Studi
     * (Dialihkan ke format 1 berkas Excel multi-sheet per prodi agar tidak lagi berupa arsip ZIP individual).
     */
    public function exportZip(Request $request): StreamedResponse|RedirectResponse
    {
        return $this->exportExcelProdi($request);
    }

    /**
     * Kirim Email Pengingat Kuesioner ke Satu Alumni Tertentu.
     *
     * @param  Request  $request  HTTP request
     * @param  int|string  $id  ID biodata alumni target
     * @return RedirectResponse Redirect kembali dengan flash notification
     */
    public function sendReminderEmail(Request $request, int|string $id): RedirectResponse
    {
        $alumni = Biodata::with(['user', 'dataAkademik'])->findOrFail($id);
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

    /**
     * Unduh Berkas Excel Multi-Sheet Komprehensif per Program Studi
     */
    public function exportExcelProdi(Request $request): StreamedResponse|RedirectResponse
    {
        $prodiId = $request->input('kode_prodi');
        $tahun = $request->input('tahun');
        $semester = $request->input('semester');

        if (! $prodiId || $prodiId === 'all') {
            return back()->with('error', 'Silakan pilih Program Studi terlebih dahulu untuk mengunduh berkas Excel.');
        }

        return AlumniProdiMultiSheetExcelExporter::download($prodiId, $tahun, $semester);
    }

    /**
     * Kirim Email Pengingat Massal ke Alumni yang Belum Selesai (Berdasarkan Filter Aktif via Antrean)
     */
    public function blastEmail(Request $request): RedirectResponse
    {
        $tahun = $request->input('tahun');
        $semester = $request->input('semester');
        $prodiId = $request->input('kode_prodi');
        $search = $request->input('search');

        $query = DB::table('v_alumni_audit_rekap')->where('is_complete_total', 0);

        if ($prodiId && $prodiId !== 'all') {
            $query->where('kode_prodi', $prodiId);
        }

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
            return back()->with('info', 'Tidak ditemukan alumni yang belum menyelesaikan kuesioner pada filter aktif terpilih.');
        }

        SendBlastReminderEmailJob::dispatch($biodataIds, Auth::id(), [
            'tahun' => $tahun ?: 'Semua',
            'semester' => $semester ?: 'Semua',
            'kode_prodi' => $prodiId ?: 'Semua',
            'search' => $search ?: null,
        ]);

        return back()->with('success', 'Email pengingat tracer study untuk '.count($biodataIds).' alumni berhasil dijadwalkan ke dalam antrean sistem.');
    }
}
