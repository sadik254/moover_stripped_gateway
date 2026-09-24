<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Company;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\VehicleClass;
use App\Models\VehicleClassAirportRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurablePricingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotes_apply_vehicle_class_distance_hourly_airport_and_charge_rules(): void
    {
        [$vehicleClass, $airport] = $this->pricingSetup();

        $cases = [
            ['payload' => ['service_type' => 'point_to_point', 'distance_miles' => 10], 'method' => 'point_to_point_flat', 'fare' => 90, 'total' => 90],
            ['payload' => ['service_type' => 'point_to_point', 'distance_miles' => 20], 'method' => 'point_to_point_minimum_hours', 'fare' => 200, 'total' => 200],
            ['payload' => ['service_type' => 'point_to_point', 'distance_miles' => 40], 'method' => 'distance', 'fare' => 160, 'total' => 160],
            ['payload' => ['service_type' => 'hourly', 'hours' => 2, 'pickup_time' => '2026-09-18 10:00:00'], 'method' => 'peak_hourly', 'fare' => 240, 'total' => 240],
            ['payload' => ['service_type' => 'airport', 'airport_id' => $airport->id], 'method' => 'airport_flat_rate', 'fare' => 150, 'total' => 150],
            [
                'payload' => [
                    'service_type' => 'custom',
                    'distance_miles' => 10,
                    'stops' => [['address' => 'First stop'], ['address' => 'Second stop']],
                    'waiting_minutes' => 30,
                    'tolls' => 10,
                ],
                'method' => 'distance',
                'fare' => 40,
                'total' => 160,
            ],
        ];

        foreach ($cases as $case) {
            $payload = array_merge([
                'name' => 'Quote Customer',
                'email' => 'quote@example.com',
                'pickup_address' => 'Manhattan',
                'pickup_time' => '2026-09-16 10:00:00',
                'passengers' => 2,
                'bags' => 1,
            ], $case['payload']);

            $response = $this->postJson('/api/bookings', $payload);
            $response
                ->assertOk()
                ->assertJsonPath('data.vehicle_class_options.0.vehicle_class_id', $vehicleClass->id)
                ->assertJsonPath('data.vehicle_class_options.0.pricing_method', $case['method'])
                ->assertJsonPath('data.vehicle_class_options.0.calculation.trip_fare', $case['fare'])
                ->assertJsonPath('data.vehicle_class_options.0.total_price', $case['total']);
            $this->assertEquals($case['total'] * 1.2, $response->json('data.vehicle_class_options.0.calculation.authorization_amount'));
        }
    }

    public function test_booking_persists_ordered_middle_stops_and_derives_the_charge(): void
    {
        [$vehicleClass] = $this->pricingSetup();

        $response = $this->postJson('/api/bookings', [
            'name' => 'Stops Customer',
            'email' => 'stops@example.com',
            'vehicle_class_id' => $vehicleClass->id,
            'service_type' => 'point_to_point',
            'pickup_address' => 'Pickup address',
            'stops' => [
                ['address' => 'First middle address'],
                ['address' => 'Second middle address'],
            ],
            'dropoff_address' => 'Drop-off address',
            'pickup_time' => '2026-09-16 10:00:00',
            'passengers' => 2,
            'bags' => 1,
            'distance_miles' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.extra_stops', 2)
            ->assertJsonPath('data.extra_stop_amount', 80)
            ->assertJsonPath('data.stops.0.address', 'First middle address')
            ->assertJsonPath('data.stops.0.position', 1)
            ->assertJsonPath('data.stops.1.address', 'Second middle address')
            ->assertJsonPath('data.stops.1.position', 2);

        $bookingId = $response->json('data.id');
        $this->actingAs(User::first(), 'sanctum')
            ->getJson("/api/bookings/{$bookingId}")
            ->assertOk()
            ->assertJsonPath('data.stops.0.address', 'First middle address')
            ->assertJsonPath('data.stops.1.address', 'Second middle address');

        $this->getJson('/api/bookings?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.data.0.stops.0.address', 'First middle address')
            ->assertJsonPath('data.data.0.stops.1.address', 'Second middle address');
    }

    public function test_same_address_with_middle_stops_uses_hourly_pricing(): void
    {
        [$vehicleClass] = $this->pricingSetup();

        $basePayload = [
            'name' => 'Pricing Customer',
            'email' => 'pricing@example.com',
            'service_type' => 'point_to_point',
            'pickup_address' => 'Manhattan',
            'dropoff_address' => 'Brooklyn',
            'pickup_time' => '2026-09-16 10:00:00',
            'passengers' => 2,
            'bags' => 1,
        ];

        $this->postJson('/api/bookings', array_merge($basePayload, [
            'pickup_address' => 'Manhattan',
            'dropoff_address' => ' manhattan ',
            'distance_miles' => 15,
            'hours' => 3,
            'stops' => [['address' => 'Brooklyn']],
        ]))->assertOk()
            ->assertJsonPath('data.vehicle_class_options.0.pricing_method', 'round_trip_hourly')
            ->assertJsonPath('data.vehicle_class_options.0.calculation.trip_fare', 300)
            ->assertJsonPath('data.vehicle_class_options.0.total_price', 340);
    }

    private function pricingSetup(): array
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'pricing-owner@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);
        $company = Company::create([
            'name' => 'Pricing Company',
            'email' => 'pricing-company@example.com',
            'user_id' => $owner->id,
        ]);
        SystemConfig::create([
            'company_id' => $company->id,
            'base_price_flat' => 0,
            'tax_rate' => 0,
            'gratuity_percentage' => 0,
            'surge_rate' => 0,
            'rate_buffer' => 20,
            'wait_time_rate' => 60,
            'waiting_grace_minutes' => 15,
            'extra_stop_fee' => 40,
            'short_distance_limit_miles' => 16.09,
            'distance_rate_start_miles' => 32.19,
            'point_to_point_minimum_hours' => 2,
            'peak_days' => ['friday', 'saturday'],
            'currency' => 'usd',
        ]);
        $vehicleClass = VehicleClass::create([
            'company_id' => $company->id,
            'name' => 'Premium Sedan',
            'capacity' => 4,
            'luggage' => 3,
            'hourly_rate' => 100,
            'peak_hourly_rate' => 120,
            'point_to_point_rate' => 90,
            'per_mile_rate' => 4,
            'extra_stop_eligible' => true,
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
            'rate' => 150,
        ]);

        return [$vehicleClass, $airport];
    }
}
