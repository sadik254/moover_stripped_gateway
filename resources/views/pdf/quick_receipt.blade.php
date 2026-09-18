<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quick Receipt #{{ $booking->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .page { padding: 25px 39px 20px; }
        .header { border-bottom: 1px solid #dbe1e8; padding-bottom: 17px; }
        .company { float: left; width: 62%; }
        .company h1 { margin: 0 0 10px; font-size: 23px; font-weight: 700; }
        .company p, .receipt-meta p { margin: 0 0 5px; color: #95a1b4; font-size: 12px; line-height: 1.45; }
        .receipt-meta { float: right; width: 38%; text-align: right; }
        .receipt-meta strong { display: block; margin: 4px 0 8px; font-size: 15px; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 10px; background: #e5eaf0; color: #425168; font-size: 9px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; }
        .receipt-meta .label, .section-title { color: #98a4b5; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .clear { clear: both; }
        .booking-id { margin: 18px 0 20px; padding: 11px 15px; border-radius: 7px; background: #f0f4f8; }
        .booking-id span { color: #8794a6; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .booking-id strong { float: right; font-size: 13px; }
        .section-title { margin: 0 0 11px; }
        .trip-row { margin: 0 0 12px; padding-left: 28px; position: relative; }
        .trip-row .dot { position: absolute; top: 1px; left: 0; width: 15px; height: 15px; border: 1px solid #172033; border-radius: 50%; text-align: center; line-height: 12px; }
        .trip-row .dot:after { content: ''; display: inline-block; width: 3px; height: 3px; border-radius: 50%; background: #172033; vertical-align: middle; }
        .trip-row strong { display: block; margin-bottom: 4px; font-size: 13px; }
        .trip-row p { margin: 0; color: #98a4b5; font-size: 12px; line-height: 1.45; }
        .vehicle { margin: 14px 0 18px; padding: 11px 15px; border-radius: 7px; background: #f0f4f8; font-size: 12px; }
        .vehicle span { color: #8794a6; }
        .passenger { margin: 0 0 18px; padding: 14px 18px; border-radius: 7px; background: #f0f4f8; }
        .passenger h2 { margin: 0 0 11px; color: #8794a6; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .detail { float: left; width: 50%; min-height: 40px; }
        .detail-wide { width: 100%; }
        .detail span { display: block; margin-bottom: 6px; color: #98a4b5; font-size: 10px; letter-spacing: .7px; text-transform: uppercase; }
        .detail strong { font-size: 12px; font-weight: 500; }
        .summary { margin: 0 0 16px; padding: 11px 14px; border: 1px solid #dbe1e8; border-radius: 7px; }
        .money-row { padding: 3px 0; color: #66758a; }
        .money-row .amount { float: right; color: #172033; }
        .money-row.total { margin-top: 7px; padding-top: 11px; border-top: 1px solid #dbe1e8; color: #172033; font-size: 14px; font-weight: 700; }
        .payment-grid { margin-bottom: 14px; padding: 12px 18px 3px; border-radius: 7px; background: #f0f4f8; }
        .footer { position: fixed; right: 39px; bottom: 18px; left: 39px; padding-top: 10px; border-top: 1px solid #dbe1e8; color: #98a4b5; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="company">
                <h1>{{ $booking->company?->name ?? config('app.name', 'Moover') }}</h1>
                @if ($booking->company?->email)<p>{{ $booking->company->email }}</p>@endif
                @if ($booking->company?->phone)<p>{{ $booking->company->phone }}</p>@endif
            </div>
            <div class="receipt-meta">
                <div class="label">Quick Receipt</div>
                <strong>{{ \Carbon\Carbon::parse($booking->pickup_time)->format('M d, Y') }}</strong>
                <p>Booking ID: {{ $booking->id }}</p>
                <span class="status">{{ str_replace('_', ' ', $booking->status ?: 'pending') }}</span>
            </div>
            <div class="clear"></div>
        </div>

        <div class="booking-id"><span>Booking ID</span><strong>{{ $booking->id }}</strong><div class="clear"></div></div>

        <div class="section-title">Trip Summary</div>
        <div class="trip-row">
            <span class="dot"></span>
            <strong>{{ \Carbon\Carbon::parse($booking->pickup_time)->format('M d, g:i A') }}</strong>
            <p>{{ $booking->pickup_address }}</p>
        </div>
        @foreach ($booking->stops as $stop)
            <div class="trip-row">
                <span class="dot"></span>
                <strong>Stop {{ $stop->position }}</strong>
                <p>{{ $stop->address }}</p>
            </div>
        @endforeach
        @if ($booking->dropoff_address)
            <div class="trip-row">
                <span class="dot"></span>
                <strong>{{ $booking->dropoff_time ? \Carbon\Carbon::parse($booking->dropoff_time)->format('M d, g:i A') : 'Drop-off location' }}</strong>
                <p>{{ $booking->dropoff_address }}</p>
            </div>
        @endif

        @if ($booking->vehicleClass)
            <div class="vehicle">
                <span>Vehicle class: </span>{{ $booking->vehicleClass->name }}
                <span style="float: right;">{{ ucwords(str_replace('_', ' ', $booking->service_type)) }}</span>
                <div class="clear"></div>
                @if ($booking->airport)
                    <span>Airport: {{ $booking->airport->code }} — {{ $booking->airport->name }}</span>
                @endif
            </div>
        @endif

        <div class="passenger">
            <h2>Passenger Details</h2>
            <div class="detail"><span>Name</span><strong>{{ $passengerName }}</strong></div>
            <div class="detail"><span>Phone</span><strong>{{ $passengerPhone ?: 'Not provided' }}</strong></div>
            <div class="clear"></div>
            <div class="detail detail-wide"><span>Email</span><strong>{{ $passengerEmail ?: 'Not provided' }}</strong></div>
            <div class="clear"></div>
        </div>

        <div class="section-title">Fare Calculation</div>
        <div class="summary">
            @if ((float) $booking->base_price > 0)
                <div class="money-row"><span>Base price</span><span class="amount">{{ $currency }} {{ number_format((float) $booking->base_price, 2) }}</span><div class="clear"></div></div>
            @endif
            @if ($tripFare > 0)
                <div class="money-row">
                    <span>Trip fare{{ $booking->pricing_method ? ' · '.str_replace('_', ' ', $booking->pricing_method) : '' }}</span>
                    <span class="amount">{{ $currency }} {{ number_format($tripFare, 2) }}</span>
                    <div class="clear"></div>
                </div>
            @endif
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
                @if ((float) $amount > 0)
                    <div class="money-row"><span>{{ $label }}</span><span class="amount">{{ $currency }} {{ number_format((float) $amount, 2) }}</span><div class="clear"></div></div>
                @endif
            @endforeach
            <div class="money-row total"><span>Final amount</span><span class="amount">{{ $currency }} {{ number_format($receiptTotal, 2) }}</span><div class="clear"></div></div>
        </div>

        <div class="section-title">Payment Information</div>
        <div class="payment-grid">
            <div class="detail"><span>Payment status</span><strong>{{ ucwords(str_replace('_', ' ', $payment?->status ?: $booking->payment_status ?: 'Not paid')) }}</strong></div>
            <div class="detail"><span>Provider</span><strong>{{ ucfirst($payment?->provider ?: $booking->payment_method ?: 'Not available') }}</strong></div>
            <div class="clear"></div>
            <div class="detail"><span>Original estimate</span><strong>{{ $currency }} {{ number_format($estimatedAmount, 2) }}</strong></div>
            <div class="detail"><span>Authorization buffer{{ $authorizationBufferPercent > 0 ? ' ('.number_format($authorizationBufferPercent, 2).'%)' : '' }}</span><strong>{{ $currency }} {{ number_format($authorizationBuffer, 2) }}</strong></div>
            <div class="clear"></div>
            <div class="detail"><span>Authorized</span><strong>{{ $payment ? $currency.' '.number_format((float) $payment->authorized_amount, 2) : 'Not available' }}</strong></div>
            <div class="detail"><span>Captured</span><strong>{{ $payment && $payment->captured_amount !== null ? $currency.' '.number_format((float) $payment->captured_amount, 2) : 'Not captured' }}</strong></div>
            <div class="clear"></div>
            <div class="detail detail-wide"><span>Unused authorization released</span><strong>{{ $currency }} {{ number_format($unusedAuthorization, 2) }}</strong></div>
            <div class="clear"></div>
            @if ($payment?->payment_intent_id)
                <div class="detail detail-wide"><span>Payment reference</span><strong>{{ $payment->payment_intent_id }}</strong></div>
                <div class="clear"></div>
            @endif
        </div>

        <div class="footer">Thank you for riding with {{ $booking->company?->name ?? config('app.name', 'Moover') }}. Payment status reflects the latest available payment record.</div>
    </div>
</body>
</html>
