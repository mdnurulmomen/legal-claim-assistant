<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformList extends Model
{
    use HasFactory;

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
