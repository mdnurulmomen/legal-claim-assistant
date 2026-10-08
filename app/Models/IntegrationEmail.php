<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEmail extends Model
{
    protected $fillable = [
        'title',
        'content',
        'status'
    ];

    protected $casts = [
        'content' => 'json'
    ];
}
