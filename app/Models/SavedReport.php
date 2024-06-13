<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SavedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'user_id',
        'title',
        'filters',
        'visited_at'
    ];

    public function pageSettings(): BelongsToMany
    {
        return $this->belongsToMany(PageSetting::class, 'page_reports')->withTimestamps();
    }

    protected function filters(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_string($value) ? json_decode($value) : $value,
            set: fn ($value) => json_encode($value),
        );
    }

    public static function booted(): void
    {
        $userId = auth()->id();

        static::creating(function ($model) use ($userId) {
            $model->uid = str()->uuid();
            $model->user_id = $userId;
            $model->visited_at = now();
        });
    }
}
