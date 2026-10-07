<?php

namespace App\Http\Controllers;

use App\Mail\CustomerBookingCancelledMail;
use App\Models\Booking;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerReservationCancellationController extends Controller
{
    private const NOTIFICATION_EMAILS = 'reservations@squarelimo.com';

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'min:1'],
            'email' => ['required', 'email', 'max:255'],
            'trip_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $company = Company::first();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::with(['company', 'customer', 'vehicleClass', 'stops'])
            ->where('company_id', $company->id)
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

        if ((string) $booking->status === 'cancelled') {
            return response()->json(['message' => 'This booking is already cancelled'], 422);
        }

        if (in_array((string) $booking->status, ['completed', 'done'], true)) {
            return response()->json(['message' => 'This booking can no longer be cancelled'], 422);
        }

        $eligibilityError = $this->cancellationEligibilityError($booking);
        if ($eligibilityError) {
            return response()->json(['message' => $eligibilityError], 422);
        }

        $previousStatus = (string) $booking->status;
        $booking->status = 'cancelled';
        $booking->saveQuietly();
        $booking->refresh()->loadMissing(['company', 'customer', 'vehicleClass', 'stops']);

        $this->sendCancellationEmails($booking, $previousStatus);

        return response()->json([
            'message' => 'Your reservation has been cancelled successfully.',
            'data' => ['booking_id' => $booking->id, 'status' => $booking->status],
        ]);
    }

    private function cancellationEligibilityError(Booking $booking): ?string
    {
        $serviceType = (string) $booking->service_type;
        if ($serviceType === 'airport') {
            return 'Airport bookings cannot be cancelled through Manage Reservation. Please contact us for assistance.';
        }

        $vehicleName = mb_strtolower((string) ($booking->vehicleClass?->name ?? ''));
        $minimumNotice = (str_contains($vehicleName, 'limo') || str_contains($vehicleName, 'sprinter'))
            ? 7 * 24
            : match ($serviceType) {
                'hourly' => 48,
                'point_to_point' => 4,
                default => null,
            };

        if ($minimumNotice === null) {
            return 'This booking cannot be cancelled through Manage Reservation. Please contact us for assistance.';
        }

        if (Carbon::parse($booking->pickup_time)->lessThanOrEqualTo(now()->addHours($minimumNotice))) {
            $notice = $minimumNotice === 168 ? '7 days' : "{$minimumNotice} hours";
            return "This booking can only be cancelled more than {$notice} before pickup.";
        }

        return null;
    }

    private function sendCancellationEmails(Booking $booking, string $previousStatus): void
    {
        $customerEmail = $booking->email ?: $booking->customer?->email;

        foreach ([
            ['recipient' => $customerEmail, 'isAdminCopy' => false],
            ['recipient' => self::NOTIFICATION_EMAILS, 'isAdminCopy' => true],
        ] as $delivery) {
            if (! $delivery['recipient']) {
                continue;
            }

            try {
                Mail::to($delivery['recipient'])->send(new CustomerBookingCancelledMail(
                    booking: $booking,
                    previousStatus: $previousStatus,
                    isAdminCopy: $delivery['isAdminCopy'],
                ));
            } catch (\Throwable $exception) {
                Log::warning('Customer booking cancellation email failed', [
                    'booking_id' => $booking->id,
                    'email' => $delivery['recipient'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
