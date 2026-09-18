<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuickReceiptController extends Controller
{
    public function download(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'min:1'],
            'email' => ['required', 'email', 'max:255'],
            'trip_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $booking = Booking::with(['company', 'customer', 'vehicleClass', 'airport', 'driver', 'latestPayment'])
            ->whereKey($validated['booking_id'])
            ->whereDate('pickup_time', $validated['trip_date'])
            ->where(function ($query) use ($validated): void {
                $query->where('email', $validated['email'])
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('email', $validated['email']));
            })
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $payment = $booking->latestPayment;
        $receiptTotal = (float) ($booking->final_price ?? $booking->total_price ?? 0);
        $subtotal = max(0, $receiptTotal
            - (float) ($booking->taxes_amount ?? 0)
            - (float) ($booking->gratuity_amount ?? 0)
            - (float) ($booking->surge_rate_amount ?? 0)
            - (float) ($booking->cancellation_fee ?? 0));
        $tripFare = max(0, $subtotal
            - (float) ($booking->base_price ?? 0)
            - (float) ($booking->extras_price ?? 0)
            - (float) ($booking->parking ?? 0)
            - (float) ($booking->others ?? 0)
            - (float) ($booking->airport_fees ?? 0)
            - (float) ($booking->congestion_charge ?? 0)
            - (float) ($booking->tolls ?? 0)
            - (float) ($booking->extra_stop_amount ?? 0)
            - (float) ($booking->waiting_time_amount ?? 0));
        $estimatedAmount = (float) ($payment?->estimated_amount ?? $booking->total_price ?? 0);
        $authorizedAmount = (float) ($payment?->authorized_amount ?? 0);
        $authorizationBuffer = max(0, $authorizedAmount - $estimatedAmount);
        $authorizationBufferPercent = $estimatedAmount > 0
            ? round(($authorizationBuffer / $estimatedAmount) * 100, 2)
            : 0;
        $unusedAuthorization = max(0, $authorizedAmount - $receiptTotal);

        return Pdf::loadView('pdf.quick_receipt', [
            'booking' => $booking,
            'payment' => $payment,
            'currency' => strtoupper((string) ($payment?->currency ?: 'USD')),
            'receiptTotal' => $receiptTotal,
            'tripFare' => $tripFare,
            'estimatedAmount' => $estimatedAmount,
            'authorizationBuffer' => $authorizationBuffer,
            'authorizationBufferPercent' => $authorizationBufferPercent,
            'unusedAuthorization' => $unusedAuthorization,
            'passengerName' => $booking->name ?: $booking->customer?->name ?: 'Passenger',
            'passengerEmail' => $booking->email ?: $booking->customer?->email,
            'passengerPhone' => $booking->phone ?: $booking->customer?->phone,
        ])
            ->setPaper('a4')
            ->stream("quick-receipt-{$booking->id}.pdf");
    }
}
