<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CaseStudy extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'title',
        'user_id',
        'description',
        'attachment',
        'status'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'attachments' => 'array',
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
