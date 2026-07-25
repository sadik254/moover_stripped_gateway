<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('affiliate_disbursements');
        Schema::dropIfExists('affiliate_booking_settlements');
        Schema::dropIfExists('booking_payments');
    }

    public function down(): void
    {
        Schema::create('booking_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('stripe');
            $table->string('currency', 10)->default('usd');
            $table->string('payment_intent_id')->unique();
            $table->string('payment_method_id')->nullable();
            $table->decimal('estimated_amount', 10, 2);
            $table->decimal('authorized_amount', 10, 2);
            $table->decimal('captured_amount', 10, 2)->nullable();
            $table->decimal('amount_to_capture', 10, 2)->nullable();
            $table->string('status', 50)->default('created');
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_booking_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_amount', 10, 2)->default(0);
            $table->decimal('affiliate_percent', 5, 2)->default(0);
            $table->decimal('platform_percent', 5, 2)->default(0);
            $table->decimal('affiliate_amount', 10, 2)->default(0);
            $table->decimal('platform_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('usd');
            $table->string('status', 20)->default('pending');
            $table->string('status_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_disbursements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_booking_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('usd');
            $table->string('status', 20);
            $table->string('stripe_transfer_id')->nullable();
            $table->text('failure_message')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
