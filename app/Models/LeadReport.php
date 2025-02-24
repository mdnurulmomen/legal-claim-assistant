<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'lead_id',
        'list_id',
        'buyer_id',
        'affid',
        'buyer_integration_id',
        'affiliate_specs_id',
        'sold_type',
        'is_retainer',
        'is_returned',
        'is_paid',
        'is_internal',
        'lead_revenue',
        'affiliate_payout',
        'lead_profit',
        'affiliate_margin',
        'profit_margin',
        'is_posted',
        'page_source',
        'affm_source_id'
    ];
}
