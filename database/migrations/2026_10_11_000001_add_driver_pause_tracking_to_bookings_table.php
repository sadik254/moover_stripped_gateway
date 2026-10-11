<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('driver_pause_started_at')->nullable()->after('waiting_minutes');
            $table->unsignedInteger('driver_pause_minutes')->default(0)->after('driver_pause_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['driver_pause_started_at', 'driver_pause_minutes']);
        });
    }
};
