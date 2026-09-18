<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $with = ['stops', 'vehicle'];

    protected $fillable = [
        'company_id',
        'customer_id',
        'booking_access_token',
        'name',
        'email',
        'phone',
        'vehicle_class_id',
        'vehicle_id',
        'airport_id',
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
        'distance_km',
        'hours',
        'extra_stops',
        'waiting_minutes',
        'tolls',
        'extra_stop_amount',
        'waiting_time_amount',
        'pricing_method',
        'base_price',
        'extras_price',
        'total_price',
        'final_price',
        'taxes',
        'gratuity',
        'parking',
        'others',
        'airport_fees',
        'congestion_charge',
        'taxes_amount',
        'gratuity_amount',
        'rate_buffer',
        'rate_buffer_amount',
        'cancellation_fee',
        'surge_rate',
        'surge_rate_amount',
        'payment_method',
        'payment_status',
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

    public function vehicleClass()
    {
        return $this->belongsTo(VehicleClass::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function airport()
    {
        return $this->belongsTo(Airport::class);
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

    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(BookingPayment::class)->latestOfMany();
    }

    public function stops()
    {
        return $this->hasMany(BookingStop::class)->orderBy('position');
    }

    public function activities()
    {
        return $this->hasMany(BookingActivity::class);
    }

    public function accessLinks()
    {
        return $this->hasMany(BookingAccessLink::class);
    }

    public function settlement()
    {
        return $this->hasOne(AffiliateBookingSettlement::class);
    }
}
