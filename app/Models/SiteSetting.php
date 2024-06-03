<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SiteSetting extends Model
{
    use HasFactory;

    //define table name
    protected $table = 'page_settings';

    protected $fillable = [
        'page',
        'user_id',
        'data',
        'type'
    ];

    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_string($value) ? json_decode($value) : $value,
            set: fn ($value) => json_encode($value),
        );
    }
}
