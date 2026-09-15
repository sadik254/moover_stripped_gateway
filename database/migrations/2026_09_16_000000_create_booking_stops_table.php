<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->text('address');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['booking_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_stops');
    }
};
