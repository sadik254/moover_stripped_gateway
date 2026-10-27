<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->string('stripe_customer_id')->nullable()->after('payment_method_id');
            $table->string('payment_kind', 50)->default('authorization')->after('provider');
            $table->timestamp('consent_granted_at')->nullable()->after('payment_kind');
            $table->string('consent_version', 50)->nullable()->after('consent_granted_at');
            $table->text('consent_text')->nullable()->after('consent_version');

            $table->index(['booking_id', 'payment_kind']);
            $table->index('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropIndex(['booking_id', 'payment_kind']);
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn([
                'stripe_customer_id',
                'payment_kind',
                'consent_granted_at',
                'consent_version',
                'consent_text',
            ]);
        });
    }
};
