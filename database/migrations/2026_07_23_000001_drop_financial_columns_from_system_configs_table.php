<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_configs', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_rate',
                'base_price_flat',
                'cancellation_fee',
                'surge_rate',
                'wait_time_rate',
                'rate_buffer',
                'gratuity_percentage',
                'currency',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('system_configs', function (Blueprint $table): void {
            $table->decimal('tax_rate', 10, 2)->nullable()->after('company_id');
            $table->decimal('base_price_flat', 10, 2)->nullable()->after('tax_rate');
            $table->decimal('cancellation_fee', 10, 2)->nullable()->after('base_price_flat');
            $table->decimal('surge_rate', 10, 2)->nullable()->after('cancellation_fee');
            $table->decimal('wait_time_rate', 10, 2)->nullable()->after('surge_rate');
            $table->decimal('rate_buffer', 5, 2)->nullable()->after('wait_time_rate');
            $table->decimal('gratuity_percentage', 5, 2)->nullable()->after('rate_buffer');
            $table->string('currency', 10)->nullable()->after('gratuity_percentage');
        });
    }
};
