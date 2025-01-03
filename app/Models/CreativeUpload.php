<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreativeUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'user_id',
        'template_offer_id',
        'creative_template_id',
        'name',
        'description',
        'attachments',
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

    /**
     * Get the user that owns the affiliate.
     */
    public function user() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the offer of creative
     */
    public function offer()
    {
        return $this->belongsTo(TemplateOffer::class, 'template_offer_id', 'id');
    }

    /**
     * Get the template of creative
     */
    public function template()
    {
        return $this->belongsTo(CreativeTemplate::class, 'creative_template_id', 'id');
    }
}
