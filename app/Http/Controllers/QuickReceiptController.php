<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
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

        $booking = Booking::with(['company', 'customer', 'vehicleClass', 'airport', 'driver', 'latestPayment', 'payments'])
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

        return $this->streamReceipt($booking);
    }

    public function downloadForAuthenticatedCustomer(Request $request, Booking $booking)
    {
        $customer = $request->user();
        if (! $customer instanceof Customer || (int) $booking->customer_id !== (int) $customer->id) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->loadMissing(['company', 'customer', 'vehicleClass', 'airport', 'driver', 'latestPayment', 'payments']);

        return $this->streamReceipt($booking);
    }

    private function streamReceipt(Booking $booking)
    {
        $latestPayment = $booking->latestPayment;
        $payment = $booking->payments->first(fn ($item) => in_array((string) $item->payment_kind, ['', 'authorization'], true)) ?: $latestPayment;
        $initialCapturedAmount = (float) $booking->payments
            ->where('status', 'succeeded')
            ->filter(fn ($item) => in_array((string) $item->payment_kind, ['', 'authorization'], true))
            ->sum('captured_amount');
        $postTripAdjustmentAmount = (float) $booking->payments
            ->where('status', 'succeeded')
            ->where('payment_kind', 'post_trip_adjustment')
            ->sum('captured_amount');
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
            'latestPayment' => $latestPayment,
            'currency' => strtoupper((string) ($payment?->currency ?: 'USD')),
            'receiptTotal' => $receiptTotal,
            'tripFare' => $tripFare,
            'estimatedAmount' => $estimatedAmount,
            'authorizationBuffer' => $authorizationBuffer,
            'authorizationBufferPercent' => $authorizationBufferPercent,
            'unusedAuthorization' => $unusedAuthorization,
            'initialCapturedAmount' => $initialCapturedAmount,
            'postTripAdjustmentAmount' => $postTripAdjustmentAmount,
            'totalPaidAmount' => $initialCapturedAmount + $postTripAdjustmentAmount,
            'passengerName' => $booking->name ?: $booking->customer?->name ?: 'Passenger',
            'passengerEmail' => $booking->email ?: $booking->customer?->email,
            'passengerPhone' => $booking->phone ?: $booking->customer?->phone,
        ])
            ->setPaper('a4')
            ->stream("quick-receipt-{$booking->id}.pdf");
    }
}
