<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConferenceEvents extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'title',
        'description',
        'thumb',
        'event_start_date',
        'event_end_date',
    ];

    public function setTagAttribute()
    {
        $this->attributes['tag'] = strtoupper(Str::random(15));
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->tag = strtoupper(Str::random(15));
        });
    }
}
