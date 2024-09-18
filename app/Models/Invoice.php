<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

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
        'listresults' => 'array',
    ];

    public function listresult()
    {
        return $this->belongsToMany(ListResult::class, 'invoices_listresult', 'invoices_id', 'listresult_id');
    }

    public function partner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function affiliateInfo()
    {
        return $this->hasOneThrough(Affiliate::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
