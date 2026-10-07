<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->string('payment_intent_id')->nullable()->change();
            $table->string('stripe_invoice_id')->nullable()->after('payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropColumn('stripe_invoice_id');
            $table->string('payment_intent_id')->nullable(false)->change();
        });
    }
};
