<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingLiveLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'driver_id',
        'latitude',
        'longitude',
        'heading',
        'speed',
        'accuracy',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'heading' => 'int',
        'speed' => 'float',
        'accuracy' => 'float',
    ];
}

