<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_swift_code',
        'vat_number',
        'address',
        'country',
        'zip',
    ];

    /**
     * Get the user that owns the affiliate.
     */
    public function user() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that owns the Account Manager.
     */
    public function accountManager()
    {
        return $this->hasOne(AccountManager::class);
    }
}
