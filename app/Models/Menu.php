<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'route_name',
        'type',
        'menu_id',
        'order'
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'menu_id', 'id');
    }
}
