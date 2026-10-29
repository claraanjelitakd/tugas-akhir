@extends('emails.layout')

@section('title', 'Reset Kata Sandi - Tracer Study UKDW')
@section('preheader', 'Gunakan tautan aman untuk membuat kata sandi baru akun Tracer Study UKDW Anda.')

@section('content')
    <p class="email-eyebrow">Keamanan Akun</p>
    <h1 class="email-title">Reset kata sandi akun</h1>

    <p class="email-text">
        Halo, <strong>{{ $user->name ?? $user->username }}</strong>.
    </p>

    <p class="email-text">
        Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda di portal Tracer Study UKDW.
        Klik tombol di bawah untuk membuat kata sandi baru.
    </p>

    <table role="presentation" class="email-info" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="email-info-label">Username</td>
            <td class="email-info-value">{{ $user->username }}</td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="email-button-wrap">
                <a href="{{ $resetUrl }}" class="email-button" style="display: inline-block; padding: 14px 24px; border-radius: 9px; background-color: #0d542b; color: #ffffff; font-size: 14px; font-weight: 700; line-height: 1.3; text-align: center; text-decoration: none;">
                    Atur Ulang Kata Sandi
                </a>
            </td>
        </tr>
    </table>

    <p class="email-text">
        Tautan ini berlaku selama <strong>60 menit</strong>. Jika Anda tidak meminta reset kata sandi,
        abaikan email ini.
    </p>

    <div class="email-link-box">
        Jika tombol tidak dapat diklik, salin tautan berikut ke peramban:<br>
        <a href="{{ $resetUrl }}" class="email-link">{{ $resetUrl }}</a>
    </div>
@endsection
