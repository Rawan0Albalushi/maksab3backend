<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per order that consumed a BOGO offer -- the ledger both caps are answered from.
 *
 * quantity counts bundles, not items: an order with two of the same bundle spends two of the
 * allowance. Caps count bundles per offer, never per cart group, which is a different id
 * answering a different question.
 */
class BogoOfferUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'bogo_offer_id',
        'order_id',
        'user_id',
        'is_guest',
        'phone',
        'quantity',
    ];

    protected $casts = [
        'bogo_offer_id' => 'integer',
        'order_id' => 'integer',
        'user_id' => 'integer',
        'is_guest' => 'boolean',
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bogoOffer()
    {
        return $this->belongsTo(BogoOffer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Narrows to one customer's usage of an offer.
     *
     * A guest is matched by phone and never by any client-supplied identifier: a guest has no
     * user_id, and anything the client can set is something the client can change to reset its
     * own allowance.
     */
    public function scopeForCustomer(Builder $query, ?int $userId, bool $isGuest, ?string $phone): Builder
    {
        if (! $isGuest && $userId) {
            return $query->where('user_id', $userId);
        }

        // No phone means nothing to attribute the usage to, so it can never match.
        return $phone ? $query->where('phone', $phone) : $query->whereRaw('1 = 0');
    }
}
