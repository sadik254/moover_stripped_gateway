<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isAdminCopy ? 'New Booking' : 'Booking Request Received' }}</title>
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
                            <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">{{ $isAdminCopy ? 'Operations notification' : 'Booking request received' }}</div>
                            <div style="font-size:28px;font-weight:700;line-height:1.3;">{{ $isAdminCopy ? 'A new booking needs attention' : 'Your trip request is in the queue' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 24px;">
                            <p style="margin:0 0 12px;font-size:16px;">Hi {{ $isAdminCopy ? 'Operations team' : ($booking->name ?? 'there') }},</p>
                            <p style="margin:0 0 18px;line-height:1.6;color:#4b5563;">
                                @if ($isAdminCopy)
                                    A new {{ $serviceType }} booking has been created. The operational details are below.
                                @else
                                    Thanks for booking with {{ $platformName }}. We have received your request and our team will follow up with you.
                                @endif
                            </p>

                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:16px;">
                                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:6px;">Booking reference</div>
                                <div style="font-size:20px;font-weight:700;color:#0f172a;">#{{ $booking->id }}</div>
                            </div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:16px;">
                                <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;width:38%;">Service</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;text-transform:capitalize;">{{ $serviceType }}</td></tr>
                                <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Pickup</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $booking->pickup_address }}</td></tr>
                                @if ($booking->dropoff_address)
                                    <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Drop-off</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $booking->dropoff_address }}</td></tr>
                                @endif
                                <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Pickup time</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $pickupTime }}</td></tr>
                                <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Passengers</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $booking->passengers }}</td></tr>
                                @if ($booking->vehicle)
                                    <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Vehicle</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $booking->vehicle->name }}</td></tr>
                                @endif
                                @if ($booking->driver)
                                    <tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#64748b;font-size:13px;">Driver</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;color:#0f172a;font-size:14px;font-weight:600;">{{ $booking->driver->name }}</td></tr>
                                @endif
                            </table>

                            @if ($isAdminCopy)
                                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:16px;">
                                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:6px;">Booking contact</div>
                                    <div style="font-size:14px;font-weight:600;color:#0f172a;">{{ $booking->name ?? 'Not provided' }}</div>
                                    <div style="font-size:14px;color:#334155;margin-top:4px;">{{ $booking->email ?? 'No email provided' }}{{ $booking->phone ? ' · '.$booking->phone : '' }}</div>
                                </div>
                            @endif

                            <p style="margin:0;color:#374151;">Thanks,<br><strong>{{ $platformName }}</strong></p>
                        </td>
                    </tr>
                    @if (!empty($companyEmail) || !empty($companyPhone) || !empty($companyAddress))
                        <tr>
                            <td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;">
                                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:8px;">Contact</div>
                                @if (!empty($companyEmail))<div style="font-size:14px;color:#334155;margin-bottom:4px;">Email: {{ $companyEmail }}</div>@endif
                                @if (!empty($companyPhone))<div style="font-size:14px;color:#334155;margin-bottom:4px;">Phone: {{ $companyPhone }}</div>@endif
                                @if (!empty($companyAddress))<div style="font-size:14px;color:#334155;">Address: {{ $companyAddress }}</div>@endif
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
