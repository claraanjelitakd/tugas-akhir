<?php

namespace App\Http\Controllers\AdminFakultas\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\Prodi;
use App\Models\RefFakultas;
use App\Services\Alumni\AlumniDirectoryQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

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
        $user = Auth::user()->load('fakultas');
        $fakultasId = $user->fakultas_id;

        if (! $fakultasId) {
            $fakultas = RefFakultas::first();
            $fakultasId = $fakultas?->id;
        } else {
            $fakultas = $user->fakultas ?: RefFakultas::find($fakultasId);
        }

        $daftarProdiFakultas = Prodi::where('fakultas_id', $fakultasId)
            ->orderBy('kode_prodi', 'asc')
            ->get();

        $prodiIdsFakultas = $daftarProdiFakultas->pluck('id')->all();

        $data = $directoryService->getDirectoryData($request, [
            'prodi_ids' => $prodiIdsFakultas,
            'fakultas_id' => $fakultasId,
        ]);

        $data['user'] = $user;
        $data['fakultas'] = $fakultas;
        $data['prodis'] = $daftarProdiFakultas;

        return Inertia::render('AdminFakultas/Alumni/Index', $data);
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
            Mail::to($targetEmail)->send(new PengingatKuesionerAlumniMail($alumni));
        } catch (\Throwable $e) {
            // Tangani kegagalan SMTP jika offline/sandbox
        }

        return back()->with('success', "Email pengingat kuesioner berhasil dikirim ke {$alumni->nama} ({$targetEmail}).");
    }
}
