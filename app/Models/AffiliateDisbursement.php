<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_booking_settlement_id',
        'affiliate_id',
        'booking_id',
        'amount',
        'currency',
        'status',
        'stripe_transfer_id',
        'failure_message',
        'processed_by_user_id',
    ];

    public function settlement()
    {
        return $this->belongsTo(AffiliateBookingSettlement::class, 'affiliate_booking_settlement_id');
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
