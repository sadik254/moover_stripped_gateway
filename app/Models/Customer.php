<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
        'customer_company',
        'customer_type',
        'name',
        'email',
        'phone',
        'password',
        'email_verification_code',
        'email_verified_at',
        'password_reset_code',
        'password_reset_code_sent_at',
        'dispatch_note',
        'preferred_service_level',
    ];

    protected $hidden = [
        'password',
        'email_verification_code',
        'password_reset_code',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'password_reset_code_sent_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }
}
