<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'admin_role',
        'is_show_affiliate'
    ];

    protected $casts = [
        'is_show_affiliate' => 'boolean'
    ];

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'permissions', 'admin_role_id', 'menu_id');
    }
}
