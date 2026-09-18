<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'image',
        'capacity',
        'luggage',
        'hourly_rate',
        'peak_hourly_rate',
        'point_to_point_rate',
        'fixed_km_rate',
        'fixed_km_limit',
        'per_km_rate',
        'airport_rate',
        'extra_stop_eligible',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function airportRates()
    {
        return $this->hasMany(VehicleClassAirportRate::class);
    }
}
