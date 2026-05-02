<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\BookingPayment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Affiliate;
use App\Models\AffiliateBookingSettlement;
use App\Models\AffiliateDisbursement;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class BookingController extends Controller
{
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
                'distance_km',
                'hours',
                'total_price',
                'final_price',
                'payment_method',
                'payment_status',
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
                    $booking->distance_km,
                    $booking->hours,
                    $booking->total_price,
                    $booking->final_price,
                    $booking->payment_method,
                    $booking->payment_status,
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

        $earningsToday = (clone $baseQuery)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('updated_at', [$todayStart, $todayEnd])
            ->sum('final_price');

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
                'earnings_today' => round((float) $earningsToday, 2),
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

        $totalRevenue = $this->sumRevenue($company->id, $start, $end, $completedStatuses);
        $completedRevenue = $this->sumCompletedRevenue($company->id, $start, $end, $completedStatuses);
        $completedTrips = $this->countCompletedTrips($company->id, $start, $end, $completedStatuses);
        $avgTripValue = $completedTrips > 0 ? round($completedRevenue / $completedTrips, 2) : 0.0;
        $affiliatePayout = $this->sumAffiliatePayout($company->id, $start, $end);
        $netProfit = round($totalRevenue - $affiliatePayout, 2);

        $comparison = null;
        if (! $hasCustomRange) {
            $lastStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
            $lastEnd = $start->copy()->subMonthNoOverflow()->endOfMonth();

            $lastTotalRevenue = $this->sumRevenue($company->id, $lastStart, $lastEnd, $completedStatuses);
            $lastCompletedRevenue = $this->sumCompletedRevenue($company->id, $lastStart, $lastEnd, $completedStatuses);
            $lastCompletedTrips = $this->countCompletedTrips($company->id, $lastStart, $lastEnd, $completedStatuses);
            $lastAvgTripValue = $lastCompletedTrips > 0 ? round($lastCompletedRevenue / $lastCompletedTrips, 2) : 0.0;
            $lastAffiliatePayout = $this->sumAffiliatePayout($company->id, $lastStart, $lastEnd);
            $lastNetProfit = round($lastTotalRevenue - $lastAffiliatePayout, 2);

            $comparison = [
                'total_revenue_percent' => $this->percentageChange((int) round($totalRevenue * 100), (int) round($lastTotalRevenue * 100)),
                'completed_trips_percent' => $this->percentageChange($completedTrips, $lastCompletedTrips),
                'avg_trip_value_percent' => $this->percentageChange((int) round($avgTripValue * 100), (int) round($lastAvgTripValue * 100)),
                'net_profit_percent' => $this->percentageChange((int) round($netProfit * 100), (int) round($lastNetProfit * 100)),
            ];
        }

        $dailyRevenue = $this->buildDailyRevenueSeries($company->id, $completedStatuses, $hasCustomRange ? $end : now());
        $vehicleUtilization = $this->buildVehicleUtilization($company->id, $start, $end, $completedStatuses);
        $topDrivers = $this->buildTopDrivers($company->id, $start, $end, $completedStatuses);

        return response()->json([
            'data' => [
                'range' => [
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                ],
                'total_revenue' => round($totalRevenue, 2),
                'completed_trips' => $completedTrips,
                'avg_trip_value' => round($avgTripValue, 2),
                'net_profit' => $netProfit,
                'comparison_vs_last_month' => $comparison,
                'last_7_days_revenue' => $dailyRevenue,
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
        $pendingEstimatedTotal = (float) (clone $pendingBookings)
            ->selectRaw('SUM(COALESCE(final_price, total_price, 0)) as total')
            ->value('total');

        $dueCount = $pendingCount;

        $pendingAffiliateStatuses = ['pending', 'ready', 'on_hold', 'failed'];
        $pendingAffiliateCommission = (float) AffiliateBookingSettlement::query()
            ->join('bookings', 'affiliate_booking_settlements.booking_id', '=', 'bookings.id')
            ->where('bookings.company_id', $company->id)
            ->whereBetween('bookings.pickup_time', [$start, $end])
            ->whereIn('affiliate_booking_settlements.status', $pendingAffiliateStatuses)
            ->sum('affiliate_booking_settlements.affiliate_amount');

        $lastBookingPayments = BookingPayment::query()
            ->join('bookings', 'booking_payments.booking_id', '=', 'bookings.id')
            ->where('bookings.company_id', $company->id)
            ->selectRaw("
                booking_payments.id as transaction_id,
                'booking_payment' as type,
                booking_payments.booking_id as booking_id,
                NULL as affiliate_id,
                COALESCE(booking_payments.captured_amount, booking_payments.authorized_amount, booking_payments.estimated_amount, 0) as amount,
                booking_payments.currency as currency,
                booking_payments.status as status,
                booking_payments.created_at as created_at
            ");

        $lastDisbursements = AffiliateDisbursement::query()
            ->join('bookings', 'affiliate_disbursements.booking_id', '=', 'bookings.id')
            ->where('bookings.company_id', $company->id)
            ->selectRaw("
                affiliate_disbursements.id as transaction_id,
                'affiliate_disbursement' as type,
                affiliate_disbursements.booking_id as booking_id,
                affiliate_disbursements.affiliate_id as affiliate_id,
                affiliate_disbursements.amount as amount,
                affiliate_disbursements.currency as currency,
                affiliate_disbursements.status as status,
                affiliate_disbursements.created_at as created_at
            ");

        $lastTransactions = $lastBookingPayments
            ->unionAll($lastDisbursements)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'transaction_id' => (int) $row->transaction_id,
                    'type' => $row->type,
                    'booking_id' => $row->booking_id ? (int) $row->booking_id : null,
                    'affiliate_id' => $row->affiliate_id ? (int) $row->affiliate_id : null,
                    'amount' => round((float) $row->amount, 2),
                    'currency' => $row->currency,
                    'status' => $row->status,
                    'created_at' => $row->created_at,
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
                    'estimated_total' => round($pendingEstimatedTotal, 2),
                ],
                'pending_affiliate_commissions' => [
                    'total' => round($pendingAffiliateCommission, 2),
                    'statuses' => $pendingAffiliateStatuses,
                ],
                'last_10_transactions' => $lastTransactions,
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
                'booking:id,status,pickup_time,dropoff_time,total_price,final_price',
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
            'distance_km'     => [
                Rule::requiredIf(fn () => $request->service_type !== 'hourly'),
                'numeric',
                'min:0',
                'nullable',
            ],
            'hours'           => 'nullable|numeric|min:0',
            'extras_price'    => 'nullable|numeric|min:0',
            'parking'         => 'nullable|numeric|min:0',
            'others'          => 'nullable|numeric|min:0',
            'airport_fees'    => 'nullable|numeric|min:0',
            'congestion_charge' => 'nullable|numeric|min:0',
            'payment_method'  => 'nullable|string|max:100',
            'payment_status'  => 'nullable|string|max:100',
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

        $vehicles = Vehicle::with('vehicleClass:id,name')
            ->whereNotIn('id', $unavailableVehicleIds)
            ->get();

        $passengers = (int) $request->passengers;
        $minCap = $passengers + 2;
        $maxCap = $passengers + 4;

        $systemConfig = $this->getSystemConfig($company->id);

        $vehicleOptions = $vehicles->map(function ($vehicle) use ($request, $minCap, $maxCap, $systemConfig) {
            $priceCalculation = $this->calculatePrice($vehicle, $this->buildPriceInput($request, $systemConfig));

            $capacity = (int) ($vehicle->capacity ?? 0);
            $recommended = $capacity >= $minCap && $capacity <= $maxCap;

            return [
                'vehicle_id' => $vehicle->id,
                'name' => $vehicle->name,
                'class' => $vehicle->vehicleClass?->name,
                'image' => $vehicle->image,
                'capacity' => $vehicle->capacity,
                'rate' => $priceCalculation['rate'],
                'base_price' => $priceCalculation['base_price'],
                'distance_km' => $priceCalculation['distance_km'],
                'hours' => $priceCalculation['hours'],
                'total_price' => $priceCalculation['total_price'],
                'calculation' => $this->buildCalculationBreakdown($priceCalculation),
                'recommended' => $recommended,
            ];
        })->values();

        if (! $request->filled('vehicle_id')) {
            return response()->json([
                'data' => [
                    'service_type' => $request->service_type,
                    'passengers' => (int) $request->passengers,
                    'distance_km' => (float) ($request->distance_km ?? 0),
                    'hours' => (float) ($request->hours ?? 0),
                    'vehicle_options' => $vehicleOptions,
                ],
            ]);
        }

        if (in_array((int) $request->vehicle_id, $unavailableVehicleIds, true)) {
            return response()->json([
                'message' => 'Selected vehicle is not available for the requested time'
            ], 409);
        }

        $vehicle = $vehicles->firstWhere('id', (int) $request->vehicle_id) ?? Vehicle::find($request->vehicle_id);
        if (! $vehicle) {
            return response()->json([
                'message' => 'Vehicle not found'
            ], 404);
        }

        try {
            $booking = DB::transaction(function () use ($request, $company, $vehicle, $authUser) {
                $systemConfig = $this->getSystemConfig($company->id);
                $priceCalculation = $this->calculatePrice($vehicle, $this->buildPriceInput($request, $systemConfig));

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
                    'distance_km',
                    'hours',
                    'extras_price',
                    'parking',
                    'others',
                    'airport_fees',
                    'congestion_charge',
                    'payment_method',
                    'payment_status',
                    'status',
                    'notes',
                ]);

                $data['company_id'] = $company->id;
                $data['base_price'] = $priceCalculation['base_price'];
                $data['taxes'] = $priceCalculation['tax_rate'];
                $data['taxes_amount'] = $priceCalculation['taxes_amount'];
                $data['gratuity'] = $priceCalculation['gratuity_percentage'];
                $data['gratuity_amount'] = $priceCalculation['gratuity_amount'];
                $data['rate_buffer'] = $priceCalculation['rate_buffer'];
                $data['rate_buffer_amount'] = $priceCalculation['buffer_amount'];
                $data['surge_rate'] = $priceCalculation['surge_rate'];
                $data['surge_rate_amount'] = $priceCalculation['surge_rate_amount'];
                $data['cancellation_fee'] = $priceCalculation['cancellation_fee'];
                $data['total_price'] = $priceCalculation['total_price'];
                $data['final_price'] = $priceCalculation['total_price'];

                $isGuestBooking = ! ($authUser instanceof Customer) && ! ($authUser instanceof User);
                if ($isGuestBooking) {
                    $data['booking_access_token'] = Str::random(64);
                }

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

                $booking = Booking::create($data);
                $booking->setAttribute('price_calculation', $priceCalculation);
                if ($isGuestBooking) {
                    $booking->setAttribute('issued_booking_access_token', $data['booking_access_token']);
                }

                return $booking;
            });

            $freshBooking = $booking->fresh();
            $calc = $booking->getAttribute('price_calculation');
            $issuedBookingAccessToken = $booking->getAttribute('issued_booking_access_token');

            $response = [
                'message' => 'Booking created successfully',
                'data' => $freshBooking,
                'calculation' => $this->buildCalculationBreakdown($calc),
            ];

            if ($issuedBookingAccessToken) {
                $response['booking_access_token'] = $issuedBookingAccessToken;
            }

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
                    'total_price' => $freshBooking->total_price,
                    'final_price' => $freshBooking->final_price,
                ]
            );

            return response()->json($response, 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage()
            ], 500);
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
            'distance_km',
            'hours',
            'base_price',
            'extras_price',
            'taxes',
            'taxes_amount',
            'gratuity',
            'gratuity_amount',
            'rate_buffer',
            'rate_buffer_amount',
            'surge_rate',
            'surge_rate_amount',
            'cancellation_fee',
            'total_price',
            'final_price',
            'payment_method',
            'payment_status',
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
            'distance_km'     => [
                'sometimes',
                Rule::requiredIf(fn () => $request->input('service_type', $booking->service_type) !== 'hourly'),
                'numeric',
                'min:0',
                'nullable',
            ],
            'hours'           => 'sometimes|nullable|numeric|min:0',
            'extras_price'    => 'sometimes|nullable|numeric|min:0',
            'parking'         => 'sometimes|nullable|numeric|min:0',
            'others'          => 'sometimes|nullable|numeric|min:0',
            'airport_fees'    => 'sometimes|nullable|numeric|min:0',
            'congestion_charge' => 'sometimes|nullable|numeric|min:0',
            'payment_method'  => 'sometimes|nullable|string|max:100',
            'payment_status'  => 'sometimes|nullable|string|max:100',
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

        // Check vehicle availability if time or vehicle is being changed
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

                // If authenticated user is a Customer, prevent overriding customer data
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
                        'distance_km',
                        'hours',
                        'extras_price',
                        'parking',
                        'others',
                        'airport_fees',
                        'congestion_charge',
                        'payment_method',
                        'payment_status',
                        'status',
                        'notes',
                    ]));
                }

                $latestPriceCalculation = null;
                $isCancelled = (string) $booking->status === 'cancelled';

                if ($isCancelled) {
                    $systemConfig = $this->getSystemConfig($booking->company_id);
                    $latestPriceCalculation = $this->buildCancellationPriceCalculation(
                        cancellationFee: (float) ($systemConfig->cancellation_fee ?? 0),
                        serviceType: (string) ($booking->service_type ?? 'custom')
                    );

                    $booking->base_price = 0;
                    $booking->extras_price = 0;
                    $booking->parking = 0;
                    $booking->others = 0;
                    $booking->airport_fees = 0;
                    $booking->congestion_charge = 0;
                    $booking->taxes = 0;
                    $booking->taxes_amount = 0;
                    $booking->gratuity = 0;
                    $booking->gratuity_amount = 0;
                    $booking->rate_buffer = 0;
                    $booking->rate_buffer_amount = 0;
                    $booking->surge_rate = 0;
                    $booking->surge_rate_amount = 0;
                    $booking->cancellation_fee = $latestPriceCalculation['cancellation_fee'];
                    $booking->total_price = $latestPriceCalculation['total_price'];
                    $booking->final_price = $latestPriceCalculation['total_price'];
                }

                $recalc = ! $isCancelled && $request->hasAny([
                    'vehicle_id',
                    'service_type',
                    'distance_km',
                    'hours',
                    'extras_price',
                    'parking',
                    'others',
                    'airport_fees',
                    'congestion_charge',
                    'status',
                ]);

                if ($recalc) {
                    $vehicle = Vehicle::find($booking->vehicle_id);
                    $systemConfig = $this->getSystemConfig($booking->company_id);
                    $priceCalculation = $this->calculatePrice($vehicle, $this->buildPriceInput($booking, $systemConfig));

                    $booking->base_price = $priceCalculation['base_price'];
                    $booking->taxes = $priceCalculation['tax_rate'];
                    $booking->taxes_amount = $priceCalculation['taxes_amount'];
                    $booking->gratuity = $priceCalculation['gratuity_percentage'];
                    $booking->gratuity_amount = $priceCalculation['gratuity_amount'];
                    $booking->rate_buffer = $priceCalculation['rate_buffer'];
                    $booking->rate_buffer_amount = $priceCalculation['buffer_amount'];
                    $booking->surge_rate = $priceCalculation['surge_rate'];
                    $booking->surge_rate_amount = $priceCalculation['surge_rate_amount'];
                    $booking->cancellation_fee = $priceCalculation['cancellation_fee'];
                    $booking->total_price = $priceCalculation['total_price'];
                    $booking->final_price = $priceCalculation['total_price'];
                    $latestPriceCalculation = $priceCalculation;
                }

                $booking->save();

                // expose latest pricing flow after any update
                if (! $latestPriceCalculation) {
                    $vehicle = Vehicle::find($booking->vehicle_id);
                    $systemConfig = $this->getSystemConfig($booking->company_id);
                    $latestPriceCalculation = $this->calculatePrice($vehicle, $this->buildPriceInput($booking, $systemConfig));
                }

                $booking->setAttribute('price_calculation', $latestPriceCalculation);
            });

            $freshBooking = $booking->fresh();
            $calc = $booking->getAttribute('price_calculation');
            $cancellationPayment = null;

            if ((string) $freshBooking->status === 'cancelled') {
                $this->syncAffiliateSettlementOnCancellation($freshBooking);
                $cancellationPayment = $this->captureCancellationPayment($freshBooking);
            }

            $response = [
                'message' => 'Booking updated successfully',
                'data' => $freshBooking,
                'calculation' => $this->buildCalculationBreakdown($calc),
            ];

            if ($cancellationPayment) {
                $response['cancellation_payment'] = $cancellationPayment;
            }

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
            'total_price',
            'final_price',
            'payment_status',
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

        if ($booking->status === 'cancelled') {
            $systemConfig = $this->getSystemConfig($booking->company_id);
            $priceCalculation = $this->buildCancellationPriceCalculation(
                cancellationFee: (float) ($systemConfig->cancellation_fee ?? 0),
                serviceType: (string) ($booking->service_type ?? 'custom')
            );

            $booking->base_price = 0;
            $booking->extras_price = 0;
            $booking->parking = 0;
            $booking->others = 0;
            $booking->airport_fees = 0;
            $booking->congestion_charge = 0;
            $booking->taxes = 0;
            $booking->taxes_amount = 0;
            $booking->gratuity = 0;
            $booking->gratuity_amount = 0;
            $booking->rate_buffer = 0;
            $booking->rate_buffer_amount = 0;
            $booking->surge_rate = 0;
            $booking->surge_rate_amount = 0;
            $booking->cancellation_fee = $priceCalculation['cancellation_fee'];
            $booking->total_price = $priceCalculation['total_price'];
            $booking->final_price = $priceCalculation['total_price'];
        }

        $booking->save();
        $freshBooking = $booking->fresh();

        $cancellationPayment = null;
        if ((string) $freshBooking->status === 'cancelled') {
            $this->syncAffiliateSettlementOnCancellation($freshBooking);
            $cancellationPayment = $this->captureCancellationPayment($freshBooking);
        }

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

        if ($cancellationPayment) {
            $response['cancellation_payment'] = $cancellationPayment;
        }

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
            'base_price',
            'extras_price',
            'parking',
            'others',
            'airport_fees',
            'congestion_charge',
            'taxes',
            'taxes_amount',
            'gratuity',
            'gratuity_amount',
            'rate_buffer',
            'rate_buffer_amount',
            'surge_rate',
            'surge_rate_amount',
            'cancellation_fee',
            'total_price',
            'final_price',
        ]);

        $systemConfig = $this->getSystemConfig($booking->company_id);
        $priceCalculation = $this->buildCancellationPriceCalculation(
            cancellationFee: (float) ($systemConfig->cancellation_fee ?? 0),
            serviceType: (string) ($booking->service_type ?? 'custom')
        );

        $booking->status = 'cancelled';
        $booking->base_price = 0;
        $booking->extras_price = 0;
        $booking->parking = 0;
        $booking->others = 0;
        $booking->airport_fees = 0;
        $booking->congestion_charge = 0;
        $booking->taxes = 0;
        $booking->taxes_amount = 0;
        $booking->gratuity = 0;
        $booking->gratuity_amount = 0;
        $booking->rate_buffer = 0;
        $booking->rate_buffer_amount = 0;
        $booking->surge_rate = 0;
        $booking->surge_rate_amount = 0;
        $booking->cancellation_fee = $priceCalculation['cancellation_fee'];
        $booking->total_price = $priceCalculation['total_price'];
        $booking->final_price = $priceCalculation['total_price'];
        $booking->save();

        $freshBooking = $booking->fresh();
        $this->syncAffiliateSettlementOnCancellation($freshBooking);
        $cancellationPayment = $this->captureCancellationPayment($freshBooking);

        $this->logBookingActivity(
            request: $request,
            booking: $freshBooking,
            action: 'booking_cancelled',
            description: 'Booking cancelled by admin/dispatcher',
            oldValues: $oldValues,
            newValues: [
                'status' => $freshBooking->status,
                'base_price' => $freshBooking->base_price,
                'extras_price' => $freshBooking->extras_price,
                'parking' => $freshBooking->parking,
                'others' => $freshBooking->others,
                'airport_fees' => $freshBooking->airport_fees,
                'congestion_charge' => $freshBooking->congestion_charge,
                'taxes' => $freshBooking->taxes,
                'taxes_amount' => $freshBooking->taxes_amount,
                'gratuity' => $freshBooking->gratuity,
                'gratuity_amount' => $freshBooking->gratuity_amount,
                'rate_buffer' => $freshBooking->rate_buffer,
                'rate_buffer_amount' => $freshBooking->rate_buffer_amount,
                'surge_rate' => $freshBooking->surge_rate,
                'surge_rate_amount' => $freshBooking->surge_rate_amount,
                'cancellation_fee' => $freshBooking->cancellation_fee,
                'total_price' => $freshBooking->total_price,
                'final_price' => $freshBooking->final_price,
            ]
        );

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'data' => $freshBooking,
            'calculation' => $this->buildCalculationBreakdown($priceCalculation),
            'cancellation_payment' => $cancellationPayment,
        ]);
    }

    /**
     * Get the company instance
     */
    private function getCompany(): ?Company
    {
        return Company::first();
    }

    private function getSystemConfig(int $companyId): ?SystemConfig
    {
        return SystemConfig::where('company_id', $companyId)->first();
    }

    /**
     * Get unavailable vehicle IDs for the given time range
     */
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

        // Exclude the current booking when updating
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

    /**
     * Calculate price for a vehicle and booking data
     */
    private function calculatePrice(Vehicle $vehicle, array $data): array
    {
        $serviceType = $data['service_type'];
        $distanceKm = (float) $data['distance_km'];
        $hours = (float) $data['hours'];
        $basePrice = (float) $data['base_price'];
        $extrasPrice = (float) $data['extras_price'];
        $taxRate = (float) $data['tax_rate'];
        $rateBuffer = (float) $data['rate_buffer'];
        $gratuityPercentage = (float) $data['gratuity_percentage'];
        $surgeRate = (float) $data['surge_rate'];
        $configuredCancellationFee = (float) $data['cancellation_fee'];
        $status = (string) ($data['status'] ?? '');
        $parking = (float) $data['parking'];
        $others = (float) $data['others'];
        $airportFees = (float) $data['airport_fees'];
        $congestionCharge = (float) $data['congestion_charge'];

        $rate = 0;
        $units = 0;

        switch ($serviceType) {
            case 'hourly':
                $rate = (float) ($vehicle->hourly_rate ?? 0);
                $units = $hours;
                break;
            case 'airport':
                $rate = (float) ($vehicle->airport_rate ?? 0);
                $units = $distanceKm;
                break;
            case 'point_to_point':
            case 'custom':
            default:
                $rate = (float) ($vehicle->per_km_rate ?? 0);
                $units = $distanceKm;
                break;
        }

        $cancellationFee = $status === 'cancelled' ? $configuredCancellationFee : 0;
        $subtotal = $basePrice + ($units * $rate) + $extrasPrice + $parking + $others + $airportFees + $congestionCharge;
        $surgeAmount = $subtotal * ($surgeRate / 100);
        $taxesAmount = ($subtotal + $surgeAmount) * ($taxRate / 100);
        $gratuityAmount = ($subtotal + $surgeAmount) * ($gratuityPercentage / 100);
        $preAuthBase = $subtotal + $surgeAmount + $taxesAmount + $gratuityAmount + $cancellationFee;
        $bufferAmount = $preAuthBase * ($rateBuffer / 100);
        $total = $preAuthBase + $bufferAmount;

        return [
            'service_type' => $serviceType,
            'rate' => $rate,
            'units' => $units,
            'base_price' => $basePrice,
            'distance_km' => $distanceKm,
            'hours' => $hours,
            'extras_price' => $extrasPrice,
            'tax_rate' => $taxRate,
            'rate_buffer' => $rateBuffer,
            'gratuity_percentage' => $gratuityPercentage,
            'surge_rate' => $surgeRate,
            'cancellation_fee' => $cancellationFee,
            'subtotal' => $subtotal,
            'surge_rate_amount' => $surgeAmount,
            'taxes_amount' => $taxesAmount,
            'gratuity_amount' => $gratuityAmount,
            'parking' => $parking,
            'others' => $others,
            'airport_fees' => $airportFees,
            'congestion_charge' => $congestionCharge,
            'buffer_amount' => $bufferAmount,
            'total_price' => $total,
        ];
    }

    private function buildPriceInput($data, ?SystemConfig $config = null): array
    {
        return [
            'service_type' => $data->service_type,
            'distance_km' => (float) ($data->distance_km ?? 0),
            'hours' => (float) ($data->hours ?? 0),
            'base_price' => (float) ($config->base_price_flat ?? 0),
            'extras_price' => (float) ($data->extras_price ?? 0),
            'tax_rate' => (float) ($config->tax_rate ?? 0),
            'rate_buffer' => (float) ($config->rate_buffer ?? 0),
            'gratuity_percentage' => (float) ($config->gratuity_percentage ?? 0),
            'surge_rate' => (float) ($config->surge_rate ?? 0),
            'cancellation_fee' => (float) ($config->cancellation_fee ?? 0),
            'status' => (string) ($data->status ?? ''),
            'parking' => (float) ($data->parking ?? 0),
            'others' => (float) ($data->others ?? 0),
            'airport_fees' => (float) ($data->airport_fees ?? 0),
            'congestion_charge' => (float) ($data->congestion_charge ?? 0),
        ];
    }

    private function buildCalculationBreakdown(?array $priceCalculation): ?array
    {
        if (! $priceCalculation) {
            return null;
        }

        $isHourly = ($priceCalculation['service_type'] ?? null) === 'hourly';
        $billedField = $isHourly ? 'hours' : 'km';
        $billedValue = $isHourly
            ? (float) ($priceCalculation['hours'] ?? 0)
            : (float) ($priceCalculation['distance_km'] ?? 0);

        return [
            'rate' => $priceCalculation['rate'],
            $billedField => $billedValue,
            'base_price' => $priceCalculation['base_price'],
            'extras_price' => $priceCalculation['extras_price'],
            'airport_fees' => $priceCalculation['airport_fees'],
            'congestion_charge' => $priceCalculation['congestion_charge'],
            'parking' => $priceCalculation['parking'],
            'others' => $priceCalculation['others'],
            'subtotal' => $priceCalculation['subtotal'],
            'surge_rate_percent' => $priceCalculation['surge_rate'],
            'surge_rate_amount' => $priceCalculation['surge_rate_amount'],
            'tax_rate_percent' => $priceCalculation['tax_rate'],
            'tax_amount' => $priceCalculation['taxes_amount'],
            'gratuity_percent' => $priceCalculation['gratuity_percentage'],
            'gratuity_amount' => $priceCalculation['gratuity_amount'],
            'rate_buffer_percent' => $priceCalculation['rate_buffer'],
            'rate_buffer_amount' => $priceCalculation['buffer_amount'],
            'cancellation_fee' => $priceCalculation['cancellation_fee'],
            'total_price' => $priceCalculation['total_price'],
        ];
    }

    private function percentageChange(int $today, int $yesterday): ?float
    {
        if ($yesterday === 0) {
            return null;
        }

        return round((($today - $yesterday) / $yesterday) * 100, 2);
    }

    private function sumRevenue(int $companyId, Carbon $start, Carbon $end, array $completedStatuses): float
    {
        return (float) Booking::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereBetween('pickup_time', [$start, $end])
            ->where(function ($query) use ($completedStatuses) {
                $query->whereIn('status', $completedStatuses)
                    ->orWhere('status', 'cancelled');
            })
            ->sum('final_price');
    }

    private function sumCompletedRevenue(int $companyId, Carbon $start, Carbon $end, array $completedStatuses): float
    {
        return (float) Booking::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $completedStatuses)
            ->sum('final_price');
    }

    private function countCompletedTrips(int $companyId, Carbon $start, Carbon $end, array $completedStatuses): int
    {
        return (int) Booking::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $completedStatuses)
            ->count();
    }

    private function sumAffiliatePayout(int $companyId, Carbon $start, Carbon $end): float
    {
        return (float) AffiliateBookingSettlement::query()
            ->join('bookings', 'affiliate_booking_settlements.booking_id', '=', 'bookings.id')
            ->where('bookings.company_id', $companyId)
            ->whereBetween('bookings.pickup_time', [$start, $end])
            ->where('affiliate_booking_settlements.status', 'paid')
            ->sum('affiliate_booking_settlements.affiliate_amount');
    }

    private function buildDailyRevenueSeries(int $companyId, array $completedStatuses, Carbon $endDate): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $endDate->copy()->subDays($i)->startOfDay();
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $revenue = $this->sumRevenue($companyId, $dayStart, $dayEnd, $completedStatuses);
            $days[] = [
                'date' => $date->toDateString(),
                'revenue' => round($revenue, 2),
            ];
        }

        return $days;
    }

    private function buildVehicleUtilization(int $companyId, Carbon $start, Carbon $end, array $completedStatuses): array
    {
        $rows = Booking::where('company_id', $companyId)
            ->where('payment_status', 'paid')
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
            ->where('payment_status', 'paid')
            ->whereBetween('pickup_time', [$start, $end])
            ->whereIn('status', $completedStatuses)
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, COUNT(*) as trips, SUM(final_price) as revenue')
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
                'earned_revenue' => round((float) $row->revenue, 2),
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

    private function syncAffiliateSettlementOnCancellation(Booking $booking): void
    {
        if (! $booking->affiliate_id || (string) $booking->status !== 'cancelled') {
            return;
        }

        $affiliate = Affiliate::find($booking->affiliate_id);
        if (! $affiliate) {
            return;
        }

        $settlement = AffiliateBookingSettlement::firstOrNew([
            'booking_id' => $booking->id,
        ]);

        $settlement->affiliate_id = $affiliate->id;
        $settlement->gross_amount = 0;
        $settlement->affiliate_percent = (float) ($affiliate->affiliate_payout_percent ?? 0);
        $settlement->platform_percent = (float) ($affiliate->platform_commission_percent ?? 0);
        $settlement->affiliate_amount = 0;
        $settlement->platform_amount = 0;
        $settlement->currency = strtolower((string) ($affiliate->payout_currency ?: 'usd'));
        $settlement->status = 'pending';
        $settlement->status_reason = 'booking_cancelled';
        $settlement->accepted_at = $settlement->accepted_at ?: now();
        $settlement->save();
    }

    private function buildCancellationPriceCalculation(float $cancellationFee, string $serviceType): array
    {
        return [
            'service_type' => $serviceType,
            'rate' => 0,
            'units' => 0,
            'base_price' => 0,
            'distance_km' => 0,
            'hours' => 0,
            'extras_price' => 0,
            'tax_rate' => 0,
            'rate_buffer' => 0,
            'gratuity_percentage' => 0,
            'surge_rate' => 0,
            'cancellation_fee' => $cancellationFee,
            'subtotal' => 0,
            'surge_rate_amount' => 0,
            'taxes_amount' => 0,
            'gratuity_amount' => 0,
            'parking' => 0,
            'others' => 0,
            'airport_fees' => 0,
            'congestion_charge' => 0,
            'buffer_amount' => 0,
            'total_price' => $cancellationFee,
        ];
    }

    private function captureCancellationPayment(Booking $booking): ?array
    {
        $latestPayment = BookingPayment::where('booking_id', $booking->id)
            ->latest()
            ->first();

        if (! $latestPayment) {
            return [
                'status' => 'skipped',
                'message' => 'No payment authorization found for cancellation capture.',
            ];
        }

        if ($latestPayment->status !== 'requires_capture') {
            return [
                'status' => 'skipped',
                'message' => 'Latest payment is not capturable.',
                'payment_status' => $latestPayment->status,
            ];
        }

        $finalAmount = (float) ($booking->final_price ?? 0);
        if ($finalAmount <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'Cancellation fee is 0, capture was not attempted.',
            ];
        }

        if ($finalAmount > (float) $latestPayment->authorized_amount) {
            return [
                'status' => 'failed',
                'message' => 'Cancellation fee exceeds authorized amount.',
            ];
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret_key'));
            $intent = PaymentIntent::retrieve($latestPayment->payment_intent_id);
            $capturedIntent = $intent->capture([
                'amount_to_capture' => (int) round($finalAmount * 100),
            ]);

            $latestPayment->captured_amount = $finalAmount;
            $latestPayment->amount_to_capture = $finalAmount;
            $latestPayment->status = $capturedIntent->status;
            $latestPayment->raw_payload = $capturedIntent->toArray();
            $latestPayment->save();

            $booking->payment_status = $capturedIntent->status === 'succeeded' ? 'paid' : $capturedIntent->status;
            $booking->save();

            return [
                'status' => 'captured',
                'captured_amount' => $finalAmount,
                'payment_status' => $latestPayment->status,
            ];
        } catch (ApiErrorException $e) {
            $latestPayment->failure_message = $e->getMessage();
            $latestPayment->status = 'failed';
            $latestPayment->save();

            $booking->payment_status = 'failed';
            $booking->save();

            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }
}
