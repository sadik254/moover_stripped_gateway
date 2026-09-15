<?php

namespace App\Http\Controllers;

use App\Mail\DriverCreatedPasswordMail;
use App\Mail\DriverPasswordResetCodeMail;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Uploadcare\Api;
use Uploadcare\Configuration;

class DriverController extends Controller
{
    public function dashboardSummary(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $totalDrivers = Driver::where('company_id', $company->id)->count();

        $onlineDrivers = Driver::where('company_id', $company->id)
            ->where('status', 'online')
            ->count();

        $onTripDrivers = Booking::where('company_id', $company->id)
            ->whereIn('status', ['assigned', 'on_route', 'in_progress'])
            ->whereNotNull('driver_id')
            ->distinct('driver_id')
            ->count('driver_id');

        $pendingApprovals = Driver::where('company_id', $company->id)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'data' => [
                'total_drivers' => $totalDrivers,
                'online_drivers' => $onlineDrivers,
                'on_trip' => $onTripDrivers,
                'pending_approvals' => $pendingApprovals,
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $drivers = Driver::where('company_id', $company->id)
            ->orderByDesc('id')
            ->get();

        $fileName = 'drivers_export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($drivers): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'driver_id',
                'name',
                'email',
                'phone',
                'status',
                'available',
                'employment_type',
                'commission',
                'license_number',
                'license_expiry',
                'vehicle_id',
                'address',
                'license_front',
                'license_back',
                'photo',
                'created_at',
                'updated_at',
            ]);

