<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;color:#17202a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e3e7ef;border-radius:8px;">
                    <tr>
                        <td style="padding:24px 28px 8px 28px;">
                            <h1 style="font-size:20px;line-height:1.35;margin:0;color:#17202a;">{{ $subjectLine }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 18px 28px;">
                            <p style="font-size:15px;line-height:1.6;margin:0;color:#374151;">{{ $bodyText }}</p>
                        </td>
                    </tr>
                    @if ($actionUrl)
                        <tr>
                            <td style="padding:0 28px 24px 28px;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;border-radius:6px;padding:11px 16px;font-size:14px;font-weight:700;">
                                    {{ $actionText }}
                                </a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:16px 28px 24px 28px;border-top:1px solid #e5e7eb;">
                            <p style="font-size:12px;line-height:1.5;margin:0;color:#6b7280;">
                                You are receiving this because email notifications are enabled for your Mindful AU account.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
