<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px 12px;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <tr><td style="padding:24px;background:#0f172a;color:#ffffff;">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.8;">Booking update</div>
                    <div style="margin-top:6px;font-size:24px;font-weight:700;">{{ ucwords(str_replace('_', ' ', $booking->status)) }}</div>
                </td></tr>
                <tr><td style="padding:24px;">
                    <p style="margin:0 0 18px;line-height:1.6;">Hi {{ $booking->name ?: $booking->customer?->name ?: 'there' }}, the status of booking #{{ $booking->id }} changed from <strong>{{ ucwords(str_replace('_', ' ', $previousStatus)) }}</strong> to <strong>{{ ucwords(str_replace('_', ' ', $booking->status)) }}</strong>.</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                        <tr><td style="padding:9px 0;border-bottom:1px solid #e5e7eb;color:#64748b;width:34%;">Pickup</td><td style="padding:9px 0;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ $booking->pickup_address }}</td></tr>
                        @foreach ($booking->stops as $stop)
                            <tr><td style="padding:9px 0;border-bottom:1px solid #e5e7eb;color:#64748b;">Stop {{ $stop->position }}</td><td style="padding:9px 0;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ $stop->address }}</td></tr>
                        @endforeach
                        @if ($booking->dropoff_address)<tr><td style="padding:9px 0;border-bottom:1px solid #e5e7eb;color:#64748b;">Drop-off</td><td style="padding:9px 0;border-bottom:1px solid #e5e7eb;font-weight:600;">{{ $booking->dropoff_address }}</td></tr>@endif
                        <tr><td style="padding:9px 0;color:#64748b;">Pickup time</td><td style="padding:9px 0;font-weight:600;">{{ \Carbon\Carbon::parse($booking->pickup_time)->format('D, d M Y \a\t h:i A') }}</td></tr>
                    </table>
                    <p style="margin:20px 0 0;color:#64748b;">Thank you for riding with {{ $booking->company?->name ?? config('app.name', 'Moover') }}.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
