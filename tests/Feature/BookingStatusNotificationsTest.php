<?php

namespace Tests\Feature;

use App\Mail\BookingDriverAssignedMail;
use App\Mail\BookingStatusChangedMail;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingStatusNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_and_status_changes_email_the_customer(): void
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
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.vehicle.id', $vehicle->id);

        Mail::assertSent(BookingDriverAssignedMail::class, function (BookingDriverAssignedMail $mail): bool {
            return $mail->hasTo('customer@example.com')
                && str_contains($mail->render(), 'Assigned Driver')
                && str_contains($mail->render(), 'ABC-123');
        });

        $this->postJson("/api/bookings/{$booking->id}/update-status", ['status' => 'on_route'])
            ->assertOk()
            ->assertJsonPath('data.status', 'on_route');

        Mail::assertSent(BookingStatusChangedMail::class, fn (BookingStatusChangedMail $mail): bool => $mail->hasTo('customer@example.com')
            && $mail->previousStatus === 'assigned'
            && $mail->booking->status === 'on_route');
    }
}
