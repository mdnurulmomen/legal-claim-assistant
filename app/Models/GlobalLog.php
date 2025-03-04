<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'loggable_id',
        'loggable_type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
