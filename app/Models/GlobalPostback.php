<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalPostback extends Model
{
    use HasFactory;
    
    protected $table = 'global_postbacks';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'global_postbacks' => 'array',
    ];

    protected $fillable = [
        'name',
        'url',
        'conditions',
        'status',
    ];

    
}
