<?php

namespace App\Observers;

use App\Mail\BookingDriverAssignedMail;
use App\Mail\BookingStatusChangedMail;
use App\Mail\BookingTrackingStartedMail;
use App\Mail\DriverTripAccessMail;
use App\Models\Booking;
use App\Models\BookingAccessLink;
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

        if (! $freshBooking) {
            return;
        }

        $customerEmail = $freshBooking->email ?: $freshBooking->customer?->email;

        if ($assignmentChanged) {
            if ($customerEmail) {
                $this->send($customerEmail, new BookingDriverAssignedMail($freshBooking), $booking->id);
            }

            if ($freshBooking->driver?->email) {
                [, $token] = BookingAccessLink::issue(
                    $freshBooking,
                    BookingAccessLink::DRIVER,
                    $freshBooking->driver_id,
                    now()->addDays(7)
                );
                $this->send(
                    $freshBooking->driver->email,
                    new DriverTripAccessMail($freshBooking, route('public.driver-trip', ['token' => $token])),
                    $booking->id
                );
            }

            return;
        }

        if (in_array((string) $freshBooking->status, ['done', 'completed', 'cancelled'], true)) {
            BookingAccessLink::where('booking_id', $freshBooking->id)
                ->where('type', BookingAccessLink::DRIVER)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        if (! $customerEmail) {
            return;
        }

        if ($previousStatus === 'picking_up' && $freshBooking->status === 'on_route') {
            [, $token] = BookingAccessLink::issue(
                $freshBooking,
                BookingAccessLink::CUSTOMER,
                null,
                now()->addDays(2)
            );
            $this->send(
                $customerEmail,
                new BookingTrackingStartedMail($freshBooking, route('public.track-trip', ['token' => $token])),
                $booking->id
            );

            return;
        }

        $this->send($customerEmail, new BookingStatusChangedMail($freshBooking, $previousStatus), $booking->id);
    }

    private function send(string $email, object $mailable, int $bookingId): void
    {
        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $exception) {
            Log::warning('Booking access or update email failed', [
                'booking_id' => $bookingId,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
