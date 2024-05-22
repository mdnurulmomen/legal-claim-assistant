<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformList extends Model
{
    use HasFactory;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'headers' => 'array',
        'cv_trigger' => 'array',
        'options' => 'array',
        'integrations' => 'array',
        'insights' => 'array',
        'buyer_headers' => 'array',
        'lead_headers' => 'array',
    ];

    protected $fillable = [
        'tag',
        'name',
        'campaign_name',
        'source',
        'total',
        'headers',
        'is_test',
        'cv_trigger',
        'integrations',
        'options',
        'insights',
        'status',
    ];
}
