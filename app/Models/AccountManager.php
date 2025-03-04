<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountManager extends Model
{
    use HasFactory;

    protected $table = 'account_managers';

    public $primaryKey = 'affiliate_id';

    protected $fillable = [
        'affiliate_id',
        'user_id',
    ];

    public $timestamps = false;

    /**
     * Get the user that owns the affiliate.
     */
    public function affilite()
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }
}
