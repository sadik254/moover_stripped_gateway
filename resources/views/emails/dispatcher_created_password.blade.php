<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatcher Account Created</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px;background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#ffffff;">
                            @if (!empty($companyLogo))
                                <img src="{{ $companyLogo }}" alt="{{ $platformName }} logo" style="max-height:44px;display:block;margin-bottom:12px;">
                            @endif
                            <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">Dispatcher Account</div>
                            <div style="font-size:28px;font-weight:700;line-height:1.3;">Your account is ready</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 24px;">
                            <p style="margin:0 0 12px;font-size:16px;">Hi {{ $dispatcher->name ?? 'Dispatcher' }},</p>
                            <p style="margin:0 0 16px;line-height:1.6;color:#4b5563;">
                                Your dispatcher account has been created by our team. Use the credentials below to sign in.
                            </p>
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:14px;">
                                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:6px;">Email</div>
                                <div style="font-size:15px;font-weight:600;color:#0f172a;">{{ $dispatcher->email }}</div>
                            </div>
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:16px;">
                                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:6px;">Temporary Password</div>
                                <div style="font-size:22px;font-weight:700;letter-spacing:3px;color:#0f172a;">{{ $generatedPassword }}</div>
                            </div>
                            <p style="margin:0 0 8px;line-height:1.6;color:#4b5563;">
                                For security, please change this password after your first login.
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
