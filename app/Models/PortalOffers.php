<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalOffers extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'active',
        'img',
        'payout_range_cpl_min',
        'payout_range_cpl_max',
        'payout_range_cpa_min',
        'payout_range_cpa_max',
        'preview_link',
        'criteria',
        'tag'
    ];

    // table name
    protected $table = 'portal_offers';
}
