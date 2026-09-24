<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemConfigController extends Controller
{
    public function index()
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $config = SystemConfig::where('company_id', $company->id)->first();

        if (! $config) {
            return response()->json([
                'message' => 'Config not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'config' => $config,
                'company_logo' => $company->logo,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        if (SystemConfig::where('company_id', $company->id)->first()) {
            return response()->json([
                'message' => 'Config already exists',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'tax_rate' => 'nullable|numeric|min:0',
            'base_price_flat' => 'nullable|numeric|min:0',
            'cancellation_fee' => 'nullable|numeric|min:0',
            'surge_rate' => 'nullable|numeric|min:0',
            'wait_time_rate' => 'nullable|numeric|min:0',
            'rate_buffer' => 'nullable|numeric|min:0|max:100',
            'gratuity_percentage' => 'nullable|numeric|min:0|max:100',
            'currency' => 'nullable|string|max:10',
            'service_zones' => 'nullable|array',
            'platform_name' => 'nullable|string|max:255',
            'primary_brand_color' => 'nullable|string|max:20',
            'secondary_brand_color' => 'nullable|string|max:20',
            'short_distance_limit_miles' => 'nullable|numeric|min:0',
            'distance_rate_start_miles' => 'nullable|numeric|min:0',
            'point_to_point_minimum_hours' => 'nullable|numeric|min:0',
            'peak_days' => 'nullable|array',
            'peak_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'extra_stop_fee' => 'nullable|numeric|min:0',
            'extra_stop_minutes' => 'nullable|integer|min:0',
            'waiting_grace_minutes' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'tax_rate',
            'base_price_flat',
            'cancellation_fee',
            'surge_rate',
            'wait_time_rate',
            'rate_buffer',
            'gratuity_percentage',
            'currency',
            'service_zones',
            'platform_name',
            'primary_brand_color',
            'secondary_brand_color',
            'short_distance_limit_miles',
            'distance_rate_start_miles',
            'point_to_point_minimum_hours',
            'peak_days',
            'extra_stop_fee',
            'extra_stop_minutes',
            'waiting_grace_minutes',
        ]);
        $data['company_id'] = $company->id;

        $config = SystemConfig::create($data);

        return response()->json([
            'message' => 'Config created successfully',
            'data' => $config,
        ], 201);
    }

    public function update(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $config = SystemConfig::where('company_id', $company->id)->first();

        if (! $config) {
            return response()->json([
                'message' => 'Config not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tax_rate' => 'sometimes|nullable|numeric|min:0',
            'base_price_flat' => 'sometimes|nullable|numeric|min:0',
            'cancellation_fee' => 'sometimes|nullable|numeric|min:0',
            'surge_rate' => 'sometimes|nullable|numeric|min:0',
            'wait_time_rate' => 'sometimes|nullable|numeric|min:0',
            'rate_buffer' => 'sometimes|nullable|numeric|min:0|max:100',
            'gratuity_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'currency' => 'sometimes|nullable|string|max:10',
            'service_zones' => 'sometimes|nullable|array',
            'platform_name' => 'sometimes|nullable|string|max:255',
            'primary_brand_color' => 'sometimes|nullable|string|max:20',
            'secondary_brand_color' => 'sometimes|nullable|string|max:20',
            'short_distance_limit_miles' => 'sometimes|nullable|numeric|min:0',
            'distance_rate_start_miles' => 'sometimes|nullable|numeric|min:0',
            'point_to_point_minimum_hours' => 'sometimes|nullable|numeric|min:0',
            'peak_days' => 'sometimes|nullable|array',
            'peak_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'extra_stop_fee' => 'sometimes|nullable|numeric|min:0',
            'extra_stop_minutes' => 'sometimes|nullable|integer|min:0',
            'waiting_grace_minutes' => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $config->fill($request->only([
            'tax_rate',
            'base_price_flat',
            'cancellation_fee',
            'surge_rate',
            'wait_time_rate',
            'rate_buffer',
            'gratuity_percentage',
            'currency',
            'service_zones',
            'platform_name',
            'primary_brand_color',
            'secondary_brand_color',
            'short_distance_limit_miles',
            'distance_rate_start_miles',
            'point_to_point_minimum_hours',
            'peak_days',
            'extra_stop_fee',
            'extra_stop_minutes',
            'waiting_grace_minutes',
        ]));

        $config->save();

        return response()->json([
            'message' => 'Config updated successfully',
            'data' => $config,
        ]);
    }
}
