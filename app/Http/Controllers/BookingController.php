<?php

namespace App\Http\Controllers;

use App\Mail\BookingCreatedMail;
use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    private const BOOKING_NOTIFICATION_EMAIL = 'info@squarelimo.com';

    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'confirmed', 'assigned', 'on_route', 'completed', 'cancelled', 'done'])],
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Booking::with('driver:id,name')
            ->where('company_id', $company->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $bookings = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json(['data' => $bookings]);
    }

    public function customerBookings(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser instanceof Customer) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'confirmed', 'assigned', 'on_route', 'completed', 'cancelled', 'done'])],
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Booking::where('company_id', $company->id)
            ->where('customer_id', $authUser->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $bookings = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json(['data' => $bookings]);
    }

    public function exportCsv(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'confirmed', 'assigned', 'on_route', 'completed', 'cancelled', 'done'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Booking::with([
                'customer:id,name,email,phone',
                'driver:id,name',
                'vehicle:id,name',
            ])
            ->where('company_id', $company->id)
            ->whereDate('pickup_time', '>=', $request->date_from)
            ->whereDate('pickup_time', '<=', $request->date_to)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->get();

        $fileName = sprintf(
            'bookings_%s_to_%s.csv',
            Carbon::parse($request->date_from)->format('Ymd'),
            Carbon::parse($request->date_to)->format('Ymd')
        );

        return response()->streamDownload(function () use ($bookings): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'booking_id',
                'status',
                'service_type',
                'pickup_time',
                'dropoff_time',
                'pickup_address',
                'dropoff_address',
                'customer_id',
                'customer_name',
                'customer_email',
                'customer_phone',
                'driver_id',
                'driver_name',
                'vehicle_id',
                'vehicle_name',
                'hours',
                'created_at',
                'updated_at',
            ]);

            foreach ($bookings as $booking) {
                fputcsv($handle, [
                    $booking->id,
                    $booking->status,
                    $booking->service_type,
                    $booking->pickup_time,
                    $booking->dropoff_time,
                    $booking->pickup_address,
                    $booking->dropoff_address,
                    $booking->customer_id,
                    $booking->customer?->name,
                    $booking->customer?->email,
                    $booking->customer?->phone,
                    $booking->driver_id,
                    $booking->driver?->name,
                    $booking->vehicle_id,
                    $booking->vehicle?->name,
                    $booking->hours,
                    $booking->created_at,
                    $booking->updated_at,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function dashboardSummary(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $baseQuery = Booking::where('company_id', $company->id);

        $todayPending = (clone $baseQuery)
            ->where('status', 'pending')
            ->whereBetween('pickup_time', [$todayStart, $todayEnd])
            ->count();

        $todayConfirmed = (clone $baseQuery)
            ->where('status', 'confirmed')
            ->whereBetween('pickup_time', [$todayStart, $todayEnd])
            ->count();

        $todayInProgress = (clone $baseQuery)
            ->whereIn('status', ['assigned', 'on_route', 'in_progress'])
            ->whereBetween('pickup_time', [$todayStart, $todayEnd])
            ->count();
        $todayTotalTracked = $todayPending + $todayConfirmed + $todayInProgress;

        $yesterdayPending = (clone $baseQuery)
            ->where('status', 'pending')
            ->whereBetween('pickup_time', [$yesterdayStart, $yesterdayEnd])
            ->count();

        $yesterdayConfirmed = (clone $baseQuery)
            ->where('status', 'confirmed')
            ->whereBetween('pickup_time', [$yesterdayStart, $yesterdayEnd])
            ->count();

        $yesterdayInProgress = (clone $baseQuery)
            ->whereIn('status', ['assigned', 'on_route', 'in_progress'])
            ->whereBetween('pickup_time', [$yesterdayStart, $yesterdayEnd])
            ->count();
        $yesterdayTotalTracked = $yesterdayPending + $yesterdayConfirmed + $yesterdayInProgress;

        $totalTripsLifetime = (clone $baseQuery)
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();

        $driversAvailable = Driver::where('company_id', $company->id)
            ->where('available', true)
            ->count();

        $completedToday = (clone $baseQuery)
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$todayStart, $todayEnd])
            ->count();

        return response()->json([
            'data' => [
                'today_counts' => [
                    'pending' => $todayPending,
                    'confirmed' => $todayConfirmed,
                    'in_progress' => $todayInProgress,
                ],
                'comparison_vs_yesterday' => [
                    'overall_percent' => $this->percentageChange($todayTotalTracked, $yesterdayTotalTracked),
                ],
                'total_trips_lifetime' => $totalTripsLifetime,
                'drivers_available_for_dispatch' => $driversAvailable,
                'completed_today' => $completedToday,
            ],
        ]);
    }

    public function reportSummary(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $hasCustomRange = $request->filled('date_from') && $request->filled('date_to');
        if ($hasCustomRange) {
            $start = Carbon::parse($request->date_from)->startOfDay();
            $end = Carbon::parse($request->date_to)->endOfDay();
        } else {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        }

        $completedStatuses = ['completed', 'done'];

        $totalBookings = Booking::where('company_id', $company->id)
            ->whereBetween('pickup_time', [$start, $end])
            ->count();
        $completedTrips = Booking::where('company_id', $company->id)
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $completedStatuses)
            ->count();
        $avgTripsPerDay = round($totalBookings / max($start->diffInDays($end) + 1, 1), 2);

        $comparison = null;
        if (! $hasCustomRange) {
            $lastStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
            $lastEnd = $start->copy()->subMonthNoOverflow()->endOfMonth();

            $lastTotalBookings = Booking::where('company_id', $company->id)
                ->whereBetween('pickup_time', [$lastStart, $lastEnd])
                ->count();
            $lastCompletedTrips = $this->countCompletedTrips($company->id, $lastStart, $lastEnd, $completedStatuses);

            $comparison = [
                'total_bookings_percent' => $this->percentageChange($totalBookings, $lastTotalBookings),
                'completed_trips_percent' => $this->percentageChange($completedTrips, $lastCompletedTrips),
                'avg_trips_per_day_percent' => $this->percentageChange(
                    (int) round($avgTripsPerDay * 100),
                    (int) round((($lastTotalBookings / max($lastStart->diffInDays($lastEnd) + 1, 1)) * 100))
                ),
            ];
        }

        $dailyBookings = $this->buildDailyBookingSeries($company->id, $completedStatuses, $hasCustomRange ? $end : now());
        $vehicleUtilization = $this->buildVehicleUtilization($company->id, $start, $end, $completedStatuses);
        $topDrivers = $this->buildTopDrivers($company->id, $start, $end, $completedStatuses);

        return response()->json([
            'data' => [
                'range' => [
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                ],
                'total_bookings' => $totalBookings,
                'completed_trips' => $completedTrips,
                'avg_trips_per_day' => $avgTripsPerDay,
                'comparison_vs_last_month' => $comparison,
                'last_7_days_bookings' => $dailyBookings,
                'vehicle_utilization' => $vehicleUtilization,
                'top_drivers' => $topDrivers,
            ],
        ]);
    }

    public function pendingCollectionsReport(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $pendingStatuses = ['pending', 'confirmed', 'assigned', 'on_route', 'in_progress'];

        $pendingBookings = Booking::where('company_id', $company->id)
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $pendingStatuses);

        $pendingCount = (clone $pendingBookings)->count();
        $dueCount = $pendingCount;
        $lastUpdates = BookingActivity::with([
                'adminUser:id,name,email,user_type',
                'booking:id,status,pickup_time,dropoff_time',
            ])
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'activity_id' => (int) $activity->id,
                    'booking_id' => (int) $activity->booking_id,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'booking_status' => $activity->booking?->status,
                    'created_at' => $activity->created_at,
                ];
            });

        return response()->json([
            'data' => [
                'range' => [
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                ],
                'pending_collections' => [
                    'pending_count' => $pendingCount,
                    'due_count' => $dueCount,
                    'operational_queue' => $pendingCount,
                ],
                'last_10_updates' => $lastUpdates,
            ],
        ]);
    }

    public function onRouteBookings(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $perPage = (int) $request->input('per_page', 30);

        $bookings = Booking::with([
                'customer:id,name,email,phone',
                'driver:id,name,phone',
                'vehicle:id,name,image',
            ])
            ->where('company_id', $company->id)
            ->where('status', 'on_route')
            ->orderByDesc('pickup_time')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json(['data' => $bookings]);
    }

    public function recentActivity(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'booking_id' => ['sometimes', 'nullable', Rule::exists('bookings', 'id')],
            'action' => 'sometimes|nullable|string|max:100',
            'admin_user_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')],
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = BookingActivity::with([
                'adminUser:id,name,email,user_type',
                'booking:id,status,pickup_time,dropoff_time',
            ])
            ->where('company_id', $company->id);

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('admin_user_id')) {
            $query->where('admin_user_id', $request->admin_user_id);
        }

        $perPage = (int) $request->input('per_page', 20);
        $activities = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $activities,
        ]);
    }

    public function liveOperationsFeed(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $feed = Booking::with([
                'customer:id,name,email,phone',
                'driver:id,name,phone',
                'vehicle:id,name,capacity,image',
            ])
            ->where('company_id', $company->id)
            ->whereIn('status', ['pending', 'assigned', 'on_route', 'in_progress'])
            ->whereBetween('pickup_time', [$todayStart, $todayEnd])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => $feed,
        ]);
    }

    public function vehicleAvailability(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $now = now();
        $unavailableVehicleIds = $this->getUnavailableVehicleIds($company->id, $now, null);

        $availableVehicles = Vehicle::with('vehicleClass:id,name')
            ->whereNotIn('id', $unavailableVehicleIds)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'window' => [
                    'center_time' => $now->toDateTimeString(),
                    'from' => $now->copy()->subHours(2)->toDateTimeString(),
                    'to' => $now->copy()->addHours(2)->toDateTimeString(),
                ],
                'count' => $availableVehicles->count(),
                'vehicles' => $availableVehicles,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $company = $this->getCompany();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_id'     => ['nullable', Rule::exists('customers', 'id')],
            'name'            => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'vehicle_id'      => ['nullable', Rule::exists('vehicles', 'id')],
            'driver_id'       => ['nullable', Rule::exists('drivers', 'id')],
            'service_type'    => ['required', Rule::in(['point_to_point', 'hourly', 'airport', 'custom'])],
            'pickup_address'  => 'required|string',
            'dropoff_address' => 'nullable|string',
            'pickup_time'     => 'required|date',
            'dropoff_time'    => 'nullable|date|after_or_equal:pickup_time',
            'passengers'      => 'required|integer|min:1',
            'child_seats'     => 'nullable|integer|min:0',
            'bags'            => 'nullable|integer|min:0',
            'flight_number'   => 'nullable|string|max:100',
            'airlines'        => 'nullable|string|max:100',
            'hours'           => 'nullable|numeric|min:0',
            'status'          => ['nullable', Rule::in(['pending', 'confirmed', 'assigned', 'on_route', 'completed', 'cancelled', 'done'])],
            'notes'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->service_type === 'hourly' && ! $request->filled('hours')) {
            return response()->json([
                'message' => 'Hours is required for hourly service'
            ], 422);
        }

        $authUser = auth('sanctum')->user() ?? $request->user();
        if ($request->filled('customer_id')) {
            if ($authUser instanceof User) {
                if (! in_array((string) $authUser->user_type, ['admin', 'dispatcher'], true)) {
                    return response()->json([
                        'message' => 'Unauthorized customer_id'
                    ], 403);
                }
            } elseif ($authUser instanceof Customer) {
                if ((int) $request->customer_id !== (int) $authUser->id) {
                    return response()->json([
                        'message' => 'Unauthorized customer_id'
                    ], 403);
                }
            } else {
                return response()->json([
                    'message' => 'Unauthorized customer_id'
                ], 403);
            }
        }

        $pickup = Carbon::parse($request->pickup_time);
        $dropoff = $request->filled('dropoff_time') ? Carbon::parse($request->dropoff_time) : null;
        $unavailableVehicleIds = $this->getUnavailableVehicleIds($company->id, $pickup, $dropoff);

        if ($request->filled('vehicle_id') && in_array((int) $request->vehicle_id, $unavailableVehicleIds, true)) {
            return response()->json([
                'message' => 'Selected vehicle is not available for the requested time'
            ], 409);
        }

        $vehicle = $request->filled('vehicle_id') ? Vehicle::find($request->vehicle_id) : null;
        if ($request->filled('vehicle_id') && ! $vehicle) {
            return response()->json([
                'message' => 'Vehicle not found'
            ], 404);
        }

        try {
            $booking = DB::transaction(function () use ($request, $company, $authUser, $vehicle) {
                $data = $request->only([
                    'customer_id',
                    'name',
                    'email',
                    'phone',
                    'vehicle_id',
                    'driver_id',
                    'service_type',
                    'pickup_address',
                    'dropoff_address',
                    'pickup_time',
                    'dropoff_time',
                    'passengers',
                    'child_seats',
                    'bags',
                    'flight_number',
                    'airlines',
                    'hours',
                    'status',
                    'notes',
                ]);

                $data['company_id'] = $company->id;

                if ($authUser instanceof Customer) {
                    $data['customer_id'] = $authUser->id;
                    $data['name'] = $authUser->name;
                    $data['email'] = $authUser->email;
                    $data['phone'] = $authUser->phone;
                } elseif (! empty($data['customer_id'])) {
                    $selectedCustomer = Customer::find($data['customer_id']);

                    if ($selectedCustomer) {
                        $data['name'] = $selectedCustomer->name;
                        $data['email'] = $selectedCustomer->email;
                        $data['phone'] = $selectedCustomer->phone;
                    }
                }

                return Booking::create($data);
            });

            $freshBooking = $booking->fresh(['company', 'customer', 'vehicle', 'driver']);

            $this->logBookingActivity(
                request: $request,
                booking: $freshBooking,
                action: 'booking_created',
                description: 'Booking created by admin/dispatcher',
                oldValues: null,
                newValues: [
                    'status' => $freshBooking->status,
                    'customer_id' => $freshBooking->customer_id,
                    'vehicle_id' => $freshBooking->vehicle_id,
                    'driver_id' => $freshBooking->driver_id,
                    'pickup_time' => $freshBooking->pickup_time,
                ]
            );

            $this->sendBookingCreatedEmails($freshBooking);

            return response()->json([
                'message' => 'Booking created successfully',
                'data' => $freshBooking,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function sendBookingCreatedEmails(Booking $booking): void
    {
        if ($booking->email) {
            try {
                Mail::to($booking->email)->send(new BookingCreatedMail($booking));
            } catch (\Throwable $e) {
                Log::warning('Booking customer confirmation email failed', [
                    'booking_id' => $booking->id,
                    'email' => $booking->email,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Booking customer confirmation email skipped because no contact email is available', [
                'booking_id' => $booking->id,
            ]);
        }

        try {
            Mail::to(self::BOOKING_NOTIFICATION_EMAIL)->send(new BookingCreatedMail(
                booking: $booking,
                isAdminCopy: true
            ));
        } catch (\Throwable $e) {
            Log::warning('Booking internal notification email failed', [
                'booking_id' => $booking->id,
                'email' => self::BOOKING_NOTIFICATION_EMAIL,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json(['data' => $booking]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $beforeSnapshot = $booking->only([
            'status',
            'customer_id',
            'vehicle_id',
            'driver_id',
            'service_type',
            'pickup_address',
            'dropoff_address',
            'pickup_time',
            'dropoff_time',
            'hours',
            'notes',
        ]);

        $validator = Validator::make($request->all(), [
            'customer_id'     => ['sometimes', 'nullable', Rule::exists('customers', 'id')],
            'name'            => 'sometimes|nullable|string|max:255',
            'email'           => 'sometimes|nullable|email|max:255',
            'phone'           => 'sometimes|nullable|string|max:50',
            'vehicle_id'      => ['sometimes', Rule::exists('vehicles', 'id')],
            'driver_id'       => ['sometimes', 'nullable', Rule::exists('drivers', 'id')],
            'service_type'    => ['sometimes', Rule::in(['point_to_point', 'hourly', 'airport', 'custom'])],
            'pickup_address'  => 'sometimes|required|string',
            'dropoff_address' => 'sometimes|nullable|string',
            'pickup_time'     => 'sometimes|required|date',
            'dropoff_time'    => 'sometimes|nullable|date|after_or_equal:pickup_time',
            'passengers'      => 'sometimes|required|integer|min:1',
            'child_seats'     => 'sometimes|nullable|integer|min:0',
            'bags'            => 'sometimes|nullable|integer|min:0',
            'flight_number'   => 'sometimes|nullable|string|max:100',
            'airlines'        => 'sometimes|nullable|string|max:100',
            'hours'           => 'sometimes|nullable|numeric|min:0',
            'status'          => ['sometimes', Rule::in(['pending', 'confirmed', 'assigned', 'on_route', 'completed', 'cancelled', 'done'])],
            'notes'           => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newServiceType = $request->input('service_type', $booking->service_type);
        $newHours = $request->input('hours', $booking->hours);

        if ($newServiceType === 'hourly' && empty($newHours)) {
            return response()->json([
                'message' => 'Hours is required for hourly service'
            ], 422);
        }

        $authUser = $request->user();
        if ($authUser instanceof Customer) {
            if ($request->filled('customer_id') && (int) $request->customer_id !== (int) $authUser->id) {
                return response()->json([
                    'message' => 'Unauthorized customer_id'
                ], 403);
            }
        }

        $isTimeOrVehicleChanged = $request->hasAny(['pickup_time', 'dropoff_time', 'vehicle_id']);

        if ($isTimeOrVehicleChanged) {
            $newPickupTime = $request->input('pickup_time', $booking->pickup_time);
            $newDropoffTime = $request->input('dropoff_time', $booking->dropoff_time);
            $newVehicleId = $request->input('vehicle_id', $booking->vehicle_id);

            if (! empty($newVehicleId)) {
                $pickup = Carbon::parse($newPickupTime);
                $dropoff = $newDropoffTime ? Carbon::parse($newDropoffTime) : null;

                $unavailableVehicleIds = $this->getUnavailableVehicleIds($company->id, $pickup, $dropoff, $booking->id);

                if (in_array((int) $newVehicleId, $unavailableVehicleIds, true)) {
                    return response()->json([
                        'message' => 'Selected vehicle is not available for the requested time'
                    ], 409);
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $booking) {
                $authUser = $request->user();

                if ($authUser instanceof Customer) {
                    $fillableData = $request->except(['customer_id', 'name', 'email', 'phone']);
                    $booking->fill($fillableData);
                } else {
                    $booking->fill($request->only([
                        'customer_id',
                        'name',
                        'email',
                        'phone',
                        'vehicle_id',
                        'driver_id',
                        'service_type',
                        'pickup_address',
                        'dropoff_address',
                        'pickup_time',
                        'dropoff_time',
                        'passengers',
                        'child_seats',
                        'bags',
                        'flight_number',
                        'airlines',
                        'hours',
                        'status',
                        'notes',
                    ]));
                }

                $booking->save();
            });

            $freshBooking = $booking->fresh();
            $response = [
                'message' => 'Booking updated successfully',
                'data' => $freshBooking,
            ];

            $afterSnapshot = $freshBooking->only(array_keys($beforeSnapshot));
            [$oldValues, $newValues] = $this->diffValues($beforeSnapshot, $afterSnapshot);

            $statusWasChanged = array_key_exists('status', $newValues);
            $action = $statusWasChanged && (string) ($newValues['status'] ?? '') === 'cancelled'
                ? 'booking_cancelled'
                : ($statusWasChanged ? 'status_changed' : 'booking_updated');
            $description = $statusWasChanged
                ? 'Booking status updated by admin/dispatcher'
                : 'Booking updated by admin/dispatcher';

            $this->logBookingActivity(
                request: $request,
                booking: $freshBooking,
                action: $action,
                description: $description,
                oldValues: $oldValues,
                newValues: $newValues
            );

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $oldValues = $booking->only([
            'status',
            'customer_id',
            'vehicle_id',
            'driver_id',
            'pickup_time',
            'dropoff_time',
        ]);

        $this->logBookingActivity(
            request: $request,
            booking: $booking,
            action: 'booking_deleted',
            description: 'Booking deleted by admin/dispatcher',
            oldValues: $oldValues,
            newValues: null
        );

        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully'], 200);
    }

    public function assignDriver(Request $request, $id)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'driver_id' => [
                'required',
                Rule::exists('drivers', 'id')->where('company_id', $company->id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (in_array((string) $booking->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Driver assignment is not allowed for completed/cancelled bookings',
            ], 422);
        }

        $pickup = Carbon::parse($booking->pickup_time);
        $dropoff = $booking->dropoff_time ? Carbon::parse($booking->dropoff_time) : null;
        $unavailableDriverIds = $this->getUnavailableDriverIds($company->id, $pickup, $dropoff, $booking->id);

        if (in_array((int) $request->driver_id, $unavailableDriverIds, true)) {
            return response()->json([
                'message' => 'Selected driver is not available for the requested time',
            ], 409);
        }

        $oldValues = ['driver_id' => $booking->driver_id];
        $booking->driver_id = (int) $request->driver_id;
        $booking->save();

        $this->logBookingActivity(
            request: $request,
            booking: $booking,
            action: 'driver_assigned',
            description: 'Driver assigned by admin/dispatcher',
            oldValues: $oldValues,
            newValues: ['driver_id' => $booking->driver_id]
        );

        return response()->json([
            'message' => 'Driver assigned successfully',
            'data' => $booking->fresh(),
        ]);
    }

    public function assignAffiliate(Request $request, $id)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'affiliate_id' => [
                'required',
                Rule::exists('affiliates', 'id')->where('company_id', $company->id),
            ],
            'affiliate_reference' => 'sometimes|nullable|string|max:255',
            'affiliate_notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (in_array((string) $booking->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Affiliate assignment is not allowed for completed/cancelled bookings',
            ], 422);
        }

        $oldValues = [
            'affiliate_id' => $booking->affiliate_id,
            'affiliate_status' => $booking->affiliate_status,
            'affiliate_reference' => $booking->affiliate_reference,
            'affiliate_notes' => $booking->affiliate_notes,
        ];

        $booking->affiliate_id = (int) $request->affiliate_id;
        $booking->affiliate_status = 'offered';
        if ($request->has('affiliate_reference')) {
            $booking->affiliate_reference = $request->affiliate_reference;
        }
        if ($request->has('affiliate_notes')) {
            $booking->affiliate_notes = $request->affiliate_notes;
        }
        $booking->save();

        $this->logBookingActivity(
            request: $request,
            booking: $booking,
            action: 'affiliate_assigned',
            description: 'Affiliate assigned by admin/dispatcher',
            oldValues: $oldValues,
            newValues: [
                'affiliate_id' => $booking->affiliate_id,
                'affiliate_status' => $booking->affiliate_status,
                'affiliate_reference' => $booking->affiliate_reference,
                'affiliate_notes' => $booking->affiliate_notes,
            ]
        );

        return response()->json([
            'message' => 'Affiliate assigned successfully',
            'data' => $booking->fresh(),
        ]);
    }

    public function updateStatusOnly(Request $request, $id)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['pending', 'confirmed', 'assigned', 'on_route', 'completed', 'cancelled', 'done'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldValues = ['status' => $booking->status];
        $booking->status = (string) $request->status;

        $booking->save();
        $freshBooking = $booking->fresh();

        $this->logBookingActivity(
            request: $request,
            booking: $freshBooking,
            action: $freshBooking->status === 'cancelled' ? 'booking_cancelled' : 'status_changed',
            description: 'Booking status updated by admin/dispatcher',
            oldValues: $oldValues,
            newValues: ['status' => $freshBooking->status]
        );

        $response = [
            'message' => 'Booking status updated successfully',
            'data' => $freshBooking,
        ];

        return response()->json($response);
    }

    public function cancelBooking(Request $request, $id)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = $this->getCompany();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $booking = Booking::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ((string) $booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Booking is already cancelled',
                'data' => $booking,
            ], 200);
        }

        $oldValues = $booking->only([
            'status',
        ]);

        $booking->status = 'cancelled';
        $booking->save();

        $freshBooking = $booking->fresh();

        $this->logBookingActivity(
            request: $request,
            booking: $freshBooking,
            action: 'booking_cancelled',
            description: 'Booking cancelled by admin/dispatcher',
            oldValues: $oldValues,
            newValues: [
                'status' => $freshBooking->status,
            ]
        );

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'data' => $freshBooking,
        ]);
    }

    /**
     * Get the company instance
     */
    private function getCompany(): ?Company
    {
        return Company::first();
    }

    private function getUnavailableVehicleIds(int $companyId, Carbon $pickup, ?Carbon $dropoff, ?int $excludeBookingId = null): array
    {
        $windowStart = $dropoff ? $pickup : $pickup->copy()->subHours(2);
        $windowEnd = $dropoff ? $dropoff : $pickup->copy()->addHours(2);

        $query = Booking::where('company_id', $companyId)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->where(function ($q) use ($windowStart, $windowEnd) {
                    $q->whereNotNull('dropoff_time')
                        ->where('pickup_time', '<=', $windowEnd)
                        ->where('dropoff_time', '>=', $windowStart);
                })->orWhere(function ($q) use ($windowStart, $windowEnd) {
                    $q->whereNull('dropoff_time')
                        ->whereBetween('pickup_time', [$windowStart, $windowEnd]);
                });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->pluck('vehicle_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function getUnavailableDriverIds(int $companyId, Carbon $pickup, ?Carbon $dropoff, ?int $excludeBookingId = null): array
    {
        $windowStart = $dropoff ? $pickup : $pickup->copy()->subHours(2);
        $windowEnd = $dropoff ? $dropoff : $pickup->copy()->addHours(2);

        $query = Booking::where('company_id', $companyId)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->where(function ($q) use ($windowStart, $windowEnd) {
                    $q->whereNotNull('dropoff_time')
                        ->where('pickup_time', '<=', $windowEnd)
                        ->where('dropoff_time', '>=', $windowStart);
                })->orWhere(function ($q) use ($windowStart, $windowEnd) {
                    $q->whereNull('dropoff_time')
                        ->whereBetween('pickup_time', [$windowStart, $windowEnd]);
                });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->pluck('driver_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function percentageChange(int $today, int $yesterday): ?float
    {
        if ($yesterday === 0) {
            return null;
        }

        return round((($today - $yesterday) / $yesterday) * 100, 2);
    }

    private function countCompletedTrips(int $companyId, Carbon $start, Carbon $end, array $completedStatuses): int
    {
        return (int) Booking::where('company_id', $companyId)
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $completedStatuses)
            ->count();
    }

    private function buildDailyBookingSeries(int $companyId, array $completedStatuses, Carbon $endDate): array
    {
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = $endDate->copy()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $count = Booking::where('company_id', $companyId)
                ->whereBetween('pickup_time', [$dayStart, $dayEnd])
                ->whereIn('status', $completedStatuses)
                ->count();

            $days[] = [
                'date' => $date->toDateString(),
                'bookings' => $count,
            ];
        }

        return $days;
    }

    private function buildVehicleUtilization(int $companyId, Carbon $start, Carbon $end, array $completedStatuses): array
    {
        $rows = Booking::where('company_id', $companyId)
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $completedStatuses)
            ->whereNotNull('vehicle_id')
            ->selectRaw('vehicle_id, COUNT(*) as trips')
            ->groupBy('vehicle_id')
            ->get();

        $totalTrips = (int) $rows->sum('trips');
        if ($totalTrips === 0) {
            return [];
        }

        $vehicleIds = $rows->pluck('vehicle_id')->all();
        $vehicles = Vehicle::whereIn('id', $vehicleIds)->get(['id', 'name'])->keyBy('id');

        return $rows->map(function ($row) use ($vehicles, $totalTrips) {
            $vehicle = $vehicles->get($row->vehicle_id);
            $percent = $totalTrips > 0 ? round(($row->trips / $totalTrips) * 100, 2) : 0;

            return [
                'vehicle_id' => $row->vehicle_id,
                'vehicle_name' => $vehicle?->name,
                'trips' => (int) $row->trips,
                'utilization_percent' => $percent,
            ];
        })->sortByDesc('utilization_percent')->values()->all();
    }

    private function buildTopDrivers(int $companyId, Carbon $start, Carbon $end, array $completedStatuses): array
    {
        $rows = Booking::where('company_id', $companyId)
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $completedStatuses)
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, COUNT(*) as trips')
            ->groupBy('driver_id')
            ->orderByDesc('trips')
            ->limit(3)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $driverIds = $rows->pluck('driver_id')->all();
        $drivers = Driver::whereIn('id', $driverIds)->get(['id', 'name'])->keyBy('id');

        return $rows->map(function ($row) use ($drivers) {
            $driver = $drivers->get($row->driver_id);

            return [
                'driver_id' => $row->driver_id,
                'driver_name' => $driver?->name,
                'trips' => (int) $row->trips,
            ];
        })->values()->all();
    }

    private function diffValues(array $before, array $after): array
    {
        $oldValues = [];
        $newValues = [];

        foreach ($after as $key => $afterValue) {
            $beforeValue = $before[$key] ?? null;

            if ($beforeValue != $afterValue) {
                $oldValues[$key] = $beforeValue;
                $newValues[$key] = $afterValue;
            }
        }

        return [$oldValues, $newValues];
    }

    private function logBookingActivity(
        Request $request,
        Booking $booking,
        string $action,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $actor = $request->user();

        if (! $actor instanceof User || ! in_array((string) $actor->user_type, ['admin', 'dispatcher'], true)) {
            return;
        }

        BookingActivity::create([
            'company_id' => $booking->company_id,
            'booking_id' => $booking->id,
            'admin_user_id' => $actor->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
        ]);
    }

}
