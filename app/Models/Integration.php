<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'buyer_unique_id',
        'buyer_id',
        'list_id',
        'buyer_headers',
        'type',
        'lead_id_key',
        'note',
    ];

    protected $casts = [
        'buyer_headers' => 'array',
    ];
}
