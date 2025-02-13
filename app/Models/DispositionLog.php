<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispositionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'disposition_config_id',
        'platform_data_id',
        'lead_status',
        'is_duplicate',
        'data',
        'updatable_data'
    ];

    protected $casts = [
        'data' => 'array',
        'updatable_data' => 'array'
    ];
}
