<?php

namespace App\Mail;

use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * UndanganEvaluasiAtasanMail
 *
 * Mengirim email otomatis berisi tautan publik khusus (tanpa login)
 * bagi Atasan / Pengguna Lulusan untuk menguji evaluasi kinerja alumni UKDW.
 */
class UndanganEvaluasiAtasanMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $evaluasiUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Biodata $biodata,
        public EvaluasiAtasan $evaluasi
    ) {
        $this->evaluasiUrl = url('/evaluasi-atasan/'.$evaluasi->token);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $namaAlumni = $this->biodata->nama ?? $this->biodata->dataAkademik?->nama ?? 'Alumni';

        return new Envelope(
            subject: "Form Evaluasi Kepuasan & Kinerja Lulusan UKDW - {$namaAlumni}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.undangan_evaluasi_atasan',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
