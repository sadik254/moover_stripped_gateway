<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Password Reset Code</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px;background:#000000;color:#ffffff;">
                            @if (!empty($companyLogo))
                                <img src="{{ $companyLogo }}" alt="{{ $platformName }} logo" style="max-height:44px;display:block;margin-bottom:12px;">
                            @endif
                            <div style="font-size:18px;font-weight:700;margin-bottom:14px;">{{ $platformName }}</div>
                            <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">Affiliate Password Reset</div>
                            <div style="font-size:28px;font-weight:700;line-height:1.3;">Use this reset code</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 24px;">
                            <p style="margin:0 0 12px;font-size:16px;">Hi {{ $affiliate->name ?? 'Affiliate' }},</p>
                            <p style="margin:0 0 16px;line-height:1.6;color:#4b5563;">
                                We received a request to reset your password. Enter this code in the app to continue.
                            </p>
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center;margin-bottom:14px;">
                                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:8px;">Reset Code</div>
                                <div style="font-size:30px;font-weight:700;letter-spacing:6px;color:#0f172a;">{{ $resetCode }}</div>
                            </div>
                            <p style="margin:0 0 8px;line-height:1.6;color:#4b5563;">
                                This code expires in 15 minutes. If you didn't request this, ignore this email.
                            </p>
                            <p style="margin:0;color:#374151;">Thanks,<br><strong>{{ $platformName }}</strong></p>
                        </td>
                    </tr>
                    @if (!empty($companyEmail) || !empty($companyPhone) || !empty($companyAddress))
                        <tr>
                            <td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;">
                                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:8px;">Contact</div>
                                @if (!empty($companyEmail))
                                    <div style="font-size:14px;color:#334155;margin-bottom:4px;">Email: {{ $companyEmail }}</div>
                                @endif
                                @if (!empty($companyPhone))
                                    <div style="font-size:14px;color:#334155;margin-bottom:4px;">Phone: {{ $companyPhone }}</div>
                                @endif
                                @if (!empty($companyAddress))
                                    <div style="font-size:14px;color:#334155;">Address: {{ $companyAddress }}</div>
                                @endif
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
