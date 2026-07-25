<?php

namespace App\Http\Controllers;

use App\Models\SystemConfig;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemConfigController extends Controller
{
    public function index()
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $config = SystemConfig::where('company_id', $company->id)->first();

        if (! $config) {
            return response()->json([
                'message' => 'Config not found'
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
                'message' => 'Company not found'
            ], 404);
        }

        if (SystemConfig::where('company_id', $company->id)->first()) {
            return response()->json([
                'message' => 'Config already exists'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'service_zones'         => 'nullable|array',
            'platform_name'         => 'nullable|string|max:255',
            'primary_brand_color'   => 'nullable|string|max:20',
            'secondary_brand_color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'service_zones',
            'platform_name',
            'primary_brand_color',
            'secondary_brand_color',
        ]);
        $data['company_id'] = $company->id;

        $config = SystemConfig::create($data);

        return response()->json([
            'message' => 'Config created successfully',
            'data'    => $config
        ], 201);
    }

    public function update(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found'
            ], 404);
        }

        $config = SystemConfig::where('company_id', $company->id)->first();

        if (! $config) {
            return response()->json([
                'message' => 'Config not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'service_zones'         => 'sometimes|nullable|array',
            'platform_name'         => 'sometimes|nullable|string|max:255',
            'primary_brand_color'   => 'sometimes|nullable|string|max:20',
            'secondary_brand_color' => 'sometimes|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $config->fill($request->only([
            'service_zones',
            'platform_name',
            'primary_brand_color',
            'secondary_brand_color',
        ]));

        $config->save();

        return response()->json([
            'message' => 'Config updated successfully',
            'data'    => $config
        ]);
    }
}
