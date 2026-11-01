<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A free-delivery setup for one zone and the modules it covers.
 *
 * The admin bears the cost, so an order freed here is attributed to 'admin' exactly as the
 * global setting this replaces was (F3).
 *
 * @property int $id
 * @property int $zone_id
 * @property string $type
 * @property float|null $minimum_order_amount
 * @property bool $status
 */
class FreeDelivery extends Model
{
    use HasFactory;

    /** Every order in the zone/module rides free. */
    public const TYPE_ALL = 'all_store';

    /** Only orders reaching `minimum_order_amount` ride free. */
    public const TYPE_CRITERIA = 'specific_criteria';

    /** The validation whitelist. */
    public const TYPES = [self::TYPE_ALL, self::TYPE_CRITERIA];

    protected $fillable = [
        'zone_id',
        'type',
        'minimum_order_amount',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'zone_id' => 'integer',
        'minimum_order_amount' => 'float',
        'status' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'free_delivery_module')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeForZoneModule(Builder $query, mixed $zoneId, mixed $moduleId): Builder
    {
        return $query->where('zone_id', $zoneId)
            ->whereHas('modules', fn ($q) => $q->where('modules.id', $moduleId));
    }

    /**
     * Does this setup free an order of the given value?
     *
     * §10.3 — `all_store` frees everything; `specific_criteria` frees orders reaching the
     * amount, and a setup with NO amount still covers every order. That last case is not a
     * loophole: rows saved before the form required an amount carry null, and treating null as
     * "never free" would silently switch off setups an admin believes are live.
     */
    public function frees(float $orderAmount): bool
    {
        if ($this->type === self::TYPE_ALL) {
            return true;
        }

        return $this->minimum_order_amount === null
            || $orderAmount >= (float) $this->minimum_order_amount;
    }

    /**
     * The name shipped clients know this type by.
     *
     * `/config`'s `admin_free_delivery.type` is the old wire contract and apps switch on it, so
     * it keeps answering in the old words however the column is spelled (§10.2, N9).
     */
    public function apiType(): string
    {
        return $this->type === self::TYPE_CRITERIA
            ? 'free_delivery_by_specific_criteria'
            : 'free_delivery_to_all_store';
    }
}
