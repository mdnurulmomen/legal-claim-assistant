<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CaseStudy extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'title',
        'auth_name',
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
        'attachment' => 'array',
    ];

    public function setTagAttribute()
    {
        $this->attributes['tag'] = strtoupper(Str::random(15));
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->tag     = strtoupper(Str::random(15));
            $model->user_id = Auth::user()->id;
            $model->status  = 'Padding';
        });
    }

    /**
     * Get the  auth of the post.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the  categories of the post.
     */
    public function categories()
    {
        return $this->belongsToMany(CaseStudyCategory::class, 'case_study_category', 'case_study_id', 'case_study_category_id');
    }

    /**
     * Get the  tags of the post.
     */
    public function tags()
    {
        return $this->belongsToMany(CaseStudyTag::class, 'case_study_tag', 'case_study_id', 'case_study_tag_id');
    }
}
