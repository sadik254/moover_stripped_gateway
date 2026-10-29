<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->after('booking_access_token');
            $table->unique(['company_id', 'idempotency_key'], 'bookings_company_checkout_idempotency_unique');
        });

        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->after('payment_intent_id');
            $table->unique(['booking_id', 'idempotency_key'], 'booking_payments_booking_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropUnique('booking_payments_booking_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique('bookings_company_checkout_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
