<?php

namespace App\Http\Controllers;

use App\Mail\AffiliateCreatedPasswordMail;
use App\Mail\AffiliatePasswordResetCodeMail;
use App\Models\Affiliate;
use App\Models\AffiliateDriver;
use App\Models\Booking;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $company = Company::first();

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

        $perPage = (int) $request->input('per_page', 15);
        $affiliates = Affiliate::where('company_id', $company->id)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json(['data' => $affiliates]);
    }

    public function store(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:affiliates,email|unique:users,email',
            'phone' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $generatedPassword = Str::upper(Str::random(6));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($generatedPassword),
            'user_type' => 'affiliate',
        ]);

        $affiliate = Affiliate::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => $request->status,
            'address' => $request->address,
        ]);

        $this->sendAffiliateCredentialsEmail($affiliate, $generatedPassword);

        return response()->json([
            'message' => 'Affiliate created successfully and credentials sent by email.',
            'data' => $affiliate,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $affiliate = Affiliate::where('company_id', $company->id)->find($id);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        return response()->json(['data' => $affiliate]);
    }

    public function update(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $affiliate = Affiliate::where('company_id', $company->id)->find($id);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('affiliates', 'email')->ignore($affiliate->id),
                Rule::unique('users', 'email')->ignore($affiliate->user_id),
            ],
            'phone' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|nullable|string|max:50',
            'address' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $affiliate->fill($request->only([
            'name',
            'email',
            'phone',
            'status',
            'address',
        ]));
        $affiliate->save();

        if ($affiliate->user_id) {
            $user = User::find($affiliate->user_id);
            if ($user) {
                $user->name = $affiliate->name;
                $user->email = $affiliate->email;
                $user->save();
            }
        }

        return response()->json([
            'message' => 'Affiliate updated successfully',
            'data' => $affiliate,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $affiliate = Affiliate::where('company_id', $company->id)->find($id);

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        if ($affiliate->user_id) {
            $user = User::find($affiliate->user_id);
            if ($user) {
                $user->delete();
            }
        }

        $affiliate->delete();

        return response()->json(['message' => 'Affiliate deleted successfully'], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('user_type', 'affiliate')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        $plainTextToken = $user->createToken('AffiliateLogin', ['affiliate'])->plainTextToken;
        $token = explode('|', $plainTextToken)[1];

        return response()->json([
            'token' => $token,
            'user_id' => $user->id,
            'affiliate_id' => $affiliate->id,
            'message' => 'Login successful',
        ], 200);
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

        $user = User::where('email', $request->email)
            ->where('user_type', 'affiliate')
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Affiliate user not found'], 404);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        $user->password_reset_code = $this->generateResetCode();
        $user->password_reset_code_sent_at = now();
        $user->save();

        $this->sendAffiliatePasswordResetCodeEmail($affiliate, (string) $user->password_reset_code);

        return response()->json(['message' => 'Password reset code sent successfully'], 200);
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

        $user = User::where('email', $request->email)
            ->where('user_type', 'affiliate')
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Affiliate user not found'], 404);
        }

        if (! $user->password_reset_code || ! $user->password_reset_code_sent_at) {
            return response()->json(['message' => 'No active reset request found'], 422);
        }

        if ((string) $user->password_reset_code !== (string) $request->reset_code) {
            return response()->json(['message' => 'Invalid reset code'], 422);
        }

        $expiresAt = Carbon::parse($user->password_reset_code_sent_at)->addMinutes(15);
        if (now()->gt($expiresAt)) {
            $user->password_reset_code = null;
            $user->password_reset_code_sent_at = null;
            $user->save();

            return response()->json(['message' => 'Reset code expired. Please request a new code.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->password_reset_code = null;
        $user->password_reset_code_sent_at = null;
        $user->save();

        return response()->json(['message' => 'Password reset successfully'], 200);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User || $user->user_type !== 'affiliate') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();

        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'affiliate_id' => $affiliate->id,
                'name' => $affiliate->name,
                'email' => $affiliate->email,
                'phone' => $affiliate->phone,
                'status' => $affiliate->status,
                'address' => $affiliate->address,
                'created_at' => $affiliate->created_at,
                'updated_at' => $affiliate->updated_at,
            ],
        ]);
    }

    public function bookings(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User || $user->user_type !== 'affiliate') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'affiliate_status' => ['sometimes', 'nullable', Rule::in(['offered', 'accepted', 'rejected', 'in_progress', 'completed', 'cancelled'])],
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Booking::with([
                'customer:id,name,email,phone',
                'vehicle:id,name,plate_number,color,model,image',
                'assignedAffiliateDriver:id,name,email,phone,status',
            ])
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('id');

        if ($request->filled('affiliate_status')) {
            $query->where('affiliate_status', $request->affiliate_status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $bookings = $query->paginate($perPage)->withQueryString();

        return response()->json(['data' => $bookings]);
    }

    public function showBooking(Request $request, $id)
    {
        $user = $request->user();

        if (! $user instanceof User || $user->user_type !== 'affiliate') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        $booking = Booking::with([
                'customer:id,name,email,phone',
                'vehicle:id,name,plate_number,color,model,image',
                'assignedAffiliateDriver:id,name,email,phone,status',
            ])
            ->where('affiliate_id', $affiliate->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json(['data' => $booking]);
    }

    public function acceptBooking(Request $request, $id)
    {
        return $this->updateAffiliateBookingState($request, $id, 'accepted');
    }

    public function rejectBooking(Request $request, $id)
    {
        return $this->updateAffiliateBookingState($request, $id, 'rejected');
    }

    public function updateBookingStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'affiliate_status' => ['required', Rule::in(['assigned_driver', 'in_progress', 'completed', 'cancelled'])],
            'assigned_affiliate_driver_id' => 'sometimes|nullable|integer',
            'affiliate_reference' => 'sometimes|nullable|string|max:255',
            'affiliate_notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->updateAffiliateBookingState(
            request: $request,
            id: $id,
            targetState: (string) $request->affiliate_status
        );
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User || $user->user_type !== 'affiliate') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User || $user->user_type !== 'affiliate') {
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

        if (! Hash::check($request->old_password, (string) $user->password)) {
            return response()->json(['message' => 'Old password is incorrect'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    private function sendAffiliateCredentialsEmail(Affiliate $affiliate, string $generatedPassword): void
    {
        try {
            Mail::to($affiliate->email)->send(new AffiliateCreatedPasswordMail(
                affiliate: $affiliate,
                generatedPassword: $generatedPassword
            ));
        } catch (\Throwable $e) {
            Log::warning('Affiliate credentials mail failed', [
                'affiliate_id' => $affiliate->id,
                'email' => $affiliate->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateAffiliateBookingState(Request $request, $id, string $targetState)
    {
        $user = $request->user();

        if (! $user instanceof User || $user->user_type !== 'affiliate') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        $booking = Booking::where('affiliate_id', $affiliate->id)
            ->where('id', $id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $currentState = (string) ($booking->affiliate_status ?? 'offered');

        if (! $this->canTransitionAffiliateState($currentState, $targetState)) {
            return response()->json([
                'message' => "Invalid affiliate status transition from '{$currentState}' to '{$targetState}'",
            ], 422);
        }

        $booking->affiliate_status = $targetState;

        if ($request->has('assigned_affiliate_driver_id')) {
            $assignedDriverId = $request->assigned_affiliate_driver_id;

            if ($assignedDriverId === null || $assignedDriverId === '') {
                $booking->assigned_affiliate_driver_id = null;
            } else {
                $driver = AffiliateDriver::where('affiliate_id', $affiliate->id)
                    ->where('id', $assignedDriverId)
                    ->first();

                if (! $driver) {
                    return response()->json([
                        'message' => 'Affiliate driver not found',
                    ], 404);
                }

                if (! $this->isAffiliateDriverAvailable($booking, (int) $assignedDriverId)) {
                    return response()->json([
                        'message' => 'Affiliate driver is not available for the selected time',
                    ], 422);
                }

                $booking->assigned_affiliate_driver_id = (int) $assignedDriverId;
            }
        }

        if ($request->has('affiliate_reference')) {
            $booking->affiliate_reference = $request->affiliate_reference;
        }

        if ($request->has('affiliate_notes')) {
            $booking->affiliate_notes = $request->affiliate_notes;
        }

        $booking->save();

        return response()->json([
            'message' => 'Affiliate booking status updated successfully',
            'data' => $booking,
        ]);
    }

    private function canTransitionAffiliateState(string $currentState, string $targetState): bool
    {
        if ($currentState === $targetState) {
            return true;
        }

        $allowedTransitions = [
            'offered' => ['accepted', 'rejected'],
            'accepted' => ['assigned_driver', 'in_progress', 'cancelled'],
            'assigned_driver' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'rejected' => [],
            'completed' => [],
            'cancelled' => [],
        ];

        if (! array_key_exists($currentState, $allowedTransitions)) {
            return false;
        }

        return in_array($targetState, $allowedTransitions[$currentState], true);
    }

    private function isAffiliateDriverAvailable(Booking $booking, int $driverId): bool
    {
        if (! $booking->pickup_time) {
            return true;
        }

        $pickup = Carbon::parse($booking->pickup_time);
        $dropoff = $booking->dropoff_time ? Carbon::parse($booking->dropoff_time) : null;

        $windowStart = $dropoff ? $pickup : $pickup->copy()->subHours(2);
        $windowEnd = $dropoff ? $dropoff : $pickup->copy()->addHours(2);

        $conflict = Booking::where('assigned_affiliate_driver_id', $driverId)
            ->where('id', '!=', $booking->id)
            ->where(function ($query) use ($windowStart, $windowEnd) {
                $query->where(function ($q) use ($windowStart, $windowEnd) {
                    $q->whereNotNull('dropoff_time')
                        ->where('pickup_time', '<=', $windowEnd)
                        ->where('dropoff_time', '>=', $windowStart);
                })->orWhere(function ($q) use ($windowStart, $windowEnd) {
                    $q->whereNull('dropoff_time')
                        ->whereBetween('pickup_time', [$windowStart, $windowEnd]);
                });
            })
            ->exists();

        return ! $conflict;
    }

    private function generateResetCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendAffiliatePasswordResetCodeEmail(Affiliate $affiliate, string $resetCode): void
    {
        try {
            Mail::to($affiliate->email)->send(new AffiliatePasswordResetCodeMail(
                affiliate: $affiliate,
                resetCode: $resetCode
            ));
        } catch (\Throwable $e) {
            Log::warning('Affiliate password reset mail failed', [
                'affiliate_id' => $affiliate->id,
                'email' => $affiliate->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
