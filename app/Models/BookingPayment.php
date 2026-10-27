<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'customer_id',
        'provider',
        'payment_kind',
        'currency',
        'payment_intent_id',
        'stripe_invoice_id',
        'payment_method_id',
        'stripe_customer_id',
        'consent_granted_at',
        'consent_version',
        'consent_text',
        'estimated_amount',
        'authorized_amount',
        'captured_amount',
        'amount_to_capture',
        'status',
        'failure_code',
        'failure_message',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'consent_granted_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
