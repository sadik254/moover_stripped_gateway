<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\VehicleClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Uploadcare\Api;
use Uploadcare\Configuration;

class VehicleClassController extends Controller
{
    public function index(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $classes = VehicleClass::where('company_id', $company->id)->get();

        return response()->json([
            'data' => $classes,
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|file|image|max:5120',
            'capacity' => 'required|integer|min:1',
            'luggage' => 'required|integer|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'per_km_rate' => 'nullable|numeric|min:0',
            'airport_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'name',
            'description',
            'capacity',
            'luggage',
            'hourly_rate',
            'per_km_rate',
            'airport_rate',
        ]);

        // Uploadcare image
        if ($request->hasFile('image')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);

            $file = $api->uploader()->fromPath(
                $request->file('image')->getPathname()
            );

            $data['image'] = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        // 🔑 THIS line auto-assigns company_id safely
        $vehicleClass = $company->vehicleClasses()->create($data);

        return response()->json([
            'message' => 'Vehicle class created successfully',
            'data' => $vehicleClass,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $vehicleClass = VehicleClass::where('company_id', $company->id)
            ->with('vehicles')
            ->where('id', $id)
            ->first();

        if (! $vehicleClass) {
            return response()->json([
                'message' => 'Vehicle class not found',
            ], 404);
        }

        return response()->json([
            'data' => $vehicleClass,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $vehicleClass = $company->vehicleClasses()->where('id', $id)->first();

        if (! $vehicleClass) {
            return response()->json([
                'message' => 'Vehicle class not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                // enforce unique name per company (ignore current record)
                \Illuminate\Validation\Rule::unique('vehicle_classes')
                    ->where(fn ($q) => $q->where('company_id', $company->id))
                    ->ignore($vehicleClass->id),
            ],
            'description' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|file|image|max:5120',
            'capacity' => 'sometimes|required|integer|min:1',
            'luggage' => 'sometimes|required|integer|min:0',
            'hourly_rate' => 'sometimes|nullable|numeric|min:0',
            'per_km_rate' => 'sometimes|nullable|numeric|min:0',
            'airport_rate' => 'sometimes|nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Uploadcare image update (only if sent)
        if ($request->hasFile('image')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);

            $file = $api->uploader()->fromPath(
                $request->file('image')->getPathname()
            );

            $vehicleClass->image = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        // Update only provided fields
        $vehicleClass->fill(
            $request->only([
                'name',
                'description',
                'capacity',
                'luggage',
                'hourly_rate',
                'per_km_rate',
                'airport_rate',
            ])
        );

        $vehicleClass->save();

        return response()->json([
            'message' => 'Vehicle class updated successfully',
            'data' => $vehicleClass,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $vehicleClass = VehicleClass::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $vehicleClass) {
            return response()->json([
                'message' => 'Vehicle class not found',
            ], 404);
        }

        $vehicleClass->delete();

        return response()->json([
            'message' => 'Vehicle class deleted successfully',
        ], 200);
    }
}
