<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Company;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_finalize_a_done_booking_with_actual_fees_within_the_authorization(): void
    {
        [$admin, $booking] = $this->bookingWithAuthorization(240);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('data.data.0.total_price', 200)
            ->assertJsonPath('data.data.0.final_price', 240)
            ->assertJsonPath('data.data.0.estimated_amount', 200)
            ->assertJsonPath('data.data.0.authorized_amount', 240)
            ->assertJsonPath('data.data.0.captured_amount', null)
            ->assertJsonPath('data.data.0.pricing_type', 'point_to_point')
            ->assertJsonPath('data.data.0.pricing_details.pricing_type', 'point_to_point')
            ->assertJsonPath('data.data.0.pricing_details.trip_fare', 200)
            ->assertJsonPath('data.data.0.pricing_details.estimated_total', 200)
            ->assertJsonPath('data.data.0.pricing_details.authorization_buffer_percent', 20)
            ->assertJsonPath('data.data.0.pricing_details.authorization_buffer_amount', 40)
            ->assertJsonPath('data.data.0.pricing_details.authorized_total', 240)
            ->assertJsonPath('data.data.0.pricing_details.display_final_price', 240);

        $response = $this->postJson("/api/bookings/{$booking->id}/finalize", [
            'parking' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_price', 200)
            ->assertJsonPath('data.final_price', 210)
            ->assertJsonPath('calculation.total_price', 210)
            ->assertJsonPath('calculation.rate_buffer_percent', 0)
            ->assertJsonPath('calculation.rate_buffer_amount', 0)
            ->assertJsonPath('calculation.authorization_amount', 210)
            ->assertJsonPath('pricing.total_price', 210)
            ->assertJsonPath('payment.original_estimated_amount', 200)
            ->assertJsonPath('payment.original_rate_buffer_percent', 20)
            ->assertJsonPath('payment.original_rate_buffer_amount', 40)
            ->assertJsonPath('payment.authorized_amount', 240)
            ->assertJsonPath('payment.amount_to_capture', 210)
            ->assertJsonPath('payment.remaining_authorization', 30)
            ->assertJsonPath('payment.unused_authorization', 30);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'completed',
            'total_price' => 200,
            'parking' => 10,
            'final_price' => 210,
            'rate_buffer' => 0,
            'rate_buffer_amount' => 0,
        ]);
        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $booking->id,
            'authorized_amount' => 240,
            'amount_to_capture' => 210,
        ]);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('data.data.0.total_price', 200)
            ->assertJsonPath('data.data.0.final_price', 240)
            ->assertJsonPath('data.data.0.authorized_amount', 240)
            ->assertJsonPath('data.data.0.amount_to_capture', 210);

        BookingPayment::where('booking_id', $booking->id)->update([
            'captured_amount' => 210,
            'status' => 'succeeded',
        ]);
        $booking->update(['payment_status' => 'paid']);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('data.data.0.total_price', 200)
            ->assertJsonPath('data.data.0.final_price', 210)
            ->assertJsonPath('data.data.0.authorized_amount', 240)
            ->assertJsonPath('data.data.0.captured_amount', 210)
            ->assertJsonPath('data.data.0.pricing_details.finalized_total', 210)
            ->assertJsonPath('data.data.0.pricing_details.captured_total', 210)
            ->assertJsonPath('data.data.0.pricing_details.display_final_price', 210);
    }

    public function test_finalization_is_rejected_when_actual_price_exceeds_the_authorization(): void
    {
        [$admin, $booking] = $this->bookingWithAuthorization(240);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/bookings/{$booking->id}/finalize", [
            'parking' => 50,
        ])->assertUnprocessable()
            ->assertJsonPath('data.final_price', 250)
            ->assertJsonPath('data.authorized_amount', 240);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'done',
            'parking' => 0,
            'final_price' => 200,
        ]);
    }

    private function bookingWithAuthorization(float $authorizedAmount): array
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'finalize@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);

        $company = Company::create([
            'name' => 'Finalize Company',
            'email' => 'company-finalize@example.com',
            'user_id' => $admin->id,
        ]);

        SystemConfig::create([
            'company_id' => $company->id,
            'base_price_flat' => 0,
            'tax_rate' => 0,
            'gratuity_percentage' => 0,
            'surge_rate' => 0,
            'rate_buffer' => 20,
            'currency' => 'usd',
        ]);

        $vehicleClass = VehicleClass::create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'capacity' => 4,
            'luggage' => 3,
            'per_mile_rate' => 2,
            'hourly_rate' => 20,
            'airport_rate' => 5,
        ]);

        $booking = Booking::create([
            'company_id' => $company->id,
            'vehicle_class_id' => $vehicleClass->id,
            'service_type' => 'point_to_point',
            'pickup_address' => 'Pickup',
            'pickup_time' => now(),
            'passengers' => 2,
            'distance_miles' => 100,
            'parking' => 0,
            'total_price' => 200,
            'final_price' => 200,
            'rate_buffer' => 20,
            'rate_buffer_amount' => 40,
            'status' => 'done',
            'payment_status' => 'authorized',
        ]);

        BookingPayment::create([
            'booking_id' => $booking->id,
            'provider' => 'stripe',
            'currency' => 'usd',
            'payment_intent_id' => 'pi_finalize_test',
            'estimated_amount' => 200,
            'authorized_amount' => $authorizedAmount,
            'status' => 'requires_capture',
        ]);

        return [$admin, $booking];
    }
}
