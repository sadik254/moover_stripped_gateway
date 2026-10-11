<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_stops', function (Blueprint $table): void {
            $table->unsignedSmallInteger('minutes')->default(0)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('booking_stops', function (Blueprint $table): void {
            $table->dropColumn('minutes');
        });
    }
};
