<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleClassAirportRate extends Model
{
    protected $fillable = ['vehicle_class_id', 'airport_id', 'service_zone', 'rate'];

    public function vehicleClass()
    {
        return $this->belongsTo(VehicleClass::class);
    }

    public function airport()
    {
        return $this->belongsTo(Airport::class);
    }
}
