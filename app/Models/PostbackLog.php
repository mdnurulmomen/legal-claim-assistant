<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostbackLog extends Model
{
    use HasFactory;

    protected $table = 'postback_logs';

    protected $fillable = [
        'request_id',
        'lead_id',
        'type',
        'success',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(PlatformData::class, 'lead_id', 'id');
    }
}
