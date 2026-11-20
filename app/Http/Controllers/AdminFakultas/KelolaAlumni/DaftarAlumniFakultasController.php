<?php

namespace App\Http\Controllers\AdminFakultas\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Jobs\SendBlastReminderEmailJob;
use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Models\User;
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
 * DaftarAlumniFakultasController
 *
 * Fungsi:
 * Menampilkan direktori seluruh alumni dalam fakultas yang sedang login,
 * dilengkapi filter program studi yang ada di fakultas tersebut, filter tahun/semester kelulusan,
 * dan pencarian cepat menggunakan Database View (v_alumni_audit_rekap) dengan performa tinggi.
 */
class DaftarAlumniFakultasController extends Controller
{
    /**
     * Tampilkan Direktori Mahasiswa & Alumni Fakultas
     */
    public function index(Request $request, AlumniDirectoryQueryService $directoryService): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('fakultas');
        $fakultasId = $user->kode_fakultas;

        if (! $fakultasId) {
            abort(403, 'Akun Admin Fakultas belum dikaitkan dengan Fakultas.');
        } else {
            $fakultas = $user->fakultas ?: RefFakultas::find($fakultasId);
        }

        $daftarProdiFakultas = Prodi::where('kode_fakultas', $fakultasId)
            ->orderBy('kode_prodi', 'asc')
            ->get();

        $prodiIdsFakultas = $daftarProdiFakultas->pluck('kode_prodi')->all();

        $data = $directoryService->getDirectoryData($request, [
            'prodi_ids' => $prodiIdsFakultas,
            'kode_fakultas' => $fakultasId,
            'server_pagination' => true,
        ]);

        $data['user'] = $user;
        $data['fakultas'] = $fakultas;
        $data['prodis'] = $daftarProdiFakultas;

        return Inertia::render('AdminFakultas/Alumni/Index', $data);
    }

    /**
     * Kirim Email Pengingat Kuesioner ke Satu Alumni Tertentu (Flash Notifikasi)
     */
    public function sendReminderEmail(Request $request, int|string $id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $fakultasId = $user->kode_fakultas;

        if (! $fakultasId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Fakultas.');
        }

        $alumni = Biodata::whereHas('prodi', function ($query) use ($fakultasId) {
            $query->where('kode_fakultas', $fakultasId);
        })
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

    /**
     * Unduh Berkas Excel Multi-Sheet Komprehensif per Program Studi dalam Fakultas
     */
    public function exportExcelProdi(Request $request): StreamedResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $fakultasId = $user->kode_fakultas;
        $prodiId = $request->input('kode_prodi');

        if (! $fakultasId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Fakultas.');
        }

        if (! $prodiId) {
            return back()->with('error', 'Silakan pilih Program Studi terlebih dahulu untuk mengunduh berkas Excel.');
        }

        $prodisFakultas = Prodi::where('kode_fakultas', $fakultasId)->pluck('kode_prodi')->all();
        if (! in_array($prodiId, $prodisFakultas, true)) {
            abort(403, 'Program studi tidak berada di bawah naungan Fakultas Anda.');
        }

        $tahun = $request->input('tahun');
        $semester = $request->input('semester');

        return AlumniProdiMultiSheetExcelExporter::download($prodiId, $tahun, $semester);
    }

    /**
     * Kirim Email Pengingat Massal ke Alumni Fakultas yang Belum Selesai (Berdasarkan Filter Aktif via Antrean)
     */
    public function blastEmail(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $fakultasId = $user->kode_fakultas;

        if (! $fakultasId) {
            abort(403, 'Akun Anda belum dikaitkan dengan Fakultas.');
        }

        $prodiIdsFakultas = Prodi::where('kode_fakultas', $fakultasId)->pluck('kode_prodi')->all();
        $prodiIdInput = $request->input('kode_prodi');
        $tahun = $request->input('tahun');
        $semester = $request->input('semester');
        $search = $request->input('search');

        $query = DB::table('v_alumni_audit_rekap')
            ->where('is_complete_total', 0);

        if ($prodiIdInput && $prodiIdInput !== 'all') {
            $selectedProdiId = $prodiIdInput;
            if (! in_array($selectedProdiId, $prodiIdsFakultas, true)) {
                abort(403, 'Program studi tidak valid.');
            }
            $query->where('kode_prodi', $selectedProdiId);
        } else {
            $query->whereIn('kode_prodi', $prodiIdsFakultas);
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
            return back()->with('info', 'Tidak ditemukan alumni di fakultas yang belum menyelesaikan kuesioner pada filter aktif terpilih.');
        }

        SendBlastReminderEmailJob::dispatch($biodataIds, Auth::id(), [
            'tahun' => $tahun ?: 'Semua',
            'semester' => $semester ?: 'Semua',
            'kode_prodi' => $prodiIdInput ?: 'Semua Prodi Fakultas',
            'kode_fakultas' => $fakultasId,
            'search' => $search ?: null,
        ]);

        return back()->with('success', 'Email pengingat tracer study untuk '.count($biodataIds).' alumni fakultas berhasil dijadwalkan ke dalam antrean sistem.');
    }
}
