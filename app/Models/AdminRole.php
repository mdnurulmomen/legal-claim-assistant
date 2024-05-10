<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'permissions', 'admin_role_id', 'menu_id');
    }
}
