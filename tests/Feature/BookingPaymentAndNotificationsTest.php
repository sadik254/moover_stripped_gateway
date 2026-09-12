<?php

namespace Tests\Feature;

use App\Mail\BookingCreatedMail;
use App\Models\Company;
use App\Models\User;
use App\Models\Vehicle;
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

        $payload = [
            'name' => 'Booking Contact',
            'email' => 'booking.contact@example.com',
            'service_type' => 'point_to_point',
            'pickup_address' => '123 Pickup St',
            'pickup_time' => now()->addHour()->toISOString(),
            'passengers' => 3,
            'distance_km' => 10,
        ];

        $quote = $this->postJson('/api/bookings', $payload);
        $quote->assertOk();
        $quote->assertJsonPath('data.service_type', 'point_to_point');
        $quote->assertJsonPath('data.vehicle_options.0.vehicle_id', 1);

        $response = $this->postJson('/api/bookings', $payload + ['vehicle_id' => 1]);

        $response->assertCreated();
        $response->assertJsonPath('data.service_type', 'point_to_point');
        $response->assertJsonPath('data.distance_km', 10);
        $response->assertJsonStructure(['calculation' => ['total_price']]);
        Mail::assertSent(BookingCreatedMail::class, 2);
        Mail::assertSent(BookingCreatedMail::class, fn (BookingCreatedMail $mail): bool => $mail->hasTo('booking.contact@example.com') && ! $mail->isAdminCopy
        );
        Mail::assertSent(BookingCreatedMail::class, fn (BookingCreatedMail $mail): bool => $mail->hasTo('reservations@squarelimo.com') && $mail->isAdminCopy
        );
    }
}
