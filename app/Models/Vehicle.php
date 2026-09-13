<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'vehicle_class_id',
        'status',
        'plate_number',
        'color',
        'model',
        'image',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicleClass()
    {
        return $this->belongsTo(VehicleClass::class);
    }

    public function drivers()
    {
        return $this->hasMany(Driver::class);
    }
}
