@extends('emails.layout')

@section('title', 'Pengingat Tracer Study UKDW')
@section('preheader', 'Mohon lengkapi kuesioner Tracer Study UKDW melalui portal resmi.')

@section('content')
    <p class="email-eyebrow">Pengisian Tracer Study</p>
    <h1 class="email-title">Mohon lengkapi Tracer Study UKDW</h1>

    <p class="email-text">
        Halo Sdr/i <strong>{{ $namaAlumni }}</strong>,
    </p>

    <p class="email-text">
        Kami mengundang Anda untuk berpartisipasi dalam pengisian instrumen
        <strong>Tracer Study UKDW</strong>. Data yang Anda sampaikan membantu evaluasi kurikulum,
        peningkatan mutu pendidikan, serta akreditasi program studi dan universitas.
    </p>

    <table role="presentation" class="email-info" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="email-info-label">Portal</td>
            <td class="email-info-value"><a href="{{ $portalUrl }}" class="email-link">Buka portal Tracer Study</a></td>
        </tr>
        <tr>
            <td class="email-info-label">Username</td>
            <td class="email-info-value">{{ $nim }} (NIM)</td>
        </tr>
        <tr>
            <td class="email-info-label">Kata sandi</td>
            <td class="email-info-value">Kata sandi akun Tracer Study Anda</td>
        </tr>
    </table>

    @if ($pesanTambahan)
        <div class="email-note">
            {{ $pesanTambahan }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="email-button-wrap">
                <a href="{{ $portalUrl }}" class="email-button" style="display: inline-block; padding: 14px 24px; border-radius: 9px; background-color: #0d542b; color: #ffffff; font-size: 14px; font-weight: 700; line-height: 1.3; text-align: center; text-decoration: none;">
                    Masuk dan Isi Kuesioner
                </a>
            </td>
        </tr>
    </table>

    <p class="email-text" style="margin-bottom: 0; color: #66736c; font-size: 13px;">
        Waktu pengisian kurang lebih 5–10 menit. Jika mengalami kendala teknis, silakan hubungi tim Biro 3 UKDW melalui layanan resmi universitas.
    </p>
@endsection
