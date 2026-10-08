<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispositionConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'uid',
        'conditions'
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(DispositionLog::class);
    }
}
