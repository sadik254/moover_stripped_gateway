<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('timed_stop_minutes', 8, 2)->default(0)->after('waiting_minutes');
            $table->decimal('timed_stop_grace_minutes', 8, 2)->default(0)->after('timed_stop_minutes');
            $table->decimal('timed_stop_block_minutes', 8, 2)->default(15)->after('timed_stop_grace_minutes');
            $table->unsignedInteger('timed_stop_blocks')->default(0)->after('timed_stop_minutes');
            $table->decimal('timed_stop_amount', 10, 2)->default(0)->after('timed_stop_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['timed_stop_minutes', 'timed_stop_grace_minutes', 'timed_stop_block_minutes', 'timed_stop_blocks', 'timed_stop_amount']);
        });
    }
};