            foreach ($drivers as $driver) {
                fputcsv($handle, [
                    $driver->id,
                    $driver->name,
                    $driver->email,
                    $driver->phone,
                    $driver->status,
                    $driver->available,
                    $driver->employment_type,
                    $driver->commission,
                    $driver->license_number,
                    $driver->license_expiry,
                    $driver->vehicle_id,
                    $driver->address,
                    $driver->license_front,
                    $driver->license_back,
                    $driver->photo,
                    $driver->created_at,
                    $driver->updated_at,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function index(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $perPage = (int) $request->input('per_page', 15);
        $drivers = Driver::where('company_id', $company->id)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $drivers
        ]);
    }

    public function store(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_id' => [
                'nullable',
                Rule::exists('vehicles', 'id')
                    ->where('company_id', $company->id),
            ],
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|max:255|unique:drivers,email',
            'phone'            => 'required|string|max:255',
            'license_number'   => 'required|string|max:255',
            'license_expiry'   => 'nullable|date',
            'status'           => 'nullable|string|max:50',
            'employment_type'  => 'nullable|string|max:50',
            'commission'       => 'nullable|string|max:50',
            'license_front'    => 'nullable|file|image|max:5120',
            'license_back'     => 'nullable|file|image|max:5120',
            'address'          => 'nullable|string',
            'photo'            => 'nullable|file|image|max:5120',
            'available'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $generatedPassword = Str::upper(Str::random(6));

        $data = $request->only([
            'vehicle_id',
            'name',
            'email',
            'phone',
            'license_number',
            'license_expiry',
            'status',
            'employment_type',
            'commission',
            'address',
            'available',
        ]);
        $data['company_id'] = $company->id;
        $data['password'] = Hash::make($generatedPassword);

        if ($request->hasFile('license_front')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);
            $file = $api->uploader()->fromPath(
                $request->file('license_front')->getPathname()
            );

            $data['license_front'] = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        if ($request->hasFile('license_back')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);
            $file = $api->uploader()->fromPath(
                $request->file('license_back')->getPathname()
            );

            $data['license_back'] = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        if ($request->hasFile('photo')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);
            $file = $api->uploader()->fromPath(
                $request->file('photo')->getPathname()
            );

            $data['photo'] = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        $driver = Driver::create($data);
        $this->sendDriverCredentialsEmail($driver, $generatedPassword);

        return response()->json([
            'message' => 'Driver created successfully',
            'data'    => $driver
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $driver = Driver::where('email', $request->email)->first();

        if (! $driver || ! Hash::check($request->password, (string) $driver->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $plainTextToken = $driver->createToken('DriverLogin', ['driver'])->plainTextToken;
        $token = explode('|', $plainTextToken)[1];

        return response()->json([
            'token' => $token,
            'driver_id' => $driver->id,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'status' => $driver->status,
            ],
            'message' => 'Login successful',
        ], 200);
    }

    public function me(Request $request)
    {
        $driver = $request->user();

        if (! $driver instanceof Driver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'status' => $driver->status,
                'employment_type' => $driver->employment_type,
                'license_expiry' => $driver->license_expiry,
                'address' => $driver->address,
                'photo' => $driver->photo,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $driver = $request->user();

        if (! $driver instanceof Driver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $driver->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function updatePassword(Request $request)
    {
        $driver = $request->user();

        if (! $driver instanceof Driver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! Hash::check($request->old_password, (string) $driver->password)) {
            return response()->json([
                'message' => 'Old password is incorrect',
            ], 422);
        }

        $driver->password = Hash::make($request->new_password);
        $driver->save();

        return response()->json([
            'message' => 'Password updated successfully',
        ], 200);
    }

    public function updateBookingStatus(Request $request, $id)
    {
        $driver = $request->user();

        if (! $driver instanceof Driver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['on_route', 'done'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking = Booking::where('driver_id', $driver->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->status = (string) $request->status;
        $booking->save();

        return response()->json([
            'message' => 'Booking status updated successfully',
            'data' => $booking->fresh(),
        ]);
    }

    public function myBookings(Request $request)
    {
        $driver = $request->user();

        if (! $driver instanceof Driver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'confirmed', 'assigned', 'on_route', 'completed', 'cancelled'])],
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Booking::with([
            'stops:id,booking_id,address,position',
            'customer:id,name,email,phone',
            'vehicle:id,name,vehicle_class_id,plate_number,color,model,image',
        ])
            ->where('driver_id', $driver->id)
            ->orderByDesc('pickup_time');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $bookings = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $bookings,
        ]);
    }

    public function requestPasswordResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $driver = Driver::where('email', $request->email)->first();
        if (! $driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        $driver->password_reset_code = $this->generateResetCode();
        $driver->password_reset_code_sent_at = now();
        $driver->save();

        $this->sendPasswordResetCodeEmail($driver);

        return response()->json([
            'message' => 'Password reset code sent successfully',
        ], 200);
    }

    public function resetPasswordWithCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'reset_code' => 'required|string|size:6',
            'new_password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $driver = Driver::where('email', $request->email)->first();
        if (! $driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        if (! $driver->password_reset_code || ! $driver->password_reset_code_sent_at) {
            return response()->json(['message' => 'No active reset request found'], 422);
        }

        if ((string) $driver->password_reset_code !== (string) $request->reset_code) {
            return response()->json(['message' => 'Invalid reset code'], 422);
        }

        $expiresAt = Carbon::parse($driver->password_reset_code_sent_at)->addMinutes(15);
        if (now()->gt($expiresAt)) {
            $driver->password_reset_code = null;
            $driver->password_reset_code_sent_at = null;
            $driver->save();

            return response()->json(['message' => 'Reset code expired. Please request a new code.'], 422);
        }

        $driver->password = Hash::make($request->new_password);
        $driver->password_reset_code = null;
        $driver->password_reset_code_sent_at = null;
        $driver->save();

        return response()->json([
            'message' => 'Password reset successfully',
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $driver = Driver::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $driver) {
            return response()->json([
                'message' => 'Driver not found'
            ], 404);
        }

        return response()->json([
            'data' => $driver
        ]);
    }

    public function update(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $driver = Driver::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $driver) {
            return response()->json([
                'message' => 'Driver not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_id' => [
                'sometimes',
                'nullable',
                Rule::exists('vehicles', 'id')
                    ->where('company_id', $company->id),
            ],
            'name'             => 'sometimes|required|string|max:255',
            'email'            => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('drivers', 'email')->ignore($driver->id),
            ],
            'phone'            => 'sometimes|required|string|max:255',
            'license_number'   => 'sometimes|required|string|max:255',
            'license_expiry'   => 'sometimes|nullable|date',
            'status'           => 'sometimes|nullable|string|max:50',
            'employment_type'  => 'sometimes|nullable|string|max:50',
            'commission'       => 'sometimes|nullable|string|max:50',
            'license_front'    => 'sometimes|nullable|file|image|max:5120',
            'license_back'     => 'sometimes|nullable|file|image|max:5120',
            'address'          => 'sometimes|nullable|string',
            'photo'            => 'sometimes|nullable|file|image|max:5120',
            'available'        => 'sometimes|nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('license_front')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);
            $file = $api->uploader()->fromPath(
                $request->file('license_front')->getPathname()
            );

            $driver->license_front = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        if ($request->hasFile('license_back')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);
            $file = $api->uploader()->fromPath(
                $request->file('license_back')->getPathname()
            );

            $driver->license_back = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        if ($request->hasFile('photo')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);
            $file = $api->uploader()->fromPath(
                $request->file('photo')->getPathname()
            );

            $driver->photo = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        $driver->fill(
            $request->only([
                'vehicle_id',
                'name',
                'email',
                'phone',
                'license_number',
                'license_expiry',
                'status',
                'employment_type',
                'commission',
                'address',
                'available',
            ])
        );

        $driver->save();

        return response()->json([
            'message' => 'Driver updated successfully',
            'data'    => $driver
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $driver = Driver::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $driver) {
            return response()->json([
                'message' => 'Driver not found'
            ], 404);
        }

        $driver->delete();

        return response()->json([
            'message' => 'Driver deleted successfully'
        ], 200);
    }

    private function sendDriverCredentialsEmail(Driver $driver, string $generatedPassword): void
    {
        try {
            Mail::to($driver->email)->send(new DriverCreatedPasswordMail(
                driver: $driver,
                generatedPassword: $generatedPassword
            ));
        } catch (\Throwable $e) {
            Log::warning('Driver credentials mail failed', [
                'driver_id' => $driver->id,
                'email' => $driver->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateResetCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendPasswordResetCodeEmail(Driver $driver): void
    {
        try {
            Mail::to($driver->email)->send(new DriverPasswordResetCodeMail(
                driver: $driver,
                resetCode: (string) $driver->password_reset_code
            ));
        } catch (\Throwable $e) {
            Log::warning('Driver password reset mail failed', [
                'driver_id' => $driver->id,
                'email' => $driver->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
