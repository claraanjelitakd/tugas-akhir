<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 18px 45px rgba(18,61,43,0.12);
            border: 1px solid #d5e8d9;
        }
        .email-header {
            background-color: #eaf5ed;
            padding: 30px 24px;
            text-align: left;
            color: #123d2b;
        }
        .email-header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #0d542b;
        }
        .email-header p {
            margin: 4px 0 0 0;
            font-size: 12px;
            color: #527265;
        }
        .email-body {
            padding: 32px 28px;
            line-height: 1.6;
        }
        .greeting {
            font-size: 16px;
            font-weight: bold;
            color: #123d2b;
            margin-bottom: 16px;
        }
        .email-body p {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 20px;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0;
        }
        .btn-reset {
            background-color: #0d542b;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 28px;
            font-weight: 800;
            font-size: 14px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 6px 14px rgba(13, 84, 43, 0.2);
        }
        .note-box {
            background-color: #f7f9f6;
            border-left: 4px solid #fdc700;
            padding: 14px;
            border-radius: 4px;
            font-size: 12px;
            color: #64748b;
            margin-top: 24px;
        }
        .email-footer {
            background-color: #f7f9f6;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .link-alt {
            word-break: break-all;
            color: #0d542b;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>TRACER STUDY UKDW</h1>
            <p>Universitas Kristen Duta Wacana</p>
        </div>
        <div class="email-body">
            <div class="greeting">Halo, {{ $user->name ?? $user->username }}</div>
            <p>
                Kami menerima permintaan untuk melakukan atur ulang (reset) kata sandi akun Anda di aplikasi <strong>Tracer Study UKDW</strong>.
            </p>
            <p>
                Silakan klik tombol di bawah ini untuk membuat kata sandi baru:
            </p>
            <div class="btn-container">
                <a href="{{ $resetUrl }}" class="btn-reset">Reset Kata Sandi Akun</a>
            </div>
            <p>
                Tautan ini berlaku selama <strong>60 menit</strong>. Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini dan kata sandi Anda tidak akan berubah.
            </p>

            <div class="note-box">
                <strong>Mengalami masalah dengan tombol di atas?</strong><br>
                Salin dan tempel tautan berikut ke peramban (browser) Anda:<br>
                <a href="{{ $resetUrl }}" class="link-alt">{{ $resetUrl }}</a>
            </div>
        </div>
        <div class="email-footer">
            &copy; {{ date('Y') }} Tracer Study Universitas Kristen Duta Wacana. All rights reserved.
        </div>
    </div>
</body>
</html>
