<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingStop extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'address', 'position'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
