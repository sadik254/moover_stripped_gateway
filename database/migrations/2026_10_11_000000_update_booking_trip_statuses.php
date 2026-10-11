<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing picking_up trips have already left the assigned state, so
        // on_route is the safe equivalent in the new lifecycle.
        DB::table('bookings')->where('status', 'picking_up')->update(['status' => 'on_route']);

        Schema::table('bookings', function (Blueprint $table): void {
            $table->enum('status', [
                'pending', 'confirmed', 'assigned', 'on_route', 'on_location',
                'pob', 'done', 'completed', 'cancelled',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('bookings')->whereIn('status', ['on_location', 'pob'])->update(['status' => 'on_route']);

        Schema::table('bookings', function (Blueprint $table): void {
            $table->enum('status', [
                'pending', 'confirmed', 'assigned', 'picking_up', 'on_route',
                'completed', 'cancelled', 'done',
            ])->default('pending')->change();
        });
    }
};
