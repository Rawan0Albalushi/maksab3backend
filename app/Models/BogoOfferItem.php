<?php

namespace App\Models;

use App\Traits\Promotion\DescribesFrozenItemLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One frozen line of a store's bundle -- a snapshot taken the day the store enrolled.
 *
 * price is what the line costs inside the bundle, so a get row is zero. original_price is what
 * the item was worth when the bundle was built, and both are resolved from the item's BASE price
 * plus variation and add-on cost. Never from a discounted price: a bundle built on an
 * already-discounted price compounds two discounts and the store carries both.
 *
 * Exactly one of item_id and service_id is set. The database enforces it with a CHECK, and
 * saving() repeats the rule so the failure is an exception with a sentence rather than a driver
 * error -- and so servers that parse CHECK without enforcing it still behave.
 */
class BogoOfferItem extends Model
{
    use DescribesFrozenItemLine, HasFactory;

    public const TYPE_BUY = 'buy';

    public const TYPE_GET = 'get';

    protected $fillable = [
        'bogo_offer_store_id',
        'item_id',
        'service_id',
        'type',
        'quantity',
        'item_name',
        'item_image',
        'price',
        'original_price',
        'variations',
        'add_on_ids',
        'add_on_qtys',
    ];

    protected $casts = [
        'bogo_offer_store_id' => 'integer',
        'item_id' => 'integer',
        'service_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'float',
        'original_price' => 'float',
        'variations' => 'array',
        'add_on_ids' => 'array',
        'add_on_qtys' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(BogoOfferStore::class, 'bogo_offer_store_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeBuy(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BUY);
    }

    public function scopeGet(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_GET);
    }

    public function isFree(): bool
    {
        return $this->type === self::TYPE_GET;
    }

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            $hasItem = $line->item_id !== null;
            $hasService = $line->service_id !== null;

            if ($hasItem === $hasService) {
                throw new \InvalidArgumentException(
                    'A BOGO offer line must reference exactly one of item_id or service_id.'
                );
            }
        });
    }
}
