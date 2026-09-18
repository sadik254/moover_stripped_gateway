<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\VehicleClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

        $classes = VehicleClass::with('airportRates.airport')->where('company_id', $company->id)->get();

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
            'peak_hourly_rate' => 'nullable|numeric|min:0',
            'point_to_point_rate' => 'nullable|numeric|min:0',
            'fixed_km_rate' => 'nullable|required_with:fixed_km_limit|numeric|min:0',
            'fixed_km_limit' => 'nullable|required_with:fixed_km_rate|numeric|gt:0',
            'per_km_rate' => 'nullable|numeric|min:0',
            'airport_rate' => 'nullable|numeric|min:0',
            'extra_stop_eligible' => 'nullable|boolean',
            'airport_rates' => 'nullable|array',
            'airport_rates.*.airport_id' => ['required', Rule::exists('airports', 'id')->where('company_id', $company->id)],
            'airport_rates.*.rate' => 'required|numeric|min:0',
            'airport_rates.*.service_zone' => 'nullable|string|max:100',
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
            'peak_hourly_rate',
            'point_to_point_rate',
            'fixed_km_rate',
            'fixed_km_limit',
            'per_km_rate',
            'airport_rate',
            'extra_stop_eligible',
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
        $this->syncAirportRates($vehicleClass, $request->input('airport_rates'));

        return response()->json([
            'message' => 'Vehicle class created successfully',
            'data' => $vehicleClass->load('airportRates.airport'),
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
            ->with(['vehicles', 'airportRates.airport'])
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
            'peak_hourly_rate' => 'sometimes|nullable|numeric|min:0',
            'point_to_point_rate' => 'sometimes|nullable|numeric|min:0',
            'fixed_km_rate' => 'sometimes|nullable|required_with:fixed_km_limit|numeric|min:0',
            'fixed_km_limit' => 'sometimes|nullable|required_with:fixed_km_rate|numeric|gt:0',
            'per_km_rate' => 'sometimes|nullable|numeric|min:0',
            'airport_rate' => 'sometimes|nullable|numeric|min:0',
            'extra_stop_eligible' => 'sometimes|boolean',
            'airport_rates' => 'sometimes|array',
            'airport_rates.*.airport_id' => ['required', Rule::exists('airports', 'id')->where('company_id', $company->id)],
            'airport_rates.*.rate' => 'required|numeric|min:0',
            'airport_rates.*.service_zone' => 'nullable|string|max:100',
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
                'peak_hourly_rate',
                'point_to_point_rate',
                'fixed_km_rate',
                'fixed_km_limit',
                'per_km_rate',
                'airport_rate',
                'extra_stop_eligible',
            ])
        );

        $vehicleClass->save();
        if ($request->has('airport_rates')) {
            $this->syncAirportRates($vehicleClass, $request->input('airport_rates'));
        }

        return response()->json([
            'message' => 'Vehicle class updated successfully',
            'data' => $vehicleClass->load('airportRates.airport'),
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

    private function syncAirportRates(VehicleClass $vehicleClass, ?array $rates): void
    {
        if ($rates === null) {
            return;
        }

        $keptIds = [];
        foreach ($rates as $rate) {
            $record = $vehicleClass->airportRates()->updateOrCreate(
                [
                    'airport_id' => $rate['airport_id'],
                    'service_zone' => $rate['service_zone'] ?? 'Manhattan',
                ],
                ['rate' => $rate['rate']]
            );
            $keptIds[] = $record->id;
        }

        $vehicleClass->airportRates()->when($keptIds, fn ($query) => $query->whereNotIn('id', $keptIds))->delete();
    }
}
