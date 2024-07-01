<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformData extends Model
{
    use HasFactory;

    protected $table = 'platform_datas';

    protected $fillable = [
        'list_id',
        'affiliate_id',
        'affid',
        'phone',
        'email',
        'datas',
        'revenue',
        'payout',
        'cost',
        'is_retainer',
        'affiliate_specs_id',
        'is_sold',
        'buyer_integration_id',
        'buyer_id',
        'lead_status',
        'is_internal',
        'retained_date',
        'affm_source_id',
        'page_source',
        'sold_type'
    ];

    protected $casts = [
        'datas' => 'array'
    ];
}
