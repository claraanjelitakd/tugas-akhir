<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>@yield('title', 'Tracer Study UKDW')</title>
    <style>
        html,
        body {
            width: 100% !important;
            min-width: 100%;
            margin: 0;
            padding: 0;
            background-color: #f4f7f5;
        }

        body {
            color: #243229;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
            border-spacing: 0;
        }

        img {
            border: 0;
            display: block;
            max-width: 100%;
        }

        a {
            color: #0d542b;
        }

        .email-container {
            width: 100%;
            max-width: 640px;
        }

        .email-card {
            overflow: hidden;
            background-color: #ffffff;
            border: 1px solid #dce8df;
            border-radius: 16px;
        }

        .email-header {
            padding: 28px 32px;
            background-color: #0d542b;
            color: #ffffff;
        }

        .email-brand {
            margin: 0;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.4px;
            line-height: 1.3;
        }

        .email-brand-subtitle {
            margin: 6px 0 0;
            color: #dcefe2;
            font-size: 12px;
            line-height: 1.5;
        }

        .email-content {
            padding: 34px 32px;
        }

        .email-eyebrow {
            margin: 0 0 8px;
            color: #0d542b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .email-title {
            margin: 0 0 18px;
            color: #14231a;
            font-size: 25px;
            font-weight: 700;
            line-height: 1.25;
        }

        .email-text {
            margin: 0 0 18px;
            color: #526158;
            font-size: 15px;
            line-height: 1.7;
        }

        .email-text strong {
            color: #243229;
        }

        .email-info {
            width: 100%;
            margin: 22px 0;
            background-color: #f3f8f4;
            border: 1px solid #dce8df;
            border-radius: 10px;
        }

        .email-info td {
            padding: 13px 16px;
            border-bottom: 1px solid #dce8df;
            color: #526158;
            font-size: 13px;
            line-height: 1.55;
            vertical-align: top;
        }

        .email-info tr:last-child td {
            border-bottom: 0;
        }

        .email-info-label {
            width: 34%;
            color: #66736c !important;
            font-size: 12px !important;
        }

        .email-info-value {
            color: #243229 !important;
            font-weight: 700;
            word-break: break-word;
        }

        .email-button-wrap {
            padding: 8px 0 24px;
            text-align: center;
        }

        .email-button {
            display: inline-block;
            padding: 14px 24px;
            border-radius: 9px;
            background-color: #0d542b;
            box-shadow: 0 4px 10px rgba(13, 84, 43, 0.18);
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.3;
            text-align: center;
            text-decoration: none;
        }

        .email-note {
            margin: 20px 0 0;
            padding: 14px 16px;
            background-color: #f8faf8;
            border-left: 3px solid #0d542b;
            color: #66736c;
            font-size: 12px;
            line-height: 1.6;
        }

        .email-link-box {
            margin-top: 20px;
            padding: 14px 16px;
            background-color: #f8faf8;
            border: 1px solid #dce8df;
            border-radius: 8px;
            color: #66736c;
            font-size: 12px;
            line-height: 1.6;
        }

        .email-link {
            color: #0d542b;
            word-break: break-all;
        }

        .email-footer {
            padding: 20px 32px 24px;
            background-color: #f8faf8;
            border-top: 1px solid #e8efe9;
            color: #7a8780;
            font-size: 11px;
            line-height: 1.6;
            text-align: center;
        }

        .email-footer p {
            margin: 3px 0;
        }

        .email-footer-title {
            color: #526158;
            font-size: 12px;
            font-weight: 700;
        }

        @media screen and (max-width: 600px) {
            .email-page-padding {
                padding: 12px 8px !important;
            }

            .email-header,
            .email-content {
                padding: 24px 18px !important;
            }

            .email-footer {
                padding: 18px 18px 22px !important;
            }

            .email-title {
                font-size: 22px !important;
            }

            .email-text {
                font-size: 14px !important;
                line-height: 1.65 !important;
            }

            .email-info td {
                padding: 11px 12px !important;
                font-size: 12px !important;
            }

            .email-info-label {
                width: 38% !important;
            }

            .email-button {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
                padding: 14px 16px !important;
            }

            .email-link-box,
            .email-note {
                padding: 12px !important;
            }
        }
    </style>
</head>
<body>
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; background-color: #f4f7f5;">
        <tr>
            <td align="center" class="email-page-padding" style="padding: 24px 12px;">
                <table role="presentation" class="email-container" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td class="email-card">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="email-header">
                                        <p class="email-brand">TRACER STUDY UKDW</p>
                                        <p class="email-brand-subtitle">Universitas Kristen Duta Wacana</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-content">
                                        @yield('content')
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-footer">
                                        <p class="email-footer-title">Tracer Study UKDW</p>
                                        <p>Pesan ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.</p>
                                        <p>&copy; {{ date('Y') }} Universitas Kristen Duta Wacana</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
