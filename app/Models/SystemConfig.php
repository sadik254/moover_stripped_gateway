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
    ];

    protected $casts = [
        'service_zones' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
