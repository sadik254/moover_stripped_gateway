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
            $table->decimal('peak_hourly_rate', 10, 2)->nullable();
            $table->decimal('point_to_point_rate', 10, 2)->nullable();
            $table->boolean('extra_stop_eligible')->default(false);
        });

        Schema::table('system_configs', function (Blueprint $table): void {
            $table->decimal('short_distance_limit_km', 10, 2)->default(16.09);
            $table->decimal('distance_rate_start_km', 10, 2)->default(32.19);
            $table->decimal('point_to_point_minimum_hours', 6, 2)->default(2);
            $table->json('peak_days')->nullable();
            $table->decimal('extra_stop_fee', 10, 2)->default(40);
            $table->unsignedInteger('extra_stop_minutes')->default(15);
            $table->unsignedInteger('waiting_grace_minutes')->default(15);
        });

        Schema::create('airports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('vehicle_class_airport_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('airport_id')->constrained()->cascadeOnDelete();
            $table->string('service_zone')->default('Manhattan');
            $table->decimal('rate', 10, 2);
            $table->timestamps();
            $table->unique(['vehicle_class_id', 'airport_id', 'service_zone'], 'vehicle_airport_zone_unique');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('airport_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('extra_stops')->default(0);
            $table->decimal('waiting_minutes', 8, 2)->default(0);
            $table->decimal('tolls', 10, 2)->default(0);
            $table->decimal('extra_stop_amount', 10, 2)->default(0);
            $table->decimal('waiting_time_amount', 10, 2)->default(0);
            $table->string('pricing_method', 50)->nullable();
        });

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ([
                'JFK' => 'John F. Kennedy International Airport',
                'LGA' => 'LaGuardia Airport',
                'EWR' => 'Newark Liberty International Airport',
                'HPN' => 'Westchester County Airport',
                'TEB' => 'Teterboro Airport',
                'ISP' => 'Long Island MacArthur Airport',
            ] as $code => $name) {
                DB::table('airports')->insert([
                    'company_id' => $companyId,
                    'code' => $code,
                    'name' => $name,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('airport_id');
            $table->dropColumn(['extra_stops', 'waiting_minutes', 'tolls', 'extra_stop_amount', 'waiting_time_amount', 'pricing_method']);
        });
        Schema::dropIfExists('vehicle_class_airport_rates');
        Schema::dropIfExists('airports');
        Schema::table('system_configs', function (Blueprint $table): void {
            $table->dropColumn(['short_distance_limit_km', 'distance_rate_start_km', 'point_to_point_minimum_hours', 'peak_days', 'extra_stop_fee', 'extra_stop_minutes', 'waiting_grace_minutes']);
        });
        Schema::table('vehicle_classes', function (Blueprint $table): void {
            $table->dropColumn(['peak_hourly_rate', 'point_to_point_rate', 'extra_stop_eligible']);
        });
    }
};
