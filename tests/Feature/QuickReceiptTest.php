<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_matching_public_request_returns_a_dynamic_pdf_receipt(): void
    {
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'SquareLimo',
            'email' => 'info@squarelimo.com',
            'phone' => '+1 555 123 4567',
            'user_id' => $owner->id,
        ]);
        $booking = Booking::create([
            'company_id' => $company->id,
            'name' => 'Jane Rider',
            'email' => 'jane@example.com',
            'phone' => '+1 555 987 6543',
            'service_type' => 'airport',
            'pickup_address' => 'Airport Terminal 1',
            'dropoff_address' => 'Downtown Hotel',
            'pickup_time' => '2026-08-25 15:30:00',
            'passengers' => 2,
            'status' => 'confirmed',
        ]);

        $this->post('/api/bookings/quick-receipt', [
            'booking_id' => $booking->id,
            'email' => 'jane@example.com',
            'trip_date' => '2026-08-25',
        ])->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', "inline; filename=quick-receipt-{$booking->id}.pdf");
    }

    public function test_the_receipt_is_not_returned_when_the_booking_details_do_not_match(): void
    {
        $this->postJson('/api/bookings/quick-receipt', [
            'booking_id' => 999,
            'email' => 'not-the-booker@example.com',
            'trip_date' => '2026-08-25',
        ])->assertNotFound()
            ->assertJson(['message' => 'Booking not found']);
    }
}
