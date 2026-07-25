<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class SystemConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
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
