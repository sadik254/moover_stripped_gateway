<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SystemConfig;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'timezone',
        'user_id',
        'logo',
        'url'
    ];

        public function user()
        {
            return $this->belongsTo(User::class);
        }
        public function vehicleClasses()
        {
            return $this->hasMany(VehicleClass::class);
        }
        public function vehicles()
        {
            return $this->hasMany(Vehicle::class);
        }
        public function drivers()
        {
            return $this->hasMany(Driver::class);
        }

        public function systemConfig()
        {
            return $this->hasOne(SystemConfig::class);
        }
}
