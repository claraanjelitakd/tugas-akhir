<?php

namespace App\Jobs;

use App\Mail\PengingatKuesionerAlumniMail;
use App\Models\Biodata;
use App\Models\LogActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SendBlastReminderEmailJob
 *
 * Job antrean untuk mendistribusikan email pengingat pengisian kuesioner tracer study
 * secara massal (blast) berdasarkan hasil filter aktif atau kohort alumni.
 *
 * Mencegah server lag, blocking HTTP, dan kegagalan SMTP massal dengan pemrosesan chunk bertahap.
 */
class SendBlastReminderEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * Inisialisasi job antrean blast email pengingat.
     *
     * @param  array<int, int>  $biodataIds  Daftar ID Biodata alumni sasaran pengingat
     * @param  int|null  $initiatorUserId  ID Pengguna (Admin) yang memicu blast email
     * @param  array<string, mixed>  $filterContext  Metadata filter yang sedang aktif saat aksi dipicu
     */
    public function __construct(
        public array $biodataIds,
        public ?int $initiatorUserId = null,
        public array $filterContext = []
    ) {}

    /**
     * Eksekusi pekerjaan pengiriman email di antrean latar belakang.
     */
    public function handle(): void
    {
        if (empty($this->biodataIds)) {
            Log::info('SendBlastReminderEmailJob: Tidak ada biodata ID sasaran. Eksekusi dilewati.');

            return;
        }

        $totalSasaran = count($this->biodataIds);
        $sentCount = 0;
        $failedCount = 0;

        Log::info("SendBlastReminderEmailJob: Memulai pengiriman massal ke {$totalSasaran} sasaran alumni.");

        // Proses per chunk 25 data agar memori dan soket antrean tetap ringan
        $chunks = array_chunk($this->biodataIds, 25);

        foreach ($chunks as $chunkIds) {
            $alumnis = Biodata::with(['user', 'dataAkademik'])
                ->whereIn('id', $chunkIds)
                ->get();

            foreach ($alumnis as $alumni) {
                $targetEmail = $alumni->email_pribadi ?: $alumni->user?->email;

                if (! $targetEmail || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                    $failedCount++;
                    Log::warning("SendBlastReminderEmailJob: Email tidak valid untuk alumni NIM {$alumni->nim}.");

                    continue;
                }

                try {
                    Mail::to($targetEmail)->queue(new PengingatKuesionerAlumniMail($alumni));
                    $sentCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    Log::error("SendBlastReminderEmailJob: Gagal mengirimkan email ke {$targetEmail} (NIM: {$alumni->nim}): ".$e->getMessage());
                }
            }
        }

        // Catat rekam jejak aktivitas ke tabel log_activities
        try {
            $desc = "Pengiriman pengingat kuesioner massal berhasil diantrekan untuk {$sentCount} dari {$totalSasaran} alumni.";
            if ($failedCount > 0) {
                $desc .= " ({$failedCount} data dilewati karena email tidak valid).";
            }

            LogActivity::create([
                'user_id' => $this->initiatorUserId,
                'action' => 'blast_email_pengingat',
                'model_type' => 'Biodata',
                'model_id' => null,
                'description' => $desc,
                'new_values' => [
                    'total_sasaran' => $totalSasaran,
                    'total_terkirim' => $sentCount,
                    'total_gagal' => $failedCount,
                    'filter' => $this->filterContext,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendBlastReminderEmailJob: Gagal mencatat log_activities: '.$e->getMessage());
        }

        Log::info("SendBlastReminderEmailJob: Selesai. {$sentCount} email berhasil diantrekan.");
    }
}
