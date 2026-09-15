<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Company;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\VehicleClass;
use App\Models\VehicleClassAirportRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VehicleClassBookingTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_original_booking_type_quotes_and_creates_with_a_vehicle_class(): void
    {
        Mail::fake();

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'booking-types@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);
        $company = Company::create([
            'name' => 'Booking Types Company',
            'email' => 'company@example.com',
            'user_id' => $owner->id,
        ]);
        SystemConfig::create([
            'company_id' => $company->id,
            'tax_rate' => 0,
            'base_price_flat' => 0,
            'cancellation_fee' => 0,
            'surge_rate' => 0,
            'rate_buffer' => 0,
            'gratuity_percentage' => 0,
            'currency' => 'usd',
        ]);
        $vehicleClass = VehicleClass::create([
            'company_id' => $company->id,
            'name' => 'Executive',
            'capacity' => 4,
            'luggage' => 3,
            'hourly_rate' => 10,
            'per_km_rate' => 2,
            'airport_rate' => 5,
        ]);
        $airport = Airport::create([
            'company_id' => $company->id,
            'code' => 'JFK',
            'name' => 'John F. Kennedy International Airport',
        ]);
        VehicleClassAirportRate::create([
            'vehicle_class_id' => $vehicleClass->id,
            'airport_id' => $airport->id,
            'service_zone' => 'Manhattan',
            'rate' => 50,
        ]);

        $cases = [
            'point_to_point' => ['distance_km' => 10, 'rate' => 2, 'total' => 20],
            'custom' => ['distance_km' => 10, 'rate' => 2, 'total' => 20],
            'airport' => ['distance_km' => 10, 'airport_id' => $airport->id, 'rate' => 50, 'total' => 50],
            'hourly' => ['hours' => 3, 'rate' => 10, 'total' => 30],
        ];

        foreach ($cases as $serviceType => $expectation) {
            $payload = [
                'name' => ucfirst(str_replace('_', ' ', $serviceType)).' Passenger',
                'email' => "{$serviceType}@example.com",
                'service_type' => $serviceType,
                'pickup_address' => 'Airport Road',
                'pickup_time' => '2026-12-15 10:00:00',
                'passengers' => 2,
                'bags' => 2,
            ] + array_intersect_key($expectation, array_flip(['distance_km', 'hours', 'airport_id']));

            $this->postJson('/api/bookings', $payload)
                ->assertOk()
                ->assertJsonPath('data.vehicle_class_options.0.vehicle_class_id', $vehicleClass->id)
                ->assertJsonPath('data.vehicle_class_options.0.rate', $expectation['rate'])
                ->assertJsonPath('data.vehicle_class_options.0.total_price', $expectation['total'])
                ->assertJsonPath('data.vehicle_class_options.0.recommended', true);

            $this->postJson('/api/bookings', $payload + ['vehicle_class_id' => $vehicleClass->id])
                ->assertCreated()
                ->assertJsonPath('data.service_type', $serviceType)
                ->assertJsonPath('data.vehicle_class_id', $vehicleClass->id)
                ->assertJsonPath('calculation.rate', $expectation['rate'])
                ->assertJsonPath('calculation.total_price', $expectation['total']);
        }

        $this->assertDatabaseCount('bookings', 4);
        $this->assertTrue(Schema::hasColumn('bookings', 'vehicle_class_id'));
        $this->assertFalse(Schema::hasColumn('bookings', 'vehicle_id'));
    }
}
