<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quick Receipt #{{ $booking->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .page { padding: 35px 39px 28px; }
        .header { border-bottom: 1px solid #dbe1e8; padding-bottom: 25px; }
        .company { float: left; width: 62%; }
        .company h1 { margin: 0 0 10px; font-size: 23px; font-weight: 700; }
        .company p, .receipt-meta p { margin: 0 0 5px; color: #95a1b4; font-size: 12px; line-height: 1.45; }
        .receipt-meta { float: right; width: 38%; text-align: right; }
        .receipt-meta strong { display: block; margin: 4px 0 8px; font-size: 15px; }
        .receipt-meta .label, .section-title { color: #98a4b5; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .clear { clear: both; }
        .booking-id { margin: 30px 0 31px; padding: 13px 15px; border-radius: 7px; background: #f0f4f8; }
        .booking-id span { color: #8794a6; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .booking-id strong { float: right; font-size: 13px; }
        .section-title { margin: 0 0 18px; }
        .trip-row { margin: 0 0 18px; padding-left: 28px; position: relative; }
        .trip-row .dot { position: absolute; top: 1px; left: 0; width: 15px; height: 15px; border: 1px solid #172033; border-radius: 50%; text-align: center; line-height: 12px; }
        .trip-row .dot:after { content: ''; display: inline-block; width: 3px; height: 3px; border-radius: 50%; background: #172033; vertical-align: middle; }
        .trip-row strong { display: block; margin-bottom: 4px; font-size: 13px; }
        .trip-row p { margin: 0; color: #98a4b5; font-size: 12px; line-height: 1.45; }
        .vehicle { margin: 23px 0 30px; padding: 14px 15px; border-radius: 7px; background: #f0f4f8; font-size: 12px; }
        .vehicle span { color: #8794a6; }
        .passenger { margin: 0 0 30px; padding: 18px; border-radius: 7px; background: #f0f4f8; }
        .passenger h2 { margin: 0 0 17px; color: #8794a6; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .detail { float: left; width: 50%; min-height: 48px; }
        .detail-wide { width: 100%; }
        .detail span { display: block; margin-bottom: 6px; color: #98a4b5; font-size: 10px; letter-spacing: .7px; text-transform: uppercase; }
        .detail strong { font-size: 12px; font-weight: 500; }
        .notice { margin-top: 4px; padding: 15px; border: 1px solid #dbe1e8; border-radius: 7px; color: #66758a; font-size: 11px; line-height: 1.55; }
        .footer { margin-top: 34px; padding-top: 20px; border-top: 1px solid #dbe1e8; color: #98a4b5; font-size: 11px; text-align: center; }
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
        @if ($booking->dropoff_address)
            <div class="trip-row">
                <span class="dot"></span>
                <strong>{{ $booking->dropoff_time ? \Carbon\Carbon::parse($booking->dropoff_time)->format('M d, g:i A') : 'Drop-off location' }}</strong>
                <p>{{ $booking->dropoff_address }}</p>
            </div>
        @endif

        @if ($booking->vehicleClass)
            <div class="vehicle"><span>Vehicle class: </span>{{ $booking->vehicleClass->name }}</div>
        @endif

        <div class="passenger">
            <h2>Passenger Details</h2>
            <div class="detail"><span>Name</span><strong>{{ $passengerName }}</strong></div>
            <div class="detail"><span>Phone</span><strong>{{ $passengerPhone ?: 'Not provided' }}</strong></div>
            <div class="clear"></div>
            <div class="detail detail-wide"><span>Email</span><strong>{{ $passengerEmail ?: 'Not provided' }}</strong></div>
            <div class="clear"></div>
        </div>

        <div class="notice">
            This is a booking receipt for operational reference. It confirms that the trip request was recorded and does not contain payment or pricing information.
        </div>

        <div class="footer">Thank you for riding with {{ $booking->company?->name ?? config('app.name', 'Moover') }}.</div>
    </div>
</body>
</html>
