<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'vehicle_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')->nullable()->after('vehicle_class_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'vehicle_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_id');
        });
    }
};
