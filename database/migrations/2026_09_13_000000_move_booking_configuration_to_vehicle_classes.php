<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_classes', function (Blueprint $table): void {
            $table->integer('capacity')->nullable();
            $table->integer('luggage')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('per_km_rate', 10, 2)->nullable();
            $table->decimal('airport_rate', 10, 2)->nullable();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('vehicle_class_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('vehicle_classes')->orderBy('id')->each(function (object $vehicleClass): void {
            $vehicle = DB::table('vehicles')
                ->where('vehicle_class_id', $vehicleClass->id)
                ->orderBy('id')
                ->first(['capacity', 'luggage', 'hourly_rate', 'per_km_rate', 'airport_rate']);

            if ($vehicle) {
                DB::table('vehicle_classes')->where('id', $vehicleClass->id)->update([
                    'capacity' => $vehicle->capacity,
                    'luggage' => $vehicle->luggage,
                    'hourly_rate' => $vehicle->hourly_rate,
                    'per_km_rate' => $vehicle->per_km_rate,
                    'airport_rate' => $vehicle->airport_rate,
                ]);
            }
        });

        DB::table('bookings')
            ->whereNotNull('vehicle_id')
            ->orderBy('id')
            ->each(function (object $booking): void {
                $vehicleClassId = DB::table('vehicles')
                    ->where('id', $booking->vehicle_id)
                    ->value('vehicle_class_id');

                if ($vehicleClassId) {
                    DB::table('bookings')->where('id', $booking->id)->update([
                        'vehicle_class_id' => $vehicleClassId,
                    ]);
                }
            });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_id');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn(['capacity', 'luggage', 'hourly_rate', 'per_km_rate', 'airport_rate']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->integer('capacity')->nullable();
            $table->integer('luggage')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('per_km_rate', 10, 2)->nullable();
            $table->decimal('airport_rate', 10, 2)->nullable();
        });

        DB::table('vehicles')->orderBy('id')->each(function (object $vehicle): void {
            $vehicleClass = DB::table('vehicle_classes')->where('id', $vehicle->vehicle_class_id)->first();

            if ($vehicleClass) {
                DB::table('vehicles')->where('id', $vehicle->id)->update([
                    'capacity' => $vehicleClass->capacity,
                    'luggage' => $vehicleClass->luggage,
                    'hourly_rate' => $vehicleClass->hourly_rate,
                    'per_km_rate' => $vehicleClass->per_km_rate,
                    'airport_rate' => $vehicleClass->airport_rate,
                ]);
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_class_id');
        });

        Schema::table('vehicle_classes', function (Blueprint $table): void {
            $table->dropColumn(['capacity', 'luggage', 'hourly_rate', 'per_km_rate', 'airport_rate']);
        });
    }
};
