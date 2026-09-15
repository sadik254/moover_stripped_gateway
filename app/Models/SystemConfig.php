<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'tax_rate',
        'base_price_flat',
        'cancellation_fee',
        'surge_rate',
        'wait_time_rate',
        'rate_buffer',
        'gratuity_percentage',
        'currency',
        'service_zones',
        'platform_name',
        'primary_brand_color',
        'secondary_brand_color',
        'short_distance_limit_km',
        'distance_rate_start_km',
        'point_to_point_minimum_hours',
        'peak_days',
        'extra_stop_fee',
        'extra_stop_minutes',
        'waiting_grace_minutes',
    ];

    protected $casts = [
        'service_zones' => 'array',
        'peak_days' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
