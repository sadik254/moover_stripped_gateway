<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingWithoutPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_create_does_not_require_pricing_or_payment_payload(): void
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);

        $company = Company::create([
            'name' => 'Test Company',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'timezone' => 'UTC',
            'user_id' => $user->id,
        ]);

        $vehicleClass = VehicleClass::create([
            'company_id' => $company->id,
            'name' => 'Standard',
        ]);

        Vehicle::create([
            'company_id' => $company->id,
            'name' => 'Test Van',
            'category' => 'van',
            'capacity' => 6,
            'luggage' => 4,
            'hourly_rate' => 40,
            'per_km_rate' => 3.5,
            'airport_rate' => 8,
            'vehicle_class_id' => $vehicleClass->id,
            'status' => 'available',
        ]);

        $response = $this->postJson('/api/bookings', [
            'service_type' => 'point_to_point',
            'pickup_address' => '123 Pickup St',
            'pickup_time' => now()->addHour()->toISOString(),
            'passengers' => 3,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.service_type', 'point_to_point');
        $response->assertJsonMissingPath('calculation');
        $response->assertJsonMissingPath('data.total_price');
        $response->assertJsonMissingPath('data.final_price');
        $response->assertJsonMissingPath('data.payment_status');
        $response->assertJsonMissingPath('data.distance_km');
    }
}
