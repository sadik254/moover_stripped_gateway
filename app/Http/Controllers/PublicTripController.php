<?php

namespace App\Http\Controllers;

use App\Events\BookingLocationUpdated;
use App\Events\PublicBookingLocationUpdated;
use App\Models\Booking;
use App\Models\BookingAccessLink;
use App\Models\BookingLiveLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicTripController extends Controller
{
    public function driverPage(string $token)
    {
        [$link, $booking] = $this->resolveDriver($token);
        abort_unless($link && $booking, 404);

        return view('public.driver_trip', [
            'token' => $token,
            'booking' => $booking,
            'company' => $booking->company,
        ]);
    }

    public function driverData(string $token)
    {
        [$link, $booking] = $this->resolveDriver($token);
        if (! $link || ! $booking) {
            return response()->json(['message' => 'This driver link is invalid or expired'], 404);
        }

        return response()->json(['data' => $this->driverPayload($booking)]);
    }

    public function updateDriverStatus(Request $request, string $token)
    {
        [$link, $booking] = $this->resolveDriver($token);
        if (! $link || ! $booking) {
            return response()->json(['message' => 'This driver link is invalid or expired'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:picking_up,on_route,done',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $transitions = ['assigned' => 'picking_up', 'picking_up' => 'on_route', 'on_route' => 'done'];
        $expected = $transitions[(string) $booking->status] ?? null;
        if ($expected === null || $expected !== $request->status) {
            return response()->json([
                'message' => $expected ? "Booking must move to {$expected} next" : 'This trip can no longer be updated',
                'data' => ['current_status' => $booking->status, 'expected_status' => $expected],
            ], 422);
        }

        $booking->status = $request->status;
        $booking->save();

        if ($booking->status === 'done') {
            $link->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'Booking status updated', 'data' => $this->driverPayload($booking->fresh())]);
    }

    public function updateLocation(Request $request, string $token)
    {
        [$link, $booking] = $this->resolveDriver($token);
        if (! $link || ! $booking) {
            return response()->json(['message' => 'This driver link is invalid or expired'], 404);
        }
        if (! in_array((string) $booking->status, ['picking_up', 'on_route'], true)) {
            return response()->json(['message' => 'Location sharing is available during picking_up and on_route'], 422);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading' => 'sometimes|nullable|integer|min:0|max:360',
            'speed' => 'sometimes|nullable|numeric|min:0|max:1000',
            'accuracy' => 'sometimes|nullable|numeric|min:0|max:10000',
            'recorded_at' => 'sometimes|nullable|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $location = BookingLiveLocation::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'driver_id' => $booking->driver_id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'heading' => $request->heading,
                'speed' => $request->speed,
                'accuracy' => $request->accuracy,
                'recorded_at' => $request->recorded_at ? Carbon::parse($request->recorded_at) : now(),
            ]
        );
        $payload = $this->locationPayload($booking, $location);
        broadcast(new BookingLocationUpdated($booking->id, $payload))->toOthers();

        $trackingLink = BookingAccessLink::active()
            ->where('booking_id', $booking->id)
            ->where('type', BookingAccessLink::CUSTOMER)
            ->first();
        if ($trackingLink?->channel_key) {
            broadcast(new PublicBookingLocationUpdated($trackingLink->channel_key, $payload))->toOthers();
        }

        return response()->json(['message' => 'Location updated', 'data' => $payload]);
    }

    public function trackingPage(string $token)
    {
        [$link, $booking] = $this->resolveCustomer($token);
        abort_unless($link && $booking, 404);

        return view('public.customer_tracking', [
            'token' => $token,
            'booking' => $booking,
            'company' => $booking->company,
            'channelKey' => $link->channel_key,
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ]);
    }

    public function trackingData(string $token)
    {
        [$link, $booking] = $this->resolveCustomer($token);
        if (! $link || ! $booking) {
            return response()->json(['message' => 'This tracking link is invalid or expired'], 404);
        }

        return response()->json(['data' => $this->trackingPayload($booking)]);
    }

    public function receiptPage(string $token)
    {
        $link = BookingAccessLink::resolveToken($token, BookingAccessLink::RECEIPT);
        $booking = $link?->booking?->load(['company', 'customer', 'vehicleClass', 'airport', 'driver', 'vehicle', 'latestPayment', 'stops']);
        abort_unless($link && $booking, 404);

        $payment = $booking->latestPayment;
        $receiptTotal = (float) ($payment?->captured_amount ?? $booking->final_price ?? $booking->total_price ?? 0);
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

        return view('public.trip_receipt', [
            'booking' => $booking,
            'payment' => $payment,
            'currency' => strtoupper((string) ($payment?->currency ?: 'USD')),
            'receiptTotal' => $receiptTotal,
            'tripFare' => $tripFare,
            'estimatedAmount' => $estimatedAmount,
            'authorizationBuffer' => $authorizationBuffer,
            'authorizationBufferPercent' => $authorizationBufferPercent,
            'unusedAuthorization' => $unusedAuthorization,
        ]);
    }

    private function resolveDriver(string $token): array
    {
        $link = BookingAccessLink::resolveToken($token, BookingAccessLink::DRIVER);
        $booking = $link?->booking?->load(['company', 'driver', 'vehicle', 'vehicleClass', 'stops']);
        if ($link && $booking && (int) $booking->driver_id !== (int) $link->driver_id) {
            return [null, null];
        }

        return [$link, $booking];
    }

    private function resolveCustomer(string $token): array
    {
        $link = BookingAccessLink::resolveToken($token, BookingAccessLink::CUSTOMER);
        $booking = $link?->booking?->load(['company', 'driver', 'vehicle', 'vehicleClass', 'stops']);

        return [$link, $booking];
    }

    private function driverPayload(Booking $booking): array
    {
        $next = ['assigned' => 'picking_up', 'picking_up' => 'on_route', 'on_route' => 'done'][$booking->status] ?? null;

        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'next_status' => $next,
            'pickup_address' => $booking->pickup_address,
            'dropoff_address' => $booking->dropoff_address,
            'pickup_time' => $booking->pickup_time,
            'passenger' => ['name' => $booking->name, 'phone' => $booking->phone],
            'stops' => $booking->stops,
            'vehicle' => $booking->vehicle,
        ];
    }

    private function trackingPayload(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'driver' => $booking->driver ? $booking->driver->only(['name', 'phone', 'photo']) : null,
            'vehicle' => $booking->vehicle?->only(['name', 'model', 'color', 'plate_number', 'image']),
            'location' => BookingLiveLocation::where('booking_id', $booking->id)->first(),
        ];
    }

    private function locationPayload(Booking $booking, BookingLiveLocation $location): array
    {
        return [
            'booking_id' => $booking->id,
            'driver_id' => $booking->driver_id,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'heading' => $location->heading,
            'speed' => $location->speed,
            'accuracy' => $location->accuracy,
            'recorded_at' => $location->recorded_at?->toISOString(),
        ];
    }
}
