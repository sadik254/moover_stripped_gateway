<?php

namespace App\Http\Controllers;

use App\Mail\CustomerCreatedPasswordMail;
use App\Mail\CustomerPasswordResetCodeMail;
use App\Mail\CustomerVerificationCodeMail;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CustomerController extends Controller
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

        $totalCustomers = Customer::where('company_id', $company->id)->count();

        $corporateCustomers = Customer::where('company_id', $company->id)
            ->where('customer_type', 'corporate')
            ->count();

        $affiliateCustomers = Customer::where('company_id', $company->id)
            ->where('customer_type', 'affiliate')
            ->count();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $activeThisMonth = Booking::where('company_id', $company->id)
            ->whereNotNull('customer_id')
            ->whereBetween('pickup_time', [$monthStart, $monthEnd])
            ->distinct('customer_id')
            ->count('customer_id');

        return response()->json([
            'data' => [
                'total_customers' => $totalCustomers,
                'corporate_customers' => $corporateCustomers,
                'affiliate_customers' => $affiliateCustomers,
                'active_this_month' => $activeThisMonth,
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

        $validator = Validator::make($request->all(), [
            'customer_type' => 'sometimes|nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Customer::where('company_id', $company->id)->orderByDesc('id');

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        $customers = $query->get();
        $fileName = 'customers_export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($customers): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'customer_id',
                'name',
                'email',
                'phone',
                'customer_company',
                'customer_type',
                'preferred_service_level',
                'dispatch_note',
                'notification_preference',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]);

            foreach ($customers as $customer) {
                fputcsv($handle, [
                    $customer->id,
                    $customer->name,
                    $customer->email,
                    $customer->phone,
                    $customer->customer_company,
                    $customer->customer_type,
                    $customer->preferred_service_level,
                    $customer->dispatch_note,
                    $customer->notification_preference ?: 'regular',
                    $customer->email_verified_at,
                    $customer->created_at,
                    $customer->updated_at,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // Admin: list customers for company
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
        $customers = Customer::where('company_id', $company->id)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $customers
        ]);
    }

    // Admin: create customer with auto-generated password
    public function store(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'                    => 'nullable|string|max:255',
            'email'                   => 'required|email|max:255|unique:customers,email',
            'phone'                   => 'required|string|max:255',
            'customer_company'        => 'nullable|string|max:255',
            'customer_type'           => 'nullable|string|max:100',
            'dispatch_note'           => 'nullable|string',
            'preferred_service_level' => 'nullable|string|max:100',
            'notification_preference' => ['nullable', Rule::in(['regular', 'dnd'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $generatedPassword = Str::upper(Str::random(6));

        $data = $request->only([
            'name',
            'email',
            'phone',
            'customer_company',
            'customer_type',
            'dispatch_note',
            'preferred_service_level',
            'notification_preference',
        ]);
        $data['company_id'] = $company->id;
        $data['password'] = Hash::make($generatedPassword);
        $data['email_verified_at'] = now();
        $data['email_verification_code'] = null;

        $customer = Customer::create($data);
        $this->sendCustomerCredentialsEmail($customer, $generatedPassword);

        return response()->json([
            'message' => 'Customer created successfully and credentials sent by email.',
            'data' => $customer,
        ], 201);
    }

    // Customer: self register with own password
    public function register(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'                    => 'required|string|max:255',
            'email'                   => 'required|email|max:255|unique:customers,email',
            'phone'                   => 'required|string|max:255',
            'password'                => 'required|min:6|confirmed',
            'customer_company'        => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'name',
            'email',
            'phone',
            'customer_company',
        ]);
        $data['company_id'] = $company->id;
        $data['password'] = Hash::make($request->password);
        $data['email_verification_code'] = $this->generateVerificationCode();
        $data['email_verified_at'] = null;

        $customer = Customer::create($data);
        $this->sendVerificationCodeEmail($customer);

        return response()->json([
            'customer_id' => $customer->id,
            'message' => 'Customer registered successfully. Please verify your email with the code sent to your inbox.',
        ], 201);
    }

    // Customer: login to get token with ability
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (is_null($customer->email_verified_at)) {
            return response()->json([
                'message' => 'Email is not verified',
            ], 403);
        }

        $plainTextToken = $customer->createToken('CustomerLogin', ['customer'])->plainTextToken;
        $token = explode('|', $plainTextToken)[1];

        return response()->json([
            'token' => $token,
            'customer_id' => $customer->id,
            'customer' => $this->customerLoginPayload($customer),
            'message' => 'Login successful',
        ], 200);
    }

    public function verifyRegistrationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        if (! is_null($customer->email_verified_at)) {
            return response()->json([
                'message' => 'Email already verified'
            ], 200);
        }

        if ((string) $customer->email_verification_code !== (string) $request->verification_code) {
            return response()->json([
                'message' => 'Invalid verification code'
            ], 422);
        }

        $customer->email_verified_at = now();
        $customer->email_verification_code = null;
        $customer->save();

        $plainTextToken = $customer->createToken('CustomerRegistrationVerified', ['customer'])->plainTextToken;
        $token = explode('|', $plainTextToken)[1];

        return response()->json([
            'message' => 'Email verified successfully',
            'customer_id' => $customer->id,
            'token' => $token,
        ], 200);
    }

    public function resendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        if (! is_null($customer->email_verified_at)) {
            return response()->json([
                'message' => 'Email already verified'
            ], 200);
        }

        $customer->email_verification_code = $this->generateVerificationCode();
        $customer->save();

        $this->sendVerificationCodeEmail($customer);

        return response()->json([
            'message' => 'Verification code sent successfully',
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
                'errors'  => $validator->errors()
            ], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        $customer->password_reset_code = $this->generateVerificationCode();
        $customer->password_reset_code_sent_at = now();
        $customer->save();

        $this->sendPasswordResetCodeEmail($customer);

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
                'errors'  => $validator->errors()
            ], 422);
        }

        $customer = Customer::where('email', $request->email)->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        if (! $customer->password_reset_code || ! $customer->password_reset_code_sent_at) {
            return response()->json([
                'message' => 'No active reset request found'
            ], 422);
        }

        if ((string) $customer->password_reset_code !== (string) $request->reset_code) {
            return response()->json([
                'message' => 'Invalid reset code'
            ], 422);
        }

        $expiresAt = Carbon::parse($customer->password_reset_code_sent_at)->addMinutes(15);
        if (now()->gt($expiresAt)) {
            $customer->password_reset_code = null;
            $customer->password_reset_code_sent_at = null;
            $customer->save();

            return response()->json([
                'message' => 'Reset code expired. Please request a new code.'
            ], 422);
        }

        $customer->password = Hash::make($request->new_password);
        $customer->password_reset_code = null;
        $customer->password_reset_code_sent_at = null;
        $customer->save();

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

        $customer = Customer::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        return response()->json([
            'data' => $customer
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

        $customer = Customer::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'                    => 'sometimes|nullable|string|max:255',
            'email'                   => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone'                   => 'sometimes|nullable|string|max:255',
            'customer_company'        => 'sometimes|nullable|string|max:255',
            'customer_type'           => 'sometimes|nullable|string|max:100',
            'dispatch_note'           => 'sometimes|nullable|string',
            'preferred_service_level' => 'sometimes|nullable|string|max:100',
            'notification_preference' => ['sometimes', 'nullable', Rule::in(['regular', 'dnd'])],
            'password'                => 'sometimes|nullable|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $customer->fill(
            $request->only([
                'name',
                'email',
                'phone',
                'customer_company',
                'customer_type',
                'dispatch_note',
                'preferred_service_level',
                'notification_preference',
            ])
        );

        if ($request->filled('password')) {
            $customer->password = Hash::make($request->password);
        }

        $customer->save();

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $customer = Customer::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully'
        ], 200);
    }

    // Customer: logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    // Customer: update own profile
    public function selfUpdate(Request $request)
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'                    => 'sometimes|nullable|string|max:255',
            'email'                   => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone'                   => 'sometimes|nullable|string|max:255',
            'customer_company'        => 'sometimes|nullable|string|max:255',
            'password'                => 'sometimes|nullable|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $customer->fill(
            $request->only([
                'name',
                'email',
                'phone',
                'customer_company',
            ])
        );

        if ($request->filled('password')) {
            $customer->password = Hash::make($request->password);
        }

        $customer->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $customer
        ], 200);
    }

    private function customerLoginPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'customer_company' => $customer->customer_company,
            'customer_type' => $customer->customer_type,
            // 'preferred_service_level' => $customer->preferred_service_level,
            // 'created_at' => $customer->created_at,
            // 'updated_at' => $customer->updated_at,
        ];
    }

    private function generateVerificationCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendVerificationCodeEmail(Customer $customer): void
    {
        try {
            Mail::to($customer->email)->send(new CustomerVerificationCodeMail(
                customer: $customer,
                verificationCode: (string) $customer->email_verification_code
            ));
        } catch (\Throwable $e) {
            Log::warning('Customer verification mail failed', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendCustomerCredentialsEmail(Customer $customer, string $generatedPassword): void
    {
        try {
            Mail::to($customer->email)->send(new CustomerCreatedPasswordMail(
                customer: $customer,
                generatedPassword: $generatedPassword
            ));
        } catch (\Throwable $e) {
            Log::warning('Customer credentials mail failed', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPasswordResetCodeEmail(Customer $customer): void
    {
        try {
            Mail::to($customer->email)->send(new CustomerPasswordResetCodeMail(
                customer: $customer,
                resetCode: (string) $customer->password_reset_code
            ));
        } catch (\Throwable $e) {
            Log::warning('Customer password reset mail failed', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
