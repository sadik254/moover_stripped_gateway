<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Receipt #{{ $booking->id }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f4f5;color:#18181b;font-family:Arial,Helvetica,sans-serif}.receipt{max-width:760px;margin:28px auto;background:#fff;border:1px solid #e4e4e7;border-radius:14px;overflow:hidden}.header{padding:26px;background:#000;color:#fff;display:flex;justify-content:space-between;gap:20px}.brand{display:flex;align-items:center;gap:12px}.brand img{max-width:58px;max-height:58px}.brand strong{font-size:23px}.header-meta{text-align:right;font-size:13px;line-height:1.6}.content{padding:26px}.title{margin:0 0 5px;font-size:26px}.muted{color:#71717a}.badge{display:inline-block;padding:5px 9px;border-radius:20px;background:#e4e4e7;font-size:11px;font-weight:700;text-transform:uppercase}.section{margin-top:26px}.section-title{margin:0 0 12px;font-size:12px;letter-spacing:.9px;text-transform:uppercase;color:#71717a}.panel{padding:16px;border:1px solid #e4e4e7;border-radius:10px;background:#fafafa}.route-item{padding:10px 0;border-bottom:1px solid #e4e4e7}.route-item:last-child{border-bottom:0}.route-item small{display:block;margin-bottom:4px;color:#71717a}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.detail span{display:block;margin-bottom:4px;color:#71717a;font-size:11px;text-transform:uppercase;letter-spacing:.6px}.money-row{display:flex;justify-content:space-between;gap:20px;padding:8px 0;border-bottom:1px solid #e4e4e7;color:#52525b}.money-row:last-child{border-bottom:0}.money-row strong{color:#18181b}.money-row.total{margin-top:6px;padding-top:14px;border-top:2px solid #18181b;border-bottom:0;color:#18181b;font-size:19px;font-weight:700}.footer{padding:20px 26px;background:#fafafa;border-top:1px solid #e4e4e7;text-align:center;color:#71717a;font-size:13px;line-height:1.6}@media(max-width:700px){.receipt{margin:0;border-radius:0;min-height:100vh}.header{display:block}.header-meta{text-align:left;margin-top:16px}.grid{grid-template-columns:1fr}.content{padding:20px}.money-row{font-size:14px}}
    </style>
