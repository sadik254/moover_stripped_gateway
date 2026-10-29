<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('confirmation_email_sent_at')->nullable()->after('payment_status');
            $table->boolean('confirmation_email_needs_update')->default(false)->after('confirmation_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['confirmation_email_sent_at', 'confirmation_email_needs_update']);
        });
    }
};
