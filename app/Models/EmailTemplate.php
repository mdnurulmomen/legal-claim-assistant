<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'title',
        'content',
        'status',
    ];

    /**
     * Get the specs changes notifications for the email template.
     */
    public function specsChangesNotifications(): HasMany
    {
        return $this->hasMany(SpecsChangesNotification::class);
    }
}