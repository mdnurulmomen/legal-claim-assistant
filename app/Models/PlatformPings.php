<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformPings extends Model
{
    use HasFactory;

    protected $table = 'platform_pings';

    /**
     * Guarded attributes
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'affiliate_ping_payload' => 'array',
        'buyer_ping_post_data' => 'array',
        'ping_response' => 'array',
        'ping_logs' => 'array'
    ];
}
