<?php

namespace App\Models;

use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * How a (zone, module) arrives at the delivery time a customer is shown.
 *
 * **Everything here is minutes.**
 *
 * @property int $id
 * @property string|null $name
 * @property int $zone_id
 * @property string $calculation_method
 * @property int|null $minimum_delivery_time
 * @property int|null $preparation_buffer
 * @property int|null $transit_buffer
 * @property int|null $time_gap
 * @property bool $status
 */
class EtaConfiguration extends Model
{
    use HasFactory, HasTranslationsTrait, InvalidatesCacheTrait;

    /**
     * S19 — availability is now "delivery rule AND ETA configuration for this (zone, module)",
     * and the `/module` list is filtered on it. That answer is cached under the `zone` tag, so a
     * rule going active or inactive has to bust it or the gate is cosmetic: the customer keeps
     * being offered a module the zone stopped being able to serve.
     */
    protected static array $cacheTags = ['zone'];

    /** Travel time comes from the map's `delivery_duration`; `time_gap` widens the range. */
    public const METHOD_DISTANCE = 'distance_based';

    /** Travel time comes from the store's own delivery time, which is already a range. */
    public const METHOD_FIXED = 'fixed_delivery_time';

    /** The validation whitelist. */
    public const METHODS = [self::METHOD_DISTANCE, self::METHOD_FIXED];

    /**
     * The range gap a distance-based configuration falls back to, in minutes.
     *
     * Not merely a convenience. Left null, maximumEtaMinutes() adds `(int) null` and the maximum
     * equals the minimum, so etaRangeLabel() renders "10 min" where the whole point of the
     * distance method is "10 min - 15 min". A distance-based estimate with no gap is not a
     * configuration choice, it is a range that collapsed.
     */
    public const DEFAULT_TIME_GAP = 5;

    protected $fillable = [
        'name',
        'zone_id',
        'calculation_method',
        'minimum_delivery_time',
        'preparation_buffer',
        'transit_buffer',
        'time_gap',
        'parcel_minimum_delivery_time',
        'parcel_transit_buffer',
        'parcel_time_gap',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'zone_id' => 'integer',
        'minimum_delivery_time' => 'integer',
        'preparation_buffer' => 'integer',
        'transit_buffer' => 'integer',
        'time_gap' => 'integer',
        'parcel_minimum_delivery_time' => 'integer',
        'parcel_transit_buffer' => 'integer',
        'parcel_time_gap' => 'integer',
        'status' => 'boolean',
    ];

    /** Read by the admin edit form's language tabs, and by BaseResource's translation block. */
    public array $translatable = ['name'];

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'eta_configuration_module')->withTimestamps();
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

    /** The label the list's ETA Type column shows. */
    public function methodLabel(): string
    {
        return $this->calculation_method === self::METHOD_FIXED
            ? translate('Fixed delivery time')
            : translate('Distance based');
    }

    /** True when this method widens its estimate with a gap of its own. */
    public function usesTimeGap(): bool
    {
        return $this->calculation_method === self::METHOD_DISTANCE;
    }

    /** Added to every estimate, whichever method is chosen, before travel time is counted. */
    public function bufferMinutes(): int
    {
        return (int) $this->preparation_buffer + (int) $this->transit_buffer;
    }

    /**
     * The shortest estimate this configuration can produce.
     *
     * `minimum_delivery_time` is a FLOOR rather than a term (§11.2): the buffers and the travel
     * time are added up first, and only if that total comes in under the floor does the floor
     * replace it. With no order in hand there is no travel time, so this is the buffers alone
     * against the floor — the estimate of a journey taking no time at all.
     *
     * EtaService is what estimates a real order; this exists so the panel can preview the same
     * arithmetic without one.
     */
    public function minimumEtaMinutes(): int
    {
        return max($this->bufferMinutes(), (int) $this->minimum_delivery_time);
    }

    /**
     * The longest estimate, or null when the configuration cannot name one alone — the fixed
     * method reads its maximum from the store, which is only known once an order is in hand.
     */
    public function maximumEtaMinutes(): ?int
    {
        return $this->usesTimeGap()
            ? $this->minimumEtaMinutes() + (int) $this->time_gap
            : null;
    }

    /** The estimate as a customer would see it, e.g. "10 min - 15 min". */
    public function etaRangeLabel(): string
    {
        $unit = translate('ETA minute unit');
        $maximum = $this->maximumEtaMinutes();

        return $maximum === null || $maximum === $this->minimumEtaMinutes()
            ? "{$this->minimumEtaMinutes()} {$unit}"
            : "{$this->minimumEtaMinutes()} {$unit} - {$maximum} {$unit}";
    }

    /**
     * Parcel's own shortest estimate — no preparation buffer to fold in, since a parcel order
     * has no kitchen. Mirrors minimumEtaMinutes() so the create/edit form can preview parcel's
     * range with the same arithmetic EtaService applies to a real parcel order.
     */
    public function parcelMinimumEtaMinutes(): int
    {
        return max((int) $this->parcel_transit_buffer, (int) $this->parcel_minimum_delivery_time);
    }

    /** Parcel is always distance-based, so this always widens by its own gap — never null. */
    public function parcelMaximumEtaMinutes(): int
    {
        return $this->parcelMinimumEtaMinutes() + (int) $this->parcel_time_gap;
    }

    /** Parcel's estimate as a customer would see it, e.g. "10 min - 15 min". */
    public function parcelEtaRangeLabel(): string
    {
        $unit = translate('ETA minute unit');
        $minimum = $this->parcelMinimumEtaMinutes();
        $maximum = $this->parcelMaximumEtaMinutes();

        return $maximum === $minimum
            ? "{$minimum} {$unit}"
            : "{$minimum} {$unit} - {$maximum} {$unit}";
    }
}