</head>
<body><main class="receipt">
    <header class="header"><div class="brand">@if ($booking->company?->logo)<img src="{{ $booking->company->logo }}" alt="Logo">@endif<strong>{{ $booking->company?->name ?? config('app.name') }}</strong></div><div class="header-meta"><strong>PAYMENT RECEIPT</strong><br>Booking #{{ $booking->id }}<br>{{ $payment?->updated_at?->format('M d, Y · g:i A') ?? now()->format('M d, Y') }}</div></header>
    <div class="content">
        <h1 class="title">{{ $currency }} {{ number_format($receiptTotal, 2) }}</h1>
        <div class="muted">Final amount charged <span class="badge">{{ str_replace('_', ' ', $payment?->status ?: $booking->payment_status ?: 'paid') }}</span></div>

        <section class="section"><h2 class="section-title">Trip details</h2><div class="panel">
            <div class="route-item"><small>Pickup · {{ \Carbon\Carbon::parse($booking->pickup_time)->format('M d, Y · g:i A') }}</small><strong>{{ $booking->pickup_address }}</strong></div>
            @foreach ($booking->stops as $stop)<div class="route-item"><small>Stop {{ $stop->position }}</small><strong>{{ $stop->address }}</strong></div>@endforeach
            @if ($booking->dropoff_address)<div class="route-item"><small>Drop-off</small><strong>{{ $booking->dropoff_address }}</strong></div>@endif
        </div></section>

        <section class="section"><h2 class="section-title">Booking information</h2><div class="panel grid">
            <div class="detail"><span>Passenger</span><strong>{{ $booking->name ?: $booking->customer?->name ?: 'Not provided' }}</strong></div>
            <div class="detail"><span>Service</span><strong>{{ ucwords(str_replace('_', ' ', $booking->service_type)) }}</strong></div>
            <div class="detail"><span>Vehicle class</span><strong>{{ $booking->vehicleClass?->name ?? 'Not provided' }}</strong></div>
            <div class="detail"><span>Trip status</span><strong>{{ ucwords(str_replace('_', ' ', $booking->status)) }}</strong></div>
            @if ($booking->distance_miles)<div class="detail"><span>Distance</span><strong>{{ number_format((float) $booking->distance_miles, 2) }} mi</strong></div>@endif
            @if ($booking->hours)<div class="detail"><span>Booked hours</span><strong>{{ number_format((float) $booking->hours, 2) }}</strong></div>@endif
            @if ($booking->airport)<div class="detail"><span>Airport</span><strong>{{ $booking->airport->code }} · {{ $booking->airport->name }}</strong></div>@endif
            @if ($booking->vehicle)<div class="detail"><span>Dispatched vehicle</span><strong>{{ $booking->vehicle->name }}{{ $booking->vehicle->plate_number ? ' · '.$booking->vehicle->plate_number : '' }}</strong></div>@endif
        </div></section>

        <section class="section"><h2 class="section-title">Fare calculation</h2><div class="panel">
            @if ((float) $booking->base_price > 0)<div class="money-row"><span>Base price</span><strong>{{ $currency }} {{ number_format((float) $booking->base_price, 2) }}</strong></div>@endif
            @if ($tripFare > 0)<div class="money-row"><span>Trip fare{{ $booking->pricing_method ? ' · '.str_replace('_', ' ', $booking->pricing_method) : '' }}</span><strong>{{ $currency }} {{ number_format($tripFare, 2) }}</strong></div>@endif
            @foreach ([
                'Extras' => $booking->extras_price,
                'Extra stops'.((int) $booking->extra_stops > 0 ? ' ('.$booking->extra_stops.')' : '') => $booking->extra_stop_amount,
                'Waiting time'.((float) $booking->waiting_minutes > 0 ? ' ('.number_format((float) $booking->waiting_minutes, 0).' min)' : '') => $booking->waiting_time_amount,
                'Parking' => $booking->parking,
                'Tolls' => $booking->tolls,
                'Airport fees' => $booking->airport_fees,
                'Congestion charge' => $booking->congestion_charge,
                'Other charges' => $booking->others,
                'Surge'.((float) $booking->surge_rate > 0 ? ' ('.number_format((float) $booking->surge_rate, 2).'%)' : '') => $booking->surge_rate_amount,
                'Tax'.((float) $booking->taxes > 0 ? ' ('.number_format((float) $booking->taxes, 2).'%)' : '') => $booking->taxes_amount,
                'Gratuity'.((float) $booking->gratuity > 0 ? ' ('.number_format((float) $booking->gratuity, 2).'%)' : '') => $booking->gratuity_amount,
                'Cancellation fee' => $booking->cancellation_fee,
            ] as $label => $amount)
                @if ((float) $amount > 0)<div class="money-row"><span>{{ $label }}</span><strong>{{ $currency }} {{ number_format((float) $amount, 2) }}</strong></div>@endif
            @endforeach
            <div class="money-row total"><span>Total charged</span><span>{{ $currency }} {{ number_format($receiptTotal, 2) }}</span></div>
        </div></section>

        <section class="section"><h2 class="section-title">Payment information</h2><div class="panel grid">
            <div class="detail"><span>Provider</span><strong>{{ ucfirst($payment?->provider ?: $booking->payment_method ?: 'Not available') }}</strong></div>
            <div class="detail"><span>Payment status</span><strong>{{ ucwords(str_replace('_', ' ', $payment?->status ?: $booking->payment_status ?: 'Not available')) }}</strong></div>
            <div class="detail"><span>Original estimate</span><strong>{{ $currency }} {{ number_format($estimatedAmount, 2) }}</strong></div>
            <div class="detail"><span>Authorization buffer{{ $authorizationBufferPercent > 0 ? ' ('.number_format($authorizationBufferPercent, 2).'%)' : '' }}</span><strong>{{ $currency }} {{ number_format($authorizationBuffer, 2) }}</strong></div>
            <div class="detail"><span>Authorized amount</span><strong>{{ $payment ? $currency.' '.number_format((float) $payment->authorized_amount, 2) : 'Not available' }}</strong></div>
            <div class="detail"><span>Captured amount</span><strong>{{ $payment?->captured_amount !== null ? $currency.' '.number_format((float) $payment->captured_amount, 2) : 'Not captured' }}</strong></div>
            <div class="detail"><span>Unused authorization released</span><strong>{{ $currency }} {{ number_format($unusedAuthorization, 2) }}</strong></div>
            @if ($payment?->payment_intent_id)<div class="detail"><span>Payment reference</span><strong>{{ $payment->payment_intent_id }}</strong></div>@endif
            <div class="detail"><span>Pricing method</span><strong>{{ $booking->pricing_method ? ucwords(str_replace('_', ' ', $booking->pricing_method)) : 'Not provided' }}</strong></div>
        </div></section>
    </div>
    <footer class="footer">Thank you for riding with {{ $booking->company?->name ?? config('app.name') }}.<br>This read-only receipt link does not require a login.</footer>
</main></body></html>
