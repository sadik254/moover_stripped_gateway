<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->renameColumn('distance_km', 'distance_miles'));
        Schema::table('vehicle_classes', fn (Blueprint $table) => $table->renameColumn('per_km_rate', 'per_mile_rate'));
        Schema::table('system_configs', function (Blueprint $table): void {
            $table->renameColumn('short_distance_limit_km', 'short_distance_limit_miles');
            $table->renameColumn('distance_rate_start_km', 'distance_rate_start_miles');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->renameColumn('distance_miles', 'distance_km'));
        Schema::table('vehicle_classes', fn (Blueprint $table) => $table->renameColumn('per_mile_rate', 'per_km_rate'));
        Schema::table('system_configs', function (Blueprint $table): void {
            $table->renameColumn('short_distance_limit_miles', 'short_distance_limit_km');
            $table->renameColumn('distance_rate_start_miles', 'distance_rate_start_km');
        });
    }
};
