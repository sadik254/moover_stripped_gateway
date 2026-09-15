<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $fillable = ['company_id', 'code', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicleClassRates()
    {
        return $this->hasMany(VehicleClassAirportRate::class);
    }
}
