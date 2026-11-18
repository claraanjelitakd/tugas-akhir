<?php

namespace App\Mail;

use App\Models\Biodata;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PengingatKuesionerAlumniMail
 *
 * Mengirim email pengingat resmi dari Biro 3 UKDW kepada alumni
 * berisi tautan login ke portal Tracer Study UKDW dan petunjuk login.
 */
class PengingatKuesionerAlumniMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $portalUrl;

    public string $nim;

    public string $namaAlumni;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Biodata $biodata,
        public ?string $pesanTambahan = null
    ) {
        $this->namaAlumni = $biodata->nama ?? $biodata->dataAkademik?->nama ?? $biodata->user?->name ?? 'Alumni';
        $this->nim = (string) ($biodata->nim ?? $biodata->user?->username ?? '-');
        $this->portalUrl = url('/login');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pengingat Pengisian Tracer Study UKDW - {$this->namaAlumni} ({$this->nim})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.pengingat_kuesioner_alumni',
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
