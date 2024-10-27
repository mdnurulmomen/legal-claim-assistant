<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
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
