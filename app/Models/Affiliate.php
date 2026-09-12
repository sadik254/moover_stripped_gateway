<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'email',
        'phone',
        'status',
        'address',
        'payout_mode',
        'affiliate_payout_percent',
        'platform_commission_percent',
        'stripe_connect_account_id',
        'payout_currency',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function settlements()
    {
        return $this->hasMany(AffiliateBookingSettlement::class);
    }

    public function disbursements()
    {
        return $this->hasMany(AffiliateDisbursement::class);
    }

    public function drivers()
    {
        return $this->hasMany(AffiliateDriver::class);
    }
}
