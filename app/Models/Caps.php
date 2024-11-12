<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caps extends Model
{
    use HasFactory;

    protected $table = 'caps_history';

    protected $fillable = [
        'list_id',
        'integration_id',
        'buyer_id',
        'caps',
        'status',
        'column_scope',
        'cap_amount',
        'duration',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'caps' => 'array',
    ];
}
