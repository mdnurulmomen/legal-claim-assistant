<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerPlatformData extends Model
{
    use HasFactory;

    protected $table = 'partner_platform_datas';

    protected $guarded = ["id"];

    public function affiliate()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
