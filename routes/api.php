<?php

use App\Http\Controllers\VehicleClassController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AffiliateclickController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateDriverController;
use App\Http\Controllers\FormsubmissionController;
use App\Http\Controllers\FormtemplateController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\SystemConfigController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\BookingLiveLocationController;
use App\Http\Controllers\AffiliateSettlementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Broadcasting\BroadcastController;


// Register and login routes for Users without authentication middleware
Route::post('user/register', [UserController::class, 'register']);
Route::post('user/login', [UserController::class, 'login']);
// Route::post('user/forgot-password', [UserController::class, 'forgotPassword']);

// Admin: create dispatcher
Route::middleware(['auth:sanctum', 'user.only:admin'])->post('user/create-dispatcher', [UserController::class, 'createDispatcher']);

// Update user profile route with authentication middleware
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/user/update', [UserController::class, 'update']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('user', [UserController::class, 'show']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/user/logout', [UserController::class, 'logout']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/user/update-password', [UserController::class, 'updatePassword']);

// Authenticated Company Routes
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/company', [CompanyController::class, 'store']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('/company', [CompanyController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/company/update', [CompanyController::class, 'update']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->delete('/company', [CompanyController::class, 'destroy']);

// System Config Routes
Route::get('/system-config', [SystemConfigController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin'])->post('/system-config', [SystemConfigController::class, 'store']);
Route::middleware(['auth:sanctum', 'user.only:admin'])->post('/system-config/update', [SystemConfigController::class, 'update']);

// Authenticated Vehicle Class Routes
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('/vehicle-classes', [VehicleClassController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/vehicle-classes', [VehicleClassController::class, 'store']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('/vehicle-classes/{id}', [VehicleClassController::class, 'show']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/vehicle-classes/update/{id}', [VehicleClassController::class, 'update']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->delete('/vehicle-classes/{id}', [VehicleClassController::class, 'destroy']);

// Authenticated Vehicle Routes
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('/vehicles', [VehicleController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/vehicles', [VehicleController::class, 'store']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('/vehicles/{id}', [VehicleController::class, 'show']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('/vehicles/update/{id}', [VehicleController::class, 'update']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->delete('/vehicles/{id}', [VehicleController::class, 'destroy']);

// Authenticated Driver Routes For Admin & Dispatcher
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('drivers', [DriverController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('drivers/dashboard-summary', [DriverController::class, 'dashboardSummary']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('drivers/export/csv', [DriverController::class, 'exportCsv']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('drivers', [DriverController::class, 'store']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('drivers/{id}', [DriverController::class, 'show']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('drivers/update/{id}', [DriverController::class, 'update']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->delete('drivers/{id}', [DriverController::class, 'destroy']);

// Driver auth routes (abilities-based)
Route::post('driver/login', [DriverController::class, 'login']);
Route::post('driver/request-password-reset-code', [DriverController::class, 'requestPasswordResetCode']);
Route::post('driver/reset-password-with-code', [DriverController::class, 'resetPasswordWithCode']);
Route::middleware(['auth:sanctum', 'abilities:driver'])->get('driver/me', [DriverController::class, 'me']);
Route::middleware(['auth:sanctum', 'abilities:driver'])->get('driver/bookings', [DriverController::class, 'myBookings']);
Route::middleware(['auth:sanctum', 'abilities:driver'])->post('driver/bookings/{id}/status', [DriverController::class, 'updateBookingStatus']);
Route::middleware(['auth:sanctum', 'abilities:driver'])->post('driver/bookings/{id}/location', [BookingLiveLocationController::class, 'updateFromDriver']);
Route::middleware(['auth:sanctum', 'abilities:driver'])->post('driver/logout', [DriverController::class, 'logout']);
Route::middleware(['auth:sanctum', 'abilities:driver'])->post('driver/update-password', [DriverController::class, 'updatePassword']);

// Customer auth routes (abilities-based)
Route::post('customer/register', [CustomerController::class, 'register']);
Route::post('customer/verify-registration-code', [CustomerController::class, 'verifyRegistrationCode']);
Route::post('customer/resend-verification-code', [CustomerController::class, 'resendVerificationCode']);
Route::post('customer/request-reset-password-code', [CustomerController::class, 'requestPasswordResetCode']);
Route::post('customer/reset-password-with-code', [CustomerController::class, 'resetPasswordWithCode']);
Route::post('customer/login', [CustomerController::class, 'login']);
Route::middleware(['auth:sanctum', 'abilities:customer'])->post('customer/logout', [CustomerController::class, 'logout']);
Route::middleware(['auth:sanctum', 'abilities:customer'])->post('customer/self-update', [CustomerController::class, 'selfUpdate']);

// Authenticated Customer Routes For Admin & Dispatcher
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('customers', [CustomerController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('customers/dashboard-summary', [CustomerController::class, 'dashboardSummary']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('customers/export/csv', [CustomerController::class, 'exportCsv']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('customers', [CustomerController::class, 'store']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('customers/{id}', [CustomerController::class, 'show']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('customers/update/{id}', [CustomerController::class, 'update']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->delete('customers/{id}', [CustomerController::class, 'destroy']);

// Booking routes
Route::post('bookings', [BookingController::class, 'store']); // public: quote + create
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings', [BookingController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/export/csv', [BookingController::class, 'exportCsv']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/dashboard-summary', [BookingController::class, 'dashboardSummary']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/report', [BookingController::class, 'reportSummary']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/pending-collections-report', [BookingController::class, 'pendingCollectionsReport']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/live-operations-feed', [BookingController::class, 'liveOperationsFeed']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/vehicle-availability', [BookingController::class, 'vehicleAvailability']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/recent-activity', [BookingController::class, 'recentActivity']);
Route::middleware(['auth:sanctum', 'abilities:customer'])->get('customer/bookings', [BookingController::class, 'customerBookings']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/{id}', [BookingController::class, 'show']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('bookings/{id}/live-location', [BookingLiveLocationController::class, 'showForAdmin']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('bookings/update/{id}', [BookingController::class, 'update']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('bookings/{id}/assign-driver', [BookingController::class, 'assignDriver']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('bookings/{id}/assign-affiliate', [BookingController::class, 'assignAffiliate']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('bookings/{id}/update-status', [BookingController::class, 'updateStatusOnly']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('bookings/{id}/cancel', [BookingController::class, 'cancelBooking']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->delete('bookings/{id}', [BookingController::class, 'destroy']);

// Affiliate settlement routes (admin/dispatcher)
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('affiliate-settlements', [AffiliateSettlementController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('affiliate-settlements/{id}', [AffiliateSettlementController::class, 'show']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('affiliate-settlements/{id}/disburse', [AffiliateSettlementController::class, 'disburse']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('affiliate-disbursements', [AffiliateSettlementController::class, 'disbursements']);

// Booking payment routes
Route::post('payments/webhook/stripe', [BookingPaymentController::class, 'webhook']);
Route::middleware(['auth:sanctum'])->get('bookings/{id}/payment', [BookingPaymentController::class, 'show']);
Route::post('bookings/{id}/payment/authorize', [BookingPaymentController::class, 'authorizePayment']);
Route::middleware(['auth:sanctum'])->post('bookings/{id}/payment/capture', [BookingPaymentController::class, 'capturePayment']);

// Broadcast auth for Sanctum tokens (private channels from SPA/dashboard)
Route::middleware(['auth:sanctum'])->post('broadcasting/auth', [BroadcastController::class, 'authenticate']);

// Route::apiResource('formtemplates', FormtemplateController::class);

// Route::apiResource('formsubmissions', FormsubmissionController::class);

// Authenticated Affiliate Routes For Admin & Dispatcher
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('affiliates', [AffiliateController::class, 'index']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('affiliates', [AffiliateController::class, 'store']);
Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->get('affiliates/{id}', [AffiliateController::class, 'show']);
// Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->post('affiliates/update/{id}', [AffiliateController::class, 'update']);
// Route::middleware(['auth:sanctum', 'user.only:admin,dispatcher'])->delete('affiliates/{id}', [AffiliateController::class, 'destroy']);


// Affiliate auth route
Route::post('affiliate/login', [AffiliateController::class, 'login']);
Route::post('affiliate/request-password-reset-code', [AffiliateController::class, 'requestPasswordResetCode']);
Route::post('affiliate/reset-password-with-code', [AffiliateController::class, 'resetPasswordWithCode']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->get('affiliate/me', [AffiliateController::class, 'me']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->post('affiliate/logout', [AffiliateController::class, 'logout']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->post('affiliate/update-password', [AffiliateController::class, 'updatePassword']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->get('affiliate/bookings', [AffiliateController::class, 'bookings']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->get('affiliate/bookings/{id}', [AffiliateController::class, 'showBooking']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->post('affiliate/bookings/{id}/accept', [AffiliateController::class, 'acceptBooking']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->post('affiliate/bookings/{id}/reject', [AffiliateController::class, 'rejectBooking']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->post('affiliate/bookings/{id}/status', [AffiliateController::class, 'updateBookingStatus']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->get('affiliate/drivers', [AffiliateDriverController::class, 'index']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->post('affiliate/drivers', [AffiliateDriverController::class, 'store']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->get('affiliate/drivers/{id}', [AffiliateDriverController::class, 'show']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->post('affiliate/drivers/{id}/update', [AffiliateDriverController::class, 'update']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->delete('affiliate/drivers/{id}', [AffiliateDriverController::class, 'destroy']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->get('affiliate/settlements', [AffiliateSettlementController::class, 'mySettlements']);
Route::middleware(['auth:sanctum', 'abilities:affiliate'])->get('affiliate/disbursements', [AffiliateSettlementController::class, 'myDisbursements']);

// Route::apiResource('affiliateclicks', AffiliateclickController::class);
