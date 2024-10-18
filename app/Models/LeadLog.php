<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadLog extends Model
{
    use HasFactory;

    protected $table = 'lead_logs';

    protected $fillable = [
        'lead_id',
        'list_id',
        'log_data'
    ];

    protected $casts = [
        'log_data' => 'array'
    ];
    
    public function lead()
    {
        return $this->belongsTo(PlatformData::class, 'lead_id', 'id');
    }
}
