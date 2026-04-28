<?php

namespace App\Http\Controllers;

use App\Events\BookingLocationUpdated;
use App\Models\Booking;
use App\Models\BookingLiveLocation;
use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingLiveLocationController extends Controller
{
    public function updateFromDriver(Request $request, $bookingId)
    {
        $driver = $request->user();
        if (! $driver instanceof Driver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $booking = Booking::where('id', $bookingId)
            ->where('driver_id', $driver->id)
            ->where('company_id', $driver->company_id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ((string) $booking->status !== 'on_route') {
            return response()->json([
                'message' => 'Live tracking is only allowed when booking status is on_route',
            ], 422);
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
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $location = BookingLiveLocation::updateOrCreate(
            ['booking_id' => (int) $booking->id],
            [
                'driver_id' => (int) $driver->id,
                'latitude' => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
                'heading' => $request->heading,
                'speed' => $request->speed,
                'accuracy' => $request->accuracy,
                'recorded_at' => $request->recorded_at ? Carbon::parse($request->recorded_at) : now(),
            ]
        );

        broadcast(new BookingLocationUpdated((int) $booking->id, [
            'booking_id' => (int) $booking->id,
            'driver_id' => (int) $driver->id,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'heading' => $location->heading,
            'speed' => $location->speed,
            'accuracy' => $location->accuracy,
            'recorded_at' => optional($location->recorded_at)->toISOString(),
        ]))->toOthers();

        return response()->json([
            'message' => 'Location updated',
            'data' => [
                'booking_id' => (int) $booking->id,
                'recorded_at' => optional($location->recorded_at)->toISOString(),
            ],
        ]);
    }

    public function showForAdmin(Request $request, $bookingId)
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array((string) $user->user_type, ['admin', 'dispatcher'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $booking = Booking::where('id', $bookingId)
            ->where('company_id', $this->companyId())
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $location = BookingLiveLocation::where('booking_id', $booking->id)->first();

        return response()->json([
            'data' => [
                'booking_id' => (int) $booking->id,
                'status' => (string) ($booking->status ?? ''),
                'location' => $location,
            ],
        ]);
    }

    private function companyId(): ?int
    {
        return Company::query()->value('id');
    }
}
