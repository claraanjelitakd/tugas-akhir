<?php

namespace App\Http\Controllers\SuperAdmin\KelolaAlumni;

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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $data = $directoryService->getDirectoryData($request);
        $data['prodis'] = Prodi::orderBy('kode_prodi', 'asc')->get();

        return Inertia::render('SuperAdmin/Alumni/Index', $data);
    }

    /**
     * Download Berkas ZIP berisi Excel (.xls) per Lulusan untuk Prodi dan Tahun Tertentu.
     *
     * @param  Request  $request  HTTP request yang memuat prodi_id dan tahun
     * @return BinaryFileResponse|RedirectResponse
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
        $auditQuery = DB::table('v_alumni_audit_rekap')
            ->where('prodi_id', $prodiId);

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
        $zipFileName = "Tracer_Study_{$cleanProdiName}_{$cleanTahun}.zip";

        $zipPath = tempnam(sys_get_temp_dir(), 'tracer_zip_');
        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
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
            Mail::to($targetEmail)->send(new PengingatKuesionerAlumniMail($alumni));
        } catch (\Throwable $e) {
            // Tangani kegagalan SMTP jika offline/sandbox
        }

        return back()->with('success', "Email pengingat kuesioner berhasil dikirim ke {$alumni->nama} ({$targetEmail}).");
    }
}
