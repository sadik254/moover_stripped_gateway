<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disbursement Paid</title>
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
                            <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">Disbursement Paid</div>
                            <div style="font-size:28px;font-weight:700;line-height:1.3;">Payment sent successfully</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 24px;">
                            <p style="margin:0 0 12px;font-size:16px;">Hi {{ $affiliate->name ?? 'Affiliate' }},</p>
                            <p style="margin:0 0 16px;line-height:1.6;color:#4b5563;">
                                Your disbursement has been completed successfully.
                            </p>
                            <p style="margin:0 0 8px;color:#374151;">Booking ID: <strong>{{ $disbursement->booking_id }}</strong></p>
                            <p style="margin:0 0 8px;color:#374151;">Amount: <strong>{{ number_format((float) $disbursement->amount, 2) }} {{ strtoupper($disbursement->currency) }}</strong></p>
                            <p style="margin:0 0 8px;color:#374151;">Transfer ID: <strong>{{ $disbursement->stripe_transfer_id ?? 'N/A' }}</strong></p>
                            <p style="margin:0;color:#374151;">Thanks,<br><strong>{{ $platformName }}</strong></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

