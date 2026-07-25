<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'base_price',
                'extras_price',
                'total_price',
                'final_price',
                'taxes',
                'gratuity',
                'parking',
                'others',
                'airport_fees',
                'congestion_charge',
                'taxes_amount',
                'gratuity_amount',
                'rate_buffer',
                'rate_buffer_amount',
                'cancellation_fee',
                'surge_rate',
                'surge_rate_amount',
                'payment_method',
                'payment_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('base_price', 10, 2)->nullable()->after('hours');
            $table->decimal('extras_price', 10, 2)->nullable()->after('base_price');
            $table->decimal('total_price', 10, 2)->nullable()->after('extras_price');
            $table->decimal('final_price', 10, 2)->nullable()->after('total_price');
            $table->decimal('taxes', 10, 2)->nullable()->after('final_price');
            $table->decimal('gratuity', 10, 2)->nullable()->after('taxes');
            $table->decimal('parking', 10, 2)->nullable()->after('gratuity');
            $table->decimal('others', 10, 2)->nullable()->after('parking');
            $table->decimal('airport_fees', 10, 2)->nullable()->after('others');
            $table->decimal('congestion_charge', 10, 2)->nullable()->after('airport_fees');
            $table->decimal('taxes_amount', 10, 2)->nullable()->after('congestion_charge');
            $table->decimal('gratuity_amount', 10, 2)->nullable()->after('taxes_amount');
            $table->decimal('rate_buffer', 5, 2)->nullable()->after('gratuity_amount');
            $table->decimal('rate_buffer_amount', 10, 2)->nullable()->after('rate_buffer');
            $table->decimal('cancellation_fee', 10, 2)->nullable()->after('rate_buffer_amount');
            $table->decimal('surge_rate', 5, 2)->nullable()->after('cancellation_fee');
            $table->decimal('surge_rate_amount', 10, 2)->nullable()->after('surge_rate');
            $table->string('payment_method')->nullable()->after('others');
            $table->string('payment_status')->nullable()->after('payment_method');
        });
    }
};
