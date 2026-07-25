<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table): void {
            $table->dropColumn([
                'payout_mode',
                'affiliate_payout_percent',
                'platform_commission_percent',
                'stripe_connect_account_id',
                'payout_currency',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table): void {
            $table->string('payout_mode', 20)->nullable()->after('address');
            $table->decimal('affiliate_payout_percent', 5, 2)->nullable()->after('payout_mode');
            $table->decimal('platform_commission_percent', 5, 2)->nullable()->after('affiliate_payout_percent');
            $table->string('stripe_connect_account_id')->nullable()->after('platform_commission_percent');
            $table->string('payout_currency', 10)->nullable()->after('stripe_connect_account_id');
        });
    }
};
