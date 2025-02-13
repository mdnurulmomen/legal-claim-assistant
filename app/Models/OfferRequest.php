<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'portal_offer_id',
        'affiliate_id',
        'notes',
        'action_notes',
        'status'
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(PortalOffers::class, 'portal_offer_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }
}
