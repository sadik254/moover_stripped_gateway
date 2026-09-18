<?php

namespace App\Observers;

use App\Mail\BookingDriverAssignedMail;
use App\Mail\BookingStatusChangedMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingObserver
{
    public function updated(Booking $booking): void
    {
        $assignmentChanged = ($booking->wasChanged('driver_id') || $booking->wasChanged('vehicle_id'))
            && $booking->driver_id !== null;
        $statusChanged = $booking->wasChanged('status');

        if (! $assignmentChanged && ! $statusChanged) {
            return;
        }

        $previousStatus = (string) ($booking->getOriginal('status') ?: 'pending');
        $freshBooking = Booking::with(['company', 'customer', 'driver', 'vehicle', 'vehicleClass', 'stops'])
            ->find($booking->id);
        $email = $freshBooking?->email ?: $freshBooking?->customer?->email;

        if (! $freshBooking || ! $email) {
            return;
        }

        try {
            $mail = $assignmentChanged
                ? new BookingDriverAssignedMail($freshBooking)
                : new BookingStatusChangedMail($freshBooking, $previousStatus);
            Mail::to($email)->send($mail);
        } catch (\Throwable $exception) {
            Log::warning('Booking customer update email failed', [
                'booking_id' => $booking->id,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
