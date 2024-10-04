<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerPlatformConnection extends Model
{
    use HasFactory;

    protected $table = 'partner_platform_connections';

    protected $fillable = [
        'connection_name',
        'user_id',
        'platform_id',
        'is_active',
        'partner_share',
        'share_percentage',
        'options',
        'disabled_buyers',
        'disabled_fields',
        'optional_fields',
    ];

    protected $casts = [
        'options' => 'array',
        'disabled_buyers' => 'array',
        'disabled_fields' => 'array',
        'optional_fields' => 'array',
        'is_active' => 'boolean'
    ];
}
