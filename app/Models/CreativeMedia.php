<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreativeMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'creative_upload_id',
        'file_type',
        'attachment',
        'status'
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
     * Get the creative Upload of the media.
     */
    public function creativeUpload() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CreativeUpload::class);
    }
}
