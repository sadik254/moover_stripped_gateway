<?php

namespace Tests\Feature;

use App\Mail\BookingTrackingStartedMail;
use App\Mail\DriverTripAccessMail;
use App\Models\Booking;
use App\Models\BookingAccessLink;
use App\Models\BookingPayment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicTripAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_passwordless_driver_tracking_customer_tracking_and_receipt_pages(): void
    {
        Mail::fake();
        [$admin, $booking, $driver, $vehicle] = $this->records();

        $this->actingAs($admin, 'sanctum')->postJson("/api/bookings/{$booking->id}/assign-driver", [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertOk();

        $driverUrl = null;
        Mail::assertSent(DriverTripAccessMail::class, function (DriverTripAccessMail $mail) use (&$driverUrl): bool {
            $driverUrl = $mail->driverUrl;

            return $mail->hasTo('driver@example.com');
        });
        $driverToken = basename(parse_url($driverUrl, PHP_URL_PATH));

        $this->get($driverUrl)
            ->assertOk()
            ->assertSee('Notification Company')
            ->assertSee('Trip Dispatch')
            ->assertSee('Accept')
            ->assertSee('Reject')
            ->assertSee('Passenger')
            ->assertSee('Customer')
            ->assertSee('Trip schedule')
            ->assertDontSee('customer@example.com')
            ->assertDontSee('+12125550101')
            ->assertSee('#'.$booking->id)
            ->assertSee('navigator.wakeLock', false)
            ->assertSee("request('screen')", false);
        $this->getJson("/api/public/driver-trips/{$driverToken}")
            ->assertOk()->assertJsonPath('data.status', 'dispatched');
        $this->postJson("/api/public/driver-trips/{$driverToken}/dispatch-response", ['response' => 'accepted'])
            ->assertOk()->assertJsonPath('data.next_status', 'on_route');
        $this->postJson("/api/public/driver-trips/{$driverToken}/status", ['status' => 'on_route'])
            ->assertOk()->assertJsonPath('data.next_status', 'on_location');
        $this->postJson("/api/public/driver-trips/{$driverToken}/pause")
            ->assertOk()->assertJsonPath('data.pause_active', true);
        $this->travel(3)->minutes();
        $pauseResponse = $this->postJson("/api/public/driver-trips/{$driverToken}/pause")
            ->assertOk()
            ->assertJsonPath('data.pause_active', false);
        $recordedPauseMinutes = (int) $pauseResponse->json('data.pause_minutes');
        $this->assertGreaterThanOrEqual(3, $recordedPauseMinutes);
        $this->postJson("/api/public/driver-trips/{$driverToken}/location", [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 8,
            'heading' => 127.4,
        ])->assertOk()->assertJsonPath('data.heading', 127);
        $this->postJson("/api/public/driver-trips/{$driverToken}/status", ['status' => 'on_location'])
            ->assertOk()->assertJsonPath('data.next_status', 'pob');

        $trackingUrl = null;
        Mail::assertSent(BookingTrackingStartedMail::class, function (BookingTrackingStartedMail $mail) use (&$trackingUrl): bool {
            $trackingUrl = $mail->trackingUrl;

            return $mail->hasTo('customer@example.com');
        });
        $trackingToken = basename(parse_url($trackingUrl, PHP_URL_PATH));
        $this->get($trackingUrl)->assertOk()->assertSee('Notification Company');
        $this->getJson("/api/public/trip-tracking/{$trackingToken}")
            ->assertOk()
            ->assertJsonPath('data.driver.name', 'Assigned Driver')
            ->assertJsonPath('data.vehicle.plate_number', 'ABC-123')
            ->assertJsonPath('data.location.latitude', 40.7128);

        $this->postJson("/api/public/driver-trips/{$driverToken}/status", ['status' => 'pob'])
            ->assertOk()->assertJsonPath('data.next_status', 'done');
        $this->postJson("/api/public/driver-trips/{$driverToken}/status", ['status' => 'done'])->assertOk();
        $this->assertSame((float) $recordedPauseMinutes, (float) $booking->fresh()->waiting_minutes);
        $this->assertSame(0, (int) $booking->fresh()->driver_pause_minutes);
        $this->getJson("/api/public/driver-trips/{$driverToken}")->assertNotFound();

        BookingPayment::create([
            'booking_id' => $booking->id,
            'provider' => 'stripe',
            'currency' => 'usd',
            'payment_intent_id' => 'pi_public_receipt',
            'estimated_amount' => 100,
            'authorized_amount' => 120,
            'captured_amount' => 100,
            'amount_to_capture' => 100,
            'status' => 'succeeded',
        ]);
        [, $receiptToken] = BookingAccessLink::issue($booking, BookingAccessLink::RECEIPT, null, now()->addDays(90));
        $this->get(route('public.trip-receipt', ['token' => $receiptToken]))
            ->assertOk()
            ->assertSee('USD 100.00')
            ->assertSee('Notification Company')
            ->assertSee('Fare calculation')
            ->assertSee('Trip fare')
            ->assertSee('Authorized amount')
            ->assertSee('USD 120.00')
            ->assertSee('Authorization buffer')
            ->assertSee('USD 20.00')
            ->assertSee('Unused authorization released')
            ->assertSee('pi_public_receipt');
    }

    public function test_driver_can_reject_a_dispatched_trip_and_return_it_to_confirmed(): void
    {
        Mail::fake();
        [$admin, $booking, $driver, $vehicle] = $this->records();

        $this->actingAs($admin, 'sanctum')->postJson("/api/bookings/{$booking->id}/assign-driver", [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertOk()->assertJsonPath('data.status', 'dispatched');

        $driverUrl = null;
        Mail::assertSent(DriverTripAccessMail::class, function (DriverTripAccessMail $mail) use (&$driverUrl): bool {
            $driverUrl = $mail->driverUrl;

            return $mail->hasTo('driver@example.com');
        });
        $driverToken = basename(parse_url($driverUrl, PHP_URL_PATH));

        $this->postJson("/api/public/driver-trips/{$driverToken}/dispatch-response", ['response' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'driver_id' => null,
            'vehicle_id' => null,
        ]);
        $this->getJson("/api/public/driver-trips/{$driverToken}")->assertNotFound();
    }

    public function test_redispatch_to_the_same_driver_sends_a_fresh_link_and_revokes_the_previous_one(): void
    {
        Mail::fake();
        [$admin, $booking, $driver, $vehicle] = $this->records();

        $this->actingAs($admin, 'sanctum')->postJson("/api/bookings/{$booking->id}/assign-driver", [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertOk()->assertJsonPath('data.status', 'dispatched');

        $firstMail = Mail::sent(DriverTripAccessMail::class)->first();
        $firstToken = basename(parse_url($firstMail->driverUrl, PHP_URL_PATH));
        $this->postJson("/api/public/driver-trips/{$firstToken}/dispatch-response", ['response' => 'accepted'])
            ->assertOk()->assertJsonPath('data.status', 'assigned');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/update-status", ['status' => 'confirmed'])
            ->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->postJson("/api/bookings/{$booking->id}/assign-driver", [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])->assertOk()->assertJsonPath('data.status', 'dispatched');

        Mail::assertSent(DriverTripAccessMail::class, 2);
        $secondMail = Mail::sent(DriverTripAccessMail::class)->last();
        $secondToken = basename(parse_url($secondMail->driverUrl, PHP_URL_PATH));

        $this->assertNotSame($firstToken, $secondToken);
        $this->getJson("/api/public/driver-trips/{$firstToken}")->assertNotFound();
        $this->getJson("/api/public/driver-trips/{$secondToken}")
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched');
    }

    private function records(): array
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $company = Company::create([
            'name' => 'Notification Company',
            'email' => 'office@example.com',
            'logo' => 'https://example.com/logo.png',
            'user_id' => $admin->id,
        ]);
        $class = VehicleClass::create(['company_id' => $company->id, 'name' => 'Sedan', 'capacity' => 4, 'luggage' => 3]);
        $vehicle = Vehicle::create([
            'company_id' => $company->id, 'vehicle_class_id' => $class->id, 'name' => 'Sedan 12',
            'plate_number' => 'ABC-123', 'model' => 'S-Class', 'color' => 'Black', 'status' => 'active',
        ]);
        $driver = Driver::create([
            'company_id' => $company->id, 'name' => 'Assigned Driver', 'email' => 'driver@example.com',
            'phone' => '+12125550100', 'password' => bcrypt('secret123'), 'license_number' => 'LIC-100',
            'status' => 'online', 'available' => true,
        ]);
        $booking = Booking::create([
            'company_id' => $company->id, 'vehicle_class_id' => $class->id, 'name' => 'Customer',
            'email' => 'customer@example.com', 'phone' => '+12125550101', 'service_type' => 'point_to_point', 'pickup_address' => 'Pickup',
            'dropoff_address' => 'Drop-off', 'pickup_time' => now()->addDay(), 'passengers' => 2,
            'status' => 'confirmed', 'final_price' => 100,
        ]);

        return [$admin, $booking, $driver, $vehicle];
    }
}
