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

        $booking = Booking::with(['company', 'customer', 'vehicleClass', 'driver'])
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

        return Pdf::loadView('pdf.quick_receipt', [
            'booking' => $booking,
            'passengerName' => $booking->name ?: $booking->customer?->name ?: 'Passenger',
            'passengerEmail' => $booking->email ?: $booking->customer?->email,
            'passengerPhone' => $booking->phone ?: $booking->customer?->phone,
        ])
            ->setPaper('a4')
            ->stream("quick-receipt-{$booking->id}.pdf");
    }
}
