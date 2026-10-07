<?php

namespace App\Http\Controllers;

use App\Mail\SprinterQuoteRequestMail;
use App\Models\Company;
use App\Models\VehicleClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SprinterQuoteRequestController extends Controller
{
    private const NOTIFICATION_EMAILS = 'reservations@squarelimo.com';

    public function store(Request $request)
    {
        $company = Company::first();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'vehicle_class_id' => [
                'required',
                Rule::exists('vehicle_classes', 'id')->where('company_id', $company->id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicleClass = VehicleClass::where('company_id', $company->id)
            ->where('pricing_mode', 'custom_quote')
            ->find($request->integer('vehicle_class_id'));

        if (! $vehicleClass) {
            return response()->json([
                'message' => 'This vehicle class does not accept quote requests',
            ], 422);
        }

        $quoteRequest = [
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->toString(),
            'phone' => $request->string('phone')->trim()->toString(),
        ];

        try {
            Mail::to($quoteRequest['email'])->send(new SprinterQuoteRequestMail(
                company: $company,
                vehicleClass: $vehicleClass,
                quoteRequest: $quoteRequest,
            ));
            Mail::to(self::NOTIFICATION_EMAILS)->send(new SprinterQuoteRequestMail(
                company: $company,
                vehicleClass: $vehicleClass,
                quoteRequest: $quoteRequest,
                isAdminCopy: true,
            ));
        } catch (\Throwable $e) {
            Log::warning('Sprinter quote request email failed', [
                'vehicle_class_id' => $vehicleClass->id,
                'email' => $quoteRequest['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not submit your request. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Your quote request has been submitted successfully.',
        ], 201);
    }
}
