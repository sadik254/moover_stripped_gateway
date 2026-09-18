<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px 12px;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <tr><td style="padding:24px;background:#000000;color:#ffffff;">
                    @if ($booking->company?->logo)<img src="{{ $booking->company->logo }}" alt="{{ $booking->company?->name }} logo" style="max-height:44px;display:block;margin-bottom:12px;">@endif
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.8;">Booking #{{ $booking->id }}</div>
                    <div style="margin-top:6px;font-size:24px;font-weight:700;">Your driver is assigned</div>
                </td></tr>
                <tr><td style="padding:24px;">
                    <p style="margin:0 0 18px;line-height:1.6;">Hi {{ $booking->name ?: $booking->customer?->name ?: 'there' }}, your driver details are ready.</p>
                    <div style="padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px;">
                        @if ($booking->driver?->photo)<img src="{{ $booking->driver->photo }}" alt="Driver" style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin-bottom:12px;">@endif
                        <div style="font-size:18px;font-weight:700;">{{ $booking->driver?->name ?? 'Assigned driver' }}</div>
                        @if ($booking->driver?->phone)<div style="margin-top:5px;color:#475569;">Phone: {{ $booking->driver->phone }}</div>@endif
                    </div>
                    @if ($booking->vehicle)
                        <div style="padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#64748b;margin-bottom:8px;">Dispatch vehicle</div>
                            @if ($booking->vehicle->image)<img src="{{ $booking->vehicle->image }}" alt="Vehicle" style="max-width:180px;max-height:100px;object-fit:cover;border-radius:8px;margin-bottom:10px;">@endif
                            <div style="font-size:16px;font-weight:700;">{{ $booking->vehicle->name }}</div>
                            <div style="margin-top:5px;color:#475569;">{{ collect([$booking->vehicle->color, $booking->vehicle->model, $booking->vehicle->plate_number])->filter()->join(' · ') }}</div>
                        </div>
                    @endif
                    <div style="color:#475569;line-height:1.6;"><strong>Pickup:</strong> {{ $booking->pickup_address }}<br><strong>Pickup time:</strong> {{ \Carbon\Carbon::parse($booking->pickup_time)->format('D, d M Y \a\t h:i A') }}</div>
                    <p style="margin:20px 0 0;color:#64748b;">Thank you for riding with {{ $booking->company?->name ?? config('app.name', 'Moover') }}.</p>
                </td></tr>
                @if ($booking->company?->email || $booking->company?->phone || $booking->company?->address)
                    <tr><td style="padding:20px 24px;background:#fafafa;border-top:1px solid #e4e4e7;">
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#71717a;margin-bottom:8px;">Contact</div>
                        @if ($booking->company?->email)<div style="font-size:14px;color:#3f3f46;margin-bottom:4px;">Email: {{ $booking->company->email }}</div>@endif
                        @if ($booking->company?->phone)<div style="font-size:14px;color:#3f3f46;margin-bottom:4px;">Phone: {{ $booking->company->phone }}</div>@endif
                        @if ($booking->company?->address)<div style="font-size:14px;color:#3f3f46;">Address: {{ $booking->company->address }}</div>@endif
                    </td></tr>
                @endif
            </table>
        </td></tr>
    </table>
</body>
</html>
