<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Affiliate;
use App\Models\AffiliateDriver;
use App\Models\Vehicle;
use App\Models\BookingActivity;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'booking_access_token',
        'name',
        'email',
        'phone',
        'vehicle_id',
        'driver_id',
        'affiliate_id',
        'assigned_affiliate_driver_id',
        'service_type',
        'pickup_address',
        'dropoff_address',
        'pickup_time',
        'dropoff_time',
        'passengers',
        'child_seats',
        'bags',
        'flight_number',
        'airlines',
        'hours',
        'status',
        'affiliate_status',
        'affiliate_reference',
        'affiliate_notes',
        'notes',
    ];

    protected $hidden = [
        'booking_access_token',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function assignedAffiliateDriver()
    {
        return $this->belongsTo(AffiliateDriver::class, 'assigned_affiliate_driver_id');
    }

    public function activities()
    {
        return $this->hasMany(BookingActivity::class);
    }
}
