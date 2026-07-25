<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Booking;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NonFinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_config_only_persists_branding_fields(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);

        Company::create([
            'name' => 'Test Company',
            'email' => 'company@example.com',
            'phone' => '1234567890',
            'timezone' => 'UTC',
            'user_id' => $admin->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/system-config', [
            'service_zones' => ['Zone A', 'Zone B'],
            'platform_name' => 'Client Brand',
            'primary_brand_color' => '#111111',
            'secondary_brand_color' => '#eeeeee',
            'tax_rate' => 15,
            'currency' => 'usd',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.platform_name', 'Client Brand');
        $response->assertJsonMissingPath('data.tax_rate');
        $response->assertJsonMissingPath('data.currency');
    }

    public function test_affiliate_booking_acceptance_stays_operational_only(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);

        $affiliateUser = User::create([
            'name' => 'Affiliate',
            'email' => 'affiliate@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'affiliate',
        ]);

        $company = Company::create([
            'name' => 'Test Company',
            'email' => 'company2@example.com',
            'phone' => '1234567890',
            'timezone' => 'UTC',
            'user_id' => $admin->id,
        ]);

        $affiliate = Affiliate::create([
            'company_id' => $company->id,
            'user_id' => $affiliateUser->id,
            'name' => 'Affiliate',
            'email' => 'affiliate@example.com',
            'phone' => '1234567890',
            'status' => 'active',
            'address' => 'Main Street',
        ]);

        $booking = Booking::create([
            'company_id' => $company->id,
            'affiliate_id' => $affiliate->id,
            'service_type' => 'point_to_point',
            'pickup_address' => 'Pickup Point',
            'pickup_time' => now()->addHour(),
            'passengers' => 2,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($affiliateUser, ['affiliate']);

        $response = $this->postJson("/api/affiliate/bookings/{$booking->id}/accept");

        $response->assertOk();
        $response->assertJsonPath('data.affiliate_status', 'accepted');
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'affiliate_status' => 'accepted',
        ]);
    }
}
