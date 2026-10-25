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

        // A redispatch may use the same driver and vehicle, in which case only
        // the status changes. Every entry into dispatched must issue a fresh
        // one-time link and revoke the previous active driver link.
        if ($freshBooking->status === 'dispatched' && ($assignmentChanged || $statusChanged)) {
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

        if ($statusChanged && $freshBooking->status === 'on_route') {
            if ($customerEmail) {
                $this->send($customerEmail, new BookingDriverAssignedMail($freshBooking), $booking->id);
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

        if ($previousStatus === 'on_route' && $freshBooking->status === 'on_location') {
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

        // Statuses are operational updates. Customers only need a completion
        // notice after the trip has been finalized; the arrival notification
        // above already covers the pickup milestone.
        if ($freshBooking->status === 'completed') {
            $this->send($customerEmail, new BookingStatusChangedMail($freshBooking, $previousStatus), $booking->id);
        }
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
