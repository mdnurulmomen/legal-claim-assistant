<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformDataItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_data_id',
        'field',
        'value',
        'additional_info'
    ];
}
