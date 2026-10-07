<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('booking_origin', 30)->default('online')->after('company_id');
            $table->index(['company_id', 'booking_origin']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'booking_origin']);
            $table->dropColumn('booking_origin');
        });
    }
};
