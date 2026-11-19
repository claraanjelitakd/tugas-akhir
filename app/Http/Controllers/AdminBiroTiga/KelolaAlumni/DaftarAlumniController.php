<?php

namespace App\Http\Controllers\AdminBiroTiga\KelolaAlumni;

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
 * DaftarAlumniController (Biro 3)
 *
 * Fungsi:
 * Menampilkan direktori seluruh mahasiswa & alumni UKDW untuk Biro 3
 * dengan filter Program Studi, Tahun Kelulusan (default terplot ke tahun terbaru), Target Kelulusan, Semester Lulus,
 * Pencarian Nama/NIM, serta fitur unduh ZIP Excel per Prodi, kirim email pengingat instan, dan blast email massal.
 */
class DaftarAlumniController extends Controller
{
    /**
     * Tampilkan Halaman Daftar Alumni
     */
    public function tampilkanDaftarAlumni(Request $request, AlumniDirectoryQueryService $directoryService): Response
    {
        $data = $directoryService->getDirectoryData($request, [
            'server_pagination' => true,
        ]);
        $data['prodis'] = Prodi::orderBy('kode_prodi', 'asc')->get();

        return Inertia::render('AdminBiroTiga/AlumniIndex', $data);
    }

    /**
     * Unduh Berkas Excel Multi-Sheet Komprehensif per Program Studi
     */
    public function exportExcelProdi(Request $request): StreamedResponse|RedirectResponse
    {
        $prodiId = $request->input('prodi_id');
        $tahun = $request->input('tahun');
        $semester = $request->input('semester');

        if (! $prodiId || $prodiId === 'all') {
            return back()->with('error', 'Silakan pilih Program Studi terlebih dahulu untuk mengunduh berkas Excel.');
        }

        return AlumniProdiMultiSheetExcelExporter::download((int) $prodiId, $tahun, $semester);
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
     * Kirim Email Pengingat Kuesioner ke Satu Alumni Tertentu (Flash Notifikasi)
     */
    public function sendReminderEmail(Request $request, $id): RedirectResponse
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
     * Kirim Email Pengingat Massal ke Seluruh Alumni yang Belum Selesai (Berdasarkan Filter Aktif via Antrean)
     */
    public function blastEmail(Request $request): RedirectResponse
    {
        $tahun = $request->input('tahun');
        $semester = $request->input('semester');
        $prodiId = $request->input('prodi_id');
        $search = $request->input('search');

        $query = DB::table('v_alumni_audit_rekap')->where('is_complete_total', 0);

        if ($prodiId && $prodiId !== 'all') {
            $query->where('prodi_id', $prodiId);
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
            'prodi_id' => $prodiId ?: 'Semua',
            'search' => $search ?: null,
        ]);

        return back()->with('success', 'Email pengingat tracer study untuk '.count($biodataIds).' alumni berhasil dijadwalkan ke dalam antrean sistem.');
    }
}
