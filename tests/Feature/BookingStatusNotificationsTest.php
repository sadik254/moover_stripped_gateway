<?php

namespace Tests\Feature;

use App\Mail\BookingDriverAssignedMail;
use App\Mail\BookingStatusChangedMail;
use App\Mail\BookingTrackingStartedMail;
use App\Mail\DriverTripAccessMail;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingStatusNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_only_assignment_arrival_and_completion_trip_updates(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['user_type' => 'admin']);
        $company = Company::create([
            'name' => 'Notification Company',
            'email' => 'office@example.com',
            'user_id' => $admin->id,
        ]);
        $vehicleClass = VehicleClass::create([
            'company_id' => $company->id,
            'name' => 'Executive Sedan',
            'capacity' => 4,
            'luggage' => 3,
        ]);
        $vehicle = Vehicle::create([
            'company_id' => $company->id,
            'vehicle_class_id' => $vehicleClass->id,
            'name' => 'Sedan 12',
            'plate_number' => 'ABC-123',
            'color' => 'Black',
            'model' => 'S-Class',
            'status' => 'active',
        ]);
        $driver = Driver::create([
            'company_id' => $company->id,
            'name' => 'Assigned Driver',
            'email' => 'driver@example.com',
            'phone' => '+12125550100',
            'password' => bcrypt('secret123'),
            'license_number' => 'LIC-100',
            'status' => 'online',
            'available' => true,
            'photo' => 'https://example.com/driver.jpg',
        ]);
        $booking = Booking::create([
            'company_id' => $company->id,
            'vehicle_class_id' => $vehicleClass->id,
            'name' => 'Customer Name',
            'email' => 'customer@example.com',
            'service_type' => 'point_to_point',
            'pickup_address' => 'Pickup',
            'dropoff_address' => 'Drop-off',
            'pickup_time' => now()->addDay(),
            'passengers' => 2,
            'status' => 'confirmed',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/assign-driver", [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched')
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.vehicle.id', $vehicle->id);

        Mail::assertNotSent(BookingDriverAssignedMail::class);
        $driverUrl = null;
        Mail::assertSent(DriverTripAccessMail::class, function (DriverTripAccessMail $mail): bool {
            return $mail->hasTo('driver@example.com')
                && str_contains($mail->render(), 'Customer Name')
                && ! str_contains($mail->render(), 'customer@example.com')
                && str_contains($mail->render(), 'Pickup')
                && str_contains($mail->render(), 'Drop-off');
        });
        Mail::assertSent(DriverTripAccessMail::class, function (DriverTripAccessMail $mail) use (&$driverUrl): bool {
            $driverUrl = $mail->driverUrl;
            return true;
        });

        $this->postJson('/api/public/driver-trips/'.basename(parse_url($driverUrl, PHP_URL_PATH)).'/dispatch-response', ['response' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');
        Mail::assertSent(BookingDriverAssignedMail::class, function (BookingDriverAssignedMail $mail): bool {
            return $mail->hasTo('customer@example.com')
                && str_contains($mail->render(), 'Assigned Driver')
                && str_contains($mail->render(), 'ABC-123')
                && str_contains($mail->render(), 'Pickup')
                && str_contains($mail->render(), 'Drop-off');
        });

        Sanctum::actingAs($driver, ['driver']);
        $this->postJson("/api/driver/bookings/{$booking->id}/status", ['status' => 'pob'])
            ->assertUnprocessable()
            ->assertJsonPath('data.current_status', 'assigned')
            ->assertJsonPath('data.expected_status', 'on_route');

        $this->postJson("/api/driver/bookings/{$booking->id}/status", ['status' => 'on_route'])
            ->assertOk()
            ->assertJsonPath('data.status', 'on_route');
        Mail::assertNotSent(BookingStatusChangedMail::class);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/update-status", ['status' => 'dispatched'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A trip that is on route or later cannot be moved backward to a pre-trip status');
        $this->postJson("/api/bookings/update/{$booking->id}", ['status' => 'pending'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A trip that is on route or later cannot be moved backward to a pre-trip status');

        Sanctum::actingAs($driver, ['driver']);
        $this->postJson("/api/driver/bookings/{$booking->id}/status", ['status' => 'on_location'])
            ->assertOk()
            ->assertJsonPath('data.status', 'on_location');
        Mail::assertSent(BookingTrackingStartedMail::class, fn (BookingTrackingStartedMail $mail): bool => $mail->hasTo('customer@example.com'));

        $this->postJson("/api/driver/bookings/{$booking->id}/status", ['status' => 'pob'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pob');
        $this->postJson("/api/driver/bookings/{$booking->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');
        Mail::assertNotSent(BookingStatusChangedMail::class);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/update-status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        Mail::assertSent(BookingStatusChangedMail::class, fn (BookingStatusChangedMail $mail): bool => $mail->hasTo('customer@example.com')
            && $mail->previousStatus === 'done'
            && $mail->booking->status === 'completed');

        $this->postJson("/api/bookings/update/{$booking->id}", ['status' => 'done'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Completed or cancelled bookings cannot be moved to another status');

        $booking->status = 'cancelled';
        $booking->save();
        $this->postJson("/api/bookings/{$booking->id}/update-status", ['status' => 'confirmed'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Completed or cancelled bookings cannot be moved to another status');
    }
}
