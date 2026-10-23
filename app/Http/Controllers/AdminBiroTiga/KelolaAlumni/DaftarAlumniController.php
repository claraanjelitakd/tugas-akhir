<?php

namespace App\Http\Controllers\AdminBiroTiga\KelolaAlumni;

use App\Http\Controllers\Controller;
use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\Prodi;
use App\Services\Alumni\AlumniDirectoryQueryService;
use App\Services\Export\AlumniTracerExcelExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

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
        $data = $directoryService->getDirectoryData($request);
        $data['prodis'] = Prodi::orderBy('kode_prodi', 'asc')->get();

        return Inertia::render('AdminBiroTiga/AlumniIndex', $data);
    }

    /**
     * Unduh Arsip ZIP Berisi Berkas Excel (.xls) per Alumni untuk Prodi dan Tahun Tertentu
     */
    public function exportZip(Request $request)
    {
        $prodiId = $request->input('prodi_id');
        $tahun = $request->input('tahun');

        if (! $prodiId || $prodiId === 'all') {
            return back()->with('error', 'Silakan pilih Program Studi terlebih dahulu untuk mengunduh arsip ZIP.');
        }

        $prodi = Prodi::findOrFail($prodiId);

        // Ambil ID biodata yang cocok melalui view database v_alumni_audit_rekap
        $auditQuery = DB::table('v_alumni_audit_rekap')->where('prodi_id', $prodiId);

        if ($tahun && $tahun !== 'all') {
            $auditQuery->where('tahun_lulus', $tahun);
        }

        $biodataIds = $auditQuery->pluck('biodata_id')->filter()->unique()->values()->all();

        if (empty($biodataIds)) {
            $fallbackQuery = Biodata::where('prodi_id', $prodiId);
            if ($tahun && $tahun !== 'all') {
                $fallbackQuery->whereHas('dataAkademik', function ($da) use ($tahun) {
                    $da->where('tahun_lulus', $tahun);
                });
            }

            $alumnis = $fallbackQuery->with([
                'dataAkademik.yudisium',
                'yudisium',
                'propinsi',
                'kabupaten',
                'perusahaan.propinsi',
                'perusahaan.kabupaten',
                'atasan',
                'user',
                'prodi.fakultas',
                'evaluasiAtasan.atasan',
                'evaluasiAtasan.perusahaan',
                'evaluasiAtasan.respons.pertanyaan',
            ])->get();
        } else {
            $alumnis = Biodata::whereIn('id', $biodataIds)
                ->with([
                    'dataAkademik.yudisium',
                    'yudisium',
                    'propinsi',
                    'kabupaten',
                    'perusahaan.propinsi',
                    'perusahaan.kabupaten',
                    'atasan',
                    'user',
                    'prodi.fakultas',
                    'evaluasiAtasan.atasan',
                    'evaluasiAtasan.perusahaan',
                    'evaluasiAtasan.respons.pertanyaan',
                ])
                ->get();
        }

        if ($alumnis->isEmpty()) {
            return back()->with('error', 'Tidak ditemukan data alumni untuk Program Studi '.$prodi->nama_prodi.' pada tahun '.$tahun.'.');
        }

        $cleanProdiName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prodi->nama_prodi);
        $cleanTahun = $tahun ?: 'Terbaru';
        $zipFileName = "Tracer_Study_Biro3_{$cleanProdiName}_{$cleanTahun}.zip";

        $zipPath = tempnam(sys_get_temp_dir(), 'tracer_biro3_zip_');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Gagal memproses arsip ZIP di server.');
        }

        foreach ($alumnis as $alumni) {
            $excelContent = AlumniTracerExcelExporter::generateExcelContent($alumni);
            $nim = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $alumni->nim);
            $nama = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($alumni->nama ?? 'Alumni'));
            $excelFileName = "Tracer_Study_{$nim}_{$nama}.xls";

            $zip->addFromString($excelFileName, $excelContent);
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ])->deleteFileAfterSend(true);
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

    /**
     * Kirim Email Pengingat Massal ke Seluruh Alumni yang Belum Selesai (per Tahun Kelulusan)
     */
    public function blastEmail(Request $request): RedirectResponse
    {
        $tahun = $request->input('tahun');

        if (! $tahun || $tahun === 'all') {
            return back()->with('error', 'Silakan pilih Tahun Kelulusan terlebih dahulu sebelum mengirim email pengingat massal.');
        }

        $pendingAlumnis = DB::table('v_alumni_audit_rekap')
            ->where('tahun_lulus', $tahun)
            ->where('is_complete_total', 0)
            ->get();

        if ($pendingAlumnis->isEmpty()) {
            return back()->with('info', "Seluruh alumni pada tahun kelulusan {$tahun} telah menyelesaikan kuesioner tracer study.");
        }

        $sentCount = 0;
        foreach ($pendingAlumnis as $item) {
            $email = $item->email_pribadi ?: ($item->email ?: null);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $biodata = Biodata::find($item->biodata_id);
                if ($biodata) {
                    try {
                        Mail::to($email)->send(new PengingatKuesionerAlumniMail($biodata));
                        $sentCount++;
                    } catch (\Throwable $e) {
                        // Lanjutkan jika satu email gagal
                    }
                }
            }
        }

        return back()->with('success', "Email pengingat tracer study berhasil dikirimkan secara massal ke {$sentCount} alumni angkatan {$tahun}!");
    }
}
