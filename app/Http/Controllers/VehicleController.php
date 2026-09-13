<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Uploadcare\Api;
use Uploadcare\Configuration;

class VehicleController extends Controller
{
    /**
     * List all vehicles for logged-in user's company
     */
    public function index(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $vehicles = Vehicle::where('company_id', $company->id)
            ->with([
                'vehicleClass:id,name',
            ])
            ->get();

        return response()->json([
            'data' => $vehicles,
        ]);
    }

    /**
     * Store a new vehicle
     */
    public function store(Request $request)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_class_id' => [
                'required',
                Rule::exists('vehicle_classes', 'id')
                    ->where('company_id', $company->id),
            ],
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
            'plate_number' => 'nullable|string|max:50|unique:vehicles,plate_number',
            'color' => 'nullable|string|max:50',
            'model' => 'nullable|string|max:100',
            'image' => 'nullable|file|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'vehicle_class_id',
            'name',
            'category',
            'status',
            'plate_number',
            'color',
            'model',
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

        $vehicle = $company->vehicles()->create($data);

        return response()->json([
            'message' => 'Vehicle created successfully',
            'data' => $vehicle,
        ], 201);
    }

    /**
     * Show a single vehicle
     */
    public function show(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $vehicle = Vehicle::with('vehicleClass:id,name')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $vehicle) {
            return response()->json([
                'message' => 'Vehicle not found',
            ], 404);
        }

        return response()->json([
            'data' => $vehicle,
        ]);
    }

    /**
     * Update vehicle (partial update)
     */
    public function update(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $vehicle = Vehicle::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $vehicle) {
            return response()->json([
                'message' => 'Vehicle not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_class_id' => [
                'sometimes',
                Rule::exists('vehicle_classes', 'id')
                    ->where('company_id', $company->id),
            ],
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|nullable|string|max:100',
            'status' => 'sometimes|nullable|string|max:50',
            'plate_number' => [
                'sometimes',
                'nullable',
                Rule::unique('vehicles', 'plate_number')->ignore($vehicle->id),
            ],
            'color' => 'sometimes|nullable|string|max:50',
            'model' => 'sometimes|nullable|string|max:100',
            'image' => 'sometimes|nullable|file|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            $configuration = Configuration::create(
                config('services.uploadcare.public_key'),
                config('services.uploadcare.secret_key')
            );

            $api = new Api($configuration);

            $file = $api->uploader()->fromPath(
                $request->file('image')->getPathname()
            );

            $vehicle->image = "https://ucarecdn.com/{$file->getUuid()}/-/preview/";
        }

        $vehicle->fill(
            $request->only([
                'vehicle_class_id',
                'name',
                'category',
                'status',
                'plate_number',
                'color',
                'model',
            ])
        );

        $vehicle->save();

        return response()->json([
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle,
        ]);
    }

    /**
     * Delete vehicle
     */
    public function destroy(Request $request, $id)
    {
        $company = Company::first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        $vehicle = Vehicle::where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $vehicle) {
            return response()->json([
                'message' => 'Vehicle not found',
            ], 404);
        }

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehicle deleted successfully',
        ]);
    }
}
