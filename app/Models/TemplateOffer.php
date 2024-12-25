<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TemplateOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'name',
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

    /**
     * Get the user that owns the affiliate.
     */
    public function templates()
    {
        return $this->hasMany(CreativeTemplate::class, 'template_offer_id', 'id');
    }
}
