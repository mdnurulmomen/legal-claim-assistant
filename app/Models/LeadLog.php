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

    /**
     * Get the list of this lead log data.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */

     public function list()
     {
         return $this->belongsTo(PlatformList::class, 'list_id', 'id');
     }

    /**
     * Get the lead affiliate associated with this platform data.
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasOneThrough
     */
    public function affiliate()
    {
        return $this->hasOneThrough(
            User::class,
            PartnerPlatformData::class,
            'lead_id',
            'id',
            'lead_id',
            'user_id'
        );
    }
}
