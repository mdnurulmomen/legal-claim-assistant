<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_name',
        'alias',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'website',
        'notes',
        'status',
    ];

    //get buyer_alias_id
    public function getBuyerAliasIdAttribute()
    {
        return $this->id;
    }
}
