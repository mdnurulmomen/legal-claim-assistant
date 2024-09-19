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
        'buyer_integration_id',
        'buyer_id',
        'lead_status',
        'affm_source_id',
        'affiliate_specs_id',
        'sold_type',
        'datas',
        'revenue',
        'payout',
        'cost',
        'is_retainer',
        'is_sold',
        'is_internal',
        'retained_date',
        'page_source',
    ];

    protected $casts = [
        'datas' => 'array'
    ];

    /**
     * Get the lead report associated with this platform data.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function leadReport()
    {
        return $this->hasOne(LeadReport::class, 'lead_id', 'id');
    }
}
