<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->restoreBookingColumns();
        $this->restoreSystemConfigColumns();
        $this->restoreAffiliateColumns();
        $this->restoreFinancialTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_disbursements');
        Schema::dropIfExists('affiliate_booking_settlements');
        Schema::dropIfExists('booking_payments');

        $this->dropExistingColumns('affiliates', [
            'payout_mode', 'affiliate_payout_percent', 'platform_commission_percent',
            'stripe_connect_account_id', 'payout_currency',
        ]);
        $this->dropExistingColumns('system_configs', [
            'tax_rate', 'base_price_flat', 'cancellation_fee', 'surge_rate',
            'wait_time_rate', 'rate_buffer', 'gratuity_percentage', 'currency',
        ]);
        $this->dropExistingColumns('bookings', [
            'distance_km', 'base_price', 'extras_price', 'total_price', 'final_price',
            'taxes', 'gratuity', 'parking', 'others', 'airport_fees', 'congestion_charge',
            'taxes_amount', 'gratuity_amount', 'rate_buffer', 'rate_buffer_amount',
            'cancellation_fee', 'surge_rate', 'surge_rate_amount', 'payment_method',
            'payment_status',
        ]);
    }

    private function restoreBookingColumns(): void
    {
        $columns = [
            'distance_km' => fn (Blueprint $table) => $table->decimal('distance_km', 10, 2)->nullable(),
            'base_price' => fn (Blueprint $table) => $table->decimal('base_price', 10, 2)->nullable(),
            'extras_price' => fn (Blueprint $table) => $table->decimal('extras_price', 10, 2)->nullable(),
            'total_price' => fn (Blueprint $table) => $table->decimal('total_price', 10, 2)->nullable(),
            'final_price' => fn (Blueprint $table) => $table->decimal('final_price', 10, 2)->nullable(),
            'taxes' => fn (Blueprint $table) => $table->decimal('taxes', 10, 2)->nullable(),
            'gratuity' => fn (Blueprint $table) => $table->decimal('gratuity', 10, 2)->nullable(),
            'parking' => fn (Blueprint $table) => $table->decimal('parking', 10, 2)->nullable(),
            'others' => fn (Blueprint $table) => $table->decimal('others', 10, 2)->nullable(),
            'airport_fees' => fn (Blueprint $table) => $table->decimal('airport_fees', 10, 2)->nullable(),
            'congestion_charge' => fn (Blueprint $table) => $table->decimal('congestion_charge', 10, 2)->nullable(),
            'taxes_amount' => fn (Blueprint $table) => $table->decimal('taxes_amount', 10, 2)->nullable(),
            'gratuity_amount' => fn (Blueprint $table) => $table->decimal('gratuity_amount', 10, 2)->nullable(),
            'rate_buffer' => fn (Blueprint $table) => $table->decimal('rate_buffer', 5, 2)->nullable(),
            'rate_buffer_amount' => fn (Blueprint $table) => $table->decimal('rate_buffer_amount', 10, 2)->nullable(),
            'cancellation_fee' => fn (Blueprint $table) => $table->decimal('cancellation_fee', 10, 2)->nullable(),
            'surge_rate' => fn (Blueprint $table) => $table->decimal('surge_rate', 5, 2)->nullable(),
            'surge_rate_amount' => fn (Blueprint $table) => $table->decimal('surge_rate_amount', 10, 2)->nullable(),
            'payment_method' => fn (Blueprint $table) => $table->string('payment_method')->nullable(),
            'payment_status' => fn (Blueprint $table) => $table->string('payment_status')->nullable(),
        ];

        $this->addMissingColumns('bookings', $columns);
    }

    private function restoreSystemConfigColumns(): void
    {
        $this->addMissingColumns('system_configs', [
            'tax_rate' => fn (Blueprint $table) => $table->decimal('tax_rate', 10, 2)->nullable(),
            'base_price_flat' => fn (Blueprint $table) => $table->decimal('base_price_flat', 10, 2)->nullable(),
            'cancellation_fee' => fn (Blueprint $table) => $table->decimal('cancellation_fee', 10, 2)->nullable(),
            'surge_rate' => fn (Blueprint $table) => $table->decimal('surge_rate', 10, 2)->nullable(),
            'wait_time_rate' => fn (Blueprint $table) => $table->decimal('wait_time_rate', 10, 2)->nullable(),
            'rate_buffer' => fn (Blueprint $table) => $table->decimal('rate_buffer', 5, 2)->nullable(),
            'gratuity_percentage' => fn (Blueprint $table) => $table->decimal('gratuity_percentage', 5, 2)->nullable(),
            'currency' => fn (Blueprint $table) => $table->string('currency', 10)->nullable(),
        ]);
    }

    private function restoreAffiliateColumns(): void
    {
        $this->addMissingColumns('affiliates', [
            'payout_mode' => fn (Blueprint $table) => $table->string('payout_mode', 20)->nullable(),
            'affiliate_payout_percent' => fn (Blueprint $table) => $table->decimal('affiliate_payout_percent', 5, 2)->nullable(),
            'platform_commission_percent' => fn (Blueprint $table) => $table->decimal('platform_commission_percent', 5, 2)->nullable(),
            'stripe_connect_account_id' => fn (Blueprint $table) => $table->string('stripe_connect_account_id')->nullable(),
            'payout_currency' => fn (Blueprint $table) => $table->string('payout_currency', 10)->default('usd'),
        ]);
    }

    private function restoreFinancialTables(): void
    {
        if (! Schema::hasTable('booking_payments')) {
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
        }

        if (! Schema::hasTable('affiliate_booking_settlements')) {
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
        }

        if (! Schema::hasTable('affiliate_disbursements')) {
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
    }

    private function addMissingColumns(string $tableName, array $columns): void
    {
        foreach ($columns as $columnName => $definition) {
            if (! Schema::hasColumn($tableName, $columnName)) {
                Schema::table($tableName, $definition);
            }
        }
    }

    private function dropExistingColumns(string $tableName, array $columns): void
    {
        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($existing !== []) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
