<?php

namespace App\Mail;

use App\Models\Biodata;
use App\Models\EvaluasiAtasan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * KonfirmasiEvaluasiAtasanTerkirimMail
 *
 * Mengirim email bukti dan ucapan terima kasih kepada atasan / pengguna lulusan
 * segera setelah formulir evaluasi kinerja alumni UKDW berhasil diserahkan (submitted).
 */
class KonfirmasiEvaluasiAtasanTerkirimMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Inisialisasi mailable dengan data biodata alumni dan rekaman evaluasi.
     */
    public function __construct(
        public Biodata $biodata,
        public EvaluasiAtasan $evaluasi
    ) {}

    /**
     * Menentukan amplop email dan subjek pesan.
     */
    public function envelope(): Envelope
    {
        $namaAlumni = $this->biodata->nama ?? $this->biodata->dataAkademik?->nama ?? 'Alumni UKDW';

        return new Envelope(
            subject: "Respon Evaluasi Kinerja Lulusan UKDW Berhasil Diterima - {$namaAlumni}",
        );
    }

    /**
     * Menentukan template Blade pengirim email konfirmasi.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.konfirmasi_evaluasi_atasan',
        );
    }

    /**
     * Lampiran file email (jika ada).
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
