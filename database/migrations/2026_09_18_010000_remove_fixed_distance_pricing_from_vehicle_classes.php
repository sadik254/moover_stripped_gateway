<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['fixed_km_rate', 'fixed_km_limit'],
            fn (string $column): bool => Schema::hasColumn('vehicle_classes', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('vehicle_classes', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        $restoreRate = ! Schema::hasColumn('vehicle_classes', 'fixed_km_rate');
        $restoreLimit = ! Schema::hasColumn('vehicle_classes', 'fixed_km_limit');

        Schema::table('vehicle_classes', function (Blueprint $table) use ($restoreRate, $restoreLimit): void {
            if ($restoreRate) {
                $table->decimal('fixed_km_rate', 10, 2)->nullable();
            }

            if ($restoreLimit) {
                $table->decimal('fixed_km_limit', 10, 2)->nullable();
            }
        });
    }
};
