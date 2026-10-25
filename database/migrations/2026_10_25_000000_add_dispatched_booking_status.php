<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->enum('status', [
                'pending', 'confirmed', 'dispatched', 'assigned', 'on_route',
                'on_location', 'pob', 'done', 'completed', 'cancelled',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->enum('status', [
                'pending', 'confirmed', 'assigned', 'on_route', 'on_location',
                'pob', 'done', 'completed', 'cancelled',
            ])->default('pending')->change();
        });
    }
};
