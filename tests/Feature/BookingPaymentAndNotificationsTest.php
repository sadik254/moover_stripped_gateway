<?php

namespace Tests\Feature;

use App\Mail\BookingCreatedMail;
use App\Models\Company;
use App\Models\User;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingPaymentAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_create_restores_pricing_and_keeps_notification_emails(): void
    {
        Mail::fake();

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
            'capacity' => 6,
            'luggage' => 4,
            'hourly_rate' => 40,
            'per_mile_rate' => 3.5,
            'airport_rate' => 8,
        ]);

        $tinyClass = VehicleClass::create([
            'company_id' => $company->id,
            'name' => 'Tiny',
            'capacity' => 2,
            'luggage' => 1,
            'hourly_rate' => 20,
            'per_mile_rate' => 2,
            'airport_rate' => 5,
        ]);

        $largeClass = VehicleClass::create([
            'company_id' => $company->id,
            'name' => 'XL',
            'capacity' => 8,
            'luggage' => 6,
            'hourly_rate' => 55,
            'per_mile_rate' => 5,
            'airport_rate' => 12,
        ]);

        $payload = [
            'name' => 'Booking Contact',
            'email' => 'booking.contact@example.com',
            'service_type' => 'point_to_point',
            'pickup_address' => '123 Pickup St',
            'stops' => [
                ['address' => '456 First Stop'],
                ['address' => '789 Second Stop'],
            ],
            'dropoff_address' => '100 Drop-off Ave',
            'pickup_time' => now()->addHour()->toISOString(),
            'passengers' => 4,
            'child_seats' => 2,
            'bags' => 2,
            'distance_miles' => 10,
        ];

        $quote = $this->postJson('/api/bookings', $payload);
        $quote->assertOk();
        $quote->assertJsonPath('data.service_type', 'point_to_point');
        $quote->assertJsonPath('data.vehicle_class_options.0.vehicle_class_id', $vehicleClass->id);
        $quote->assertJsonPath('data.vehicle_class_options.0.fits_passengers', true);
        $quote->assertJsonPath('data.vehicle_class_options.0.fits_luggage', true);
        $quote->assertJsonPath('data.vehicle_class_options.0.recommended', true);
        $quote->assertJsonPath('data.required_passenger_capacity', 6);
        $quote->assertJsonMissing(['vehicle_class_id' => $tinyClass->id]);
        $quote->assertJsonFragment(['vehicle_class_id' => $largeClass->id, 'capacity' => 8]);
        $this->assertSame(
            [$vehicleClass->id, $largeClass->id],
            collect($quote->json('data.vehicle_class_options'))->pluck('vehicle_class_id')->all()
        );

        $response = $this->postJson('/api/bookings', $payload + ['vehicle_class_id' => $vehicleClass->id]);

        $response->assertCreated();
        $response->assertJsonPath('data.service_type', 'point_to_point');
        $response->assertJsonPath('data.distance_miles', 10);
        $response->assertJsonPath('data.vehicle_class_id', $vehicleClass->id);
        $response->assertJsonPath('data.vehicle_id', null);
        $response->assertJsonPath('calculation.rate', 3.5);
        $response->assertJsonStructure(['calculation' => ['total_price']]);
        Mail::assertSent(BookingCreatedMail::class, 2);
        Mail::assertSent(BookingCreatedMail::class, fn (BookingCreatedMail $mail): bool => $mail->hasTo('booking.contact@example.com') && ! $mail->isAdminCopy
        );
        Mail::assertSent(BookingCreatedMail::class, fn (BookingCreatedMail $mail): bool => $mail->hasTo('reservations@squarelimo.com') && $mail->isAdminCopy
        );
        Mail::assertSent(BookingCreatedMail::class, function (BookingCreatedMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'Stop 1')
                && str_contains($html, '456 First Stop')
                && str_contains($html, 'Stop 2')
                && str_contains($html, '789 Second Stop');
        });

        $secondResponse = $this->postJson('/api/bookings', $payload + [
            'email' => 'second.booking@example.com',
            'vehicle_class_id' => $vehicleClass->id,
        ]);
        $secondResponse->assertCreated();
    }
}
