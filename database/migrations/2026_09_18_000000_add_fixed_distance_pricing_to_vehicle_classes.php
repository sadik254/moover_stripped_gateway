<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_classes', function (Blueprint $table): void {
            $table->decimal('fixed_km_rate', 10, 2)->nullable()->after('point_to_point_rate');
            $table->decimal('fixed_km_limit', 10, 2)->nullable()->after('fixed_km_rate');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_classes', function (Blueprint $table): void {
            $table->dropColumn(['fixed_km_rate', 'fixed_km_limit']);
        });
    }
};
