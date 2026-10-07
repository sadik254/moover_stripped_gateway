<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;"><tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
            <tr><td style="padding:24px;background:#000000;color:#ffffff;">
                @if (!empty($companyLogo))<img src="{{ $companyLogo }}" alt="{{ $platformName }} logo" style="max-height:44px;display:block;margin-bottom:12px;">@endif
                <div style="font-size:18px;font-weight:700;margin-bottom:14px;">{{ $platformName }}</div>
                <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">{{ $isAdminCopy ? 'Operations notification' : 'Sprinter request received' }}</div>
                <div style="font-size:28px;font-weight:700;line-height:1.3;">{{ $isAdminCopy ? 'A Sprinter quote needs attention' : 'We will be in touch as soon as possible' }}</div>
            </td></tr>
            <tr><td style="padding:26px 24px;">
                <p style="margin:0 0 12px;font-size:16px;">Hi {{ $isAdminCopy ? 'Operations team' : $quoteRequest['name'] }},</p>
                <p style="margin:0 0 18px;line-height:1.6;color:#4b5563;">
                    @if ($isAdminCopy)
                        A customer has requested a custom quote for a Sprinter. Their details are below.
                    @else
                        Thanks for your interest in {{ $vehicleClass->name }}. We received your request and will contact you as soon as possible.
                    @endif
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:16px;">
                    <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;width:38%;">Requested vehicle</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $vehicleClass->name }}</td></tr>
                    @if ($isAdminCopy)
                        <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Name</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $quoteRequest['name'] }}</td></tr>
                        <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Email</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $quoteRequest['email'] }}</td></tr>
                        <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Phone</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $quoteRequest['phone'] }}</td></tr>
                    @endif
                </table>
                <p style="margin:0;color:#374151;">Thanks,<br><strong>{{ $platformName }}</strong></p>
            </td></tr>
            @if (!empty($companyEmail) || !empty($companyPhone) || !empty($companyAddress))
                <tr><td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:14px;color:#334155;">
                    @if (!empty($companyEmail))<div style="margin-bottom:4px;">Email: {{ $companyEmail }}</div>@endif
                    @if (!empty($companyPhone))<div style="margin-bottom:4px;">Phone: {{ $companyPhone }}</div>@endif
                    @if (!empty($companyAddress))<div>{{ $companyAddress }}</div>@endif
                </td></tr>
            @endif
        </table>
    </td></tr></table>
</body>
</html>
