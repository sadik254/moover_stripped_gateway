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
            $table->string('pricing_mode', 50)->default('standard')->after('extra_stop_eligible');
        });

        DB::table('vehicle_classes')
            ->whereIn('name', ['Executive Sprinter', 'Jet Sprinter', 'Limo Sprinter'])
            ->update(['pricing_mode' => 'sprinter_inclusive']);
    }

    public function down(): void
    {
        Schema::table('vehicle_classes', function (Blueprint $table): void {
            $table->dropColumn('pricing_mode');
        });
    }
};
