<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateLog extends Model
{
    use HasFactory;
    
    protected $table = 'affiliate_logs';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = [
        'affiliate_id',
        'accessId',
        'action',
        'data',
    ];
    
    
    public function user()
    {
        return $this->belongsTo(User::class, 'affiliate_id', 'id');
    }

    
}
