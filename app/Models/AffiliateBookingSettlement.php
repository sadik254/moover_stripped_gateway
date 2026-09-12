<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateBookingSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'affiliate_id',
        'gross_amount',
        'affiliate_percent',
        'platform_percent',
        'affiliate_amount',
        'platform_amount',
        'currency',
        'status',
        'status_reason',
        'accepted_at',
        'ready_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'ready_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function disbursements()
    {
        return $this->hasMany(AffiliateDisbursement::class);
    }
}
