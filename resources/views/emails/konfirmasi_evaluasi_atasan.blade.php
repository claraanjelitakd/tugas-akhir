@extends('emails.layout')

@section('title', 'Respon Evaluasi Lulusan UKDW Berhasil Diterima')
@section('preheader', 'Terima kasih telah mengisi formulir evaluasi kinerja lulusan Universitas Kristen Duta Wacana.')

@section('content')
    <p class="email-eyebrow">Evaluasi Pengguna Lulusan</p>
    <h1 class="email-title">Evaluasi Berhasil Diterima</h1>

    <p class="email-text">
        Yth. <strong>{{ $evaluasi->atasan?->nama ?: 'Bapak/Ibu Pimpinan / Atasan' }}</strong>,
    </p>

    <p class="email-text">
        Terima kasih yang sebesar-besarnya atas partisipasi dan waktu yang telah Anda luangkan untuk memberikan evaluasi obyektif terhadap kinerja lulusan kami di <strong>{{ $evaluasi->perusahaan?->nama_perusahaan ?: ($biodata->perusahaan?->nama_perusahaan ?: 'instansi Anda') }}</strong>.
    </p>

    <p class="email-text">
        Jawaban dan masukan konstruktif Anda telah berhasil kami rekam secara resmi ke dalam sistem basis data Tracer Study Universitas Kristen Duta Wacana (UKDW).
    </p>

    <table role="presentation" class="email-info" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="email-info-label">Nama alumni</td>
            <td class="email-info-value">{{ $biodata->nama ?? $biodata->dataAkademik?->nama ?? 'Alumni UKDW' }}</td>
        </tr>
        <tr>
            <td class="email-info-label">NIM</td>
            <td class="email-info-value">{{ $biodata->nim ?? '-' }}</td>
        </tr>
        <tr>
            <td class="email-info-label">Program studi</td>
            <td class="email-info-value">{{ $biodata->prodi?->nama_prodi ?? $biodata->dataAkademik?->program_studi ?? '-' }}</td>
        </tr>
        <tr>
            <td class="email-info-label">Perusahaan/instansi</td>
            <td class="email-info-value">{{ $evaluasi->perusahaan?->nama_perusahaan ?: ($biodata->perusahaan?->nama_perusahaan ?: '-') }}</td>
        </tr>
        <tr>
            <td class="email-info-label">Waktu pengisian</td>
            <td class="email-info-value">{{ $evaluasi->submitted_at ? $evaluasi->submitted_at->timezone('Asia/Jakarta')->format('d F Y, H:i') . ' WIB' : now()->timezone('Asia/Jakarta')->format('d F Y, H:i') . ' WIB' }}</td>
        </tr>
    </table>

    <p class="email-text">
        Penilaian dari Anda sangat berharga bagi kami dalam rangka penjaminan mutu kurikulum, peningkatan kompetensi lulusan, serta pemenuhan baku mutu akreditasi universitas.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="email-button-wrap">
                <a href="https://www.ukdw.ac.id" class="email-button" style="display: inline-block; padding: 14px 24px; border-radius: 9px; background-color: #0d542b; color: #ffffff; font-size: 14px; font-weight: 700; line-height: 1.3; text-align: center; text-decoration: none;">
                    Kunjungi Situs Resmi UKDW
                </a>
            </td>
        </tr>
    </table>
@endsection

