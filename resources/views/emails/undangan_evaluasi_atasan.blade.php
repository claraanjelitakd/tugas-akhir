@extends('emails.layout')

@section('title', 'Undangan Evaluasi Lulusan UKDW')
@section('preheader', 'Mohon kesediaan Anda mengisi evaluasi singkat kinerja lulusan UKDW.')

@section('content')
    <p class="email-eyebrow">Evaluasi Pengguna Lulusan</p>
    <h1 class="email-title">Mohon evaluasi kinerja lulusan</h1>

    <p class="email-text">
        Kepada Yth. Bapak/Ibu Atasan atau Pimpinan,
    </p>

    <p class="email-text">
        Dalam rangka peningkatan mutu pendidikan dan kurikulum di
        <strong>Universitas Kristen Duta Wacana (UKDW)</strong>, kami memohon kesediaan Bapak/Ibu
        untuk memberikan evaluasi singkat terhadap kinerja lulusan kami yang bekerja di perusahaan atau instansi Anda.
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
            <td class="email-info-value">{{ $biodata->perusahaan?->nama_perusahaan ?? '-' }}</td>
        </tr>
    </table>

    <p class="email-text">
        Formulir dapat diisi langsung tanpa login melalui tombol berikut.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="email-button-wrap">
                <a href="{{ $evaluasiUrl }}" class="email-button" style="display: inline-block; padding: 14px 24px; border-radius: 9px; background-color: #0d542b; color: #ffffff; font-size: 14px; font-weight: 700; line-height: 1.3; text-align: center; text-decoration: none;">
                    Isi Formulir Evaluasi
                </a>
            </td>
        </tr>
    </table>

    <div class="email-link-box">
        Jika tombol tidak dapat diklik, salin tautan berikut ke peramban:<br>
        <a href="{{ $evaluasiUrl }}" class="email-link">{{ $evaluasiUrl }}</a>
    </div>
@endsection
