<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AirportController extends Controller
{
    public function index()
    {
        $company = Company::first();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        return response()->json([
            'data' => Airport::where('company_id', $company->id)->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->persist($request);
    }

    public function update(Request $request, $id)
    {
        return $this->persist($request, $id);
    }

    public function destroy($id)
    {
        $company = Company::first();
        $airport = $company?->airports()->where('id', $id)->first();
        if (! $airport) {
            return response()->json(['message' => 'Airport not found'], 404);
        }
        $airport->delete();

        return response()->json(['message' => 'Airport deleted successfully']);
    }

    private function persist(Request $request, $id = null)
    {
        $company = Company::first();
        if (! $company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $airport = $id ? Airport::where('company_id', $company->id)->find($id) : new Airport;
        if ($id && ! $airport) {
            return response()->json(['message' => 'Airport not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:10', Rule::unique('airports')->where('company_id', $company->id)->ignore($airport->id)],
            'name' => 'required|string|max:255',
            'active' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $airport->fill($request->only(['code', 'name', 'active']));
        $airport->code = strtoupper((string) $airport->code);
        $airport->company_id = $company->id;
        $airport->save();

        return response()->json([
            'message' => $id ? 'Airport updated successfully' : 'Airport created successfully',
            'data' => $airport,
        ], $id ? 200 : 201);
    }
}
