<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The two saver offers a (zone, module) makes alongside standard delivery.
 *
 * **Both time columns are minutes.** The form offers Min/Hour and multiplies the hour choice out
 * before saving, so nothing downstream carries a unit next to the number.
 *
 * @property int $id
 * @property int $zone_id
 * @property float|null $express_extra_charge
 * @property int|null $express_reduce_delivery_time
 * @property float|null $delay_reduce_charge
 * @property int|null $delay_add_delivery_time
 * @property bool $status
 */
class AdditionalDeliveryCharge extends Model
{
    use HasFactory;

    /** Pay more, wait less. */
    public const TYPE_EXPRESS = 'express';

    /** Wait more, pay less. */
    public const TYPE_SLIGHTLY_DELAY = 'slightly_delay';

    protected $fillable = [
        'zone_id',
        'express_extra_charge',
        'express_reduce_delivery_time',
        'delay_reduce_charge',
        'delay_add_delivery_time',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'zone_id' => 'integer',
        'express_extra_charge' => 'float',
        'express_reduce_delivery_time' => 'integer',
        'delay_reduce_charge' => 'float',
        'delay_add_delivery_time' => 'integer',
        'status' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'additional_delivery_charge_module')->withTimestamps();
    }

    /**
     * The vehicle categories allowed to take an EXPRESS order from this setup.
     *
     * Empty means no filter — every category may take it. See the pivot's migration.
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(DMVehicle::class, 'additional_delivery_charge_vehicle', 'additional_delivery_charge_id', 'd_m_vehicle_id')
            ->withTimestamps();
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
     * Whether this setup actually makes the express offer.
     *
     * Both halves are required for it to mean anything: a charge with no time saved is a price
     * rise the customer gets nothing for, and time saved with no charge is a free upgrade. The
     * form marks both required; this is the guard for rows that predate it or arrive by import.
     */
    public function offersExpress(): bool
    {
        return $this->express_extra_charge !== null && $this->express_reduce_delivery_time !== null;
    }

    /** The mirror of offersExpress() for the slightly-delayed offer. */
    public function offersDelay(): bool
    {
        return $this->delay_reduce_charge !== null && $this->delay_add_delivery_time !== null;
    }

    /**
     * Minutes rendered as the pair the form edits — 90 becomes 1 hour, 45 stays 45 min.
     *
     * Only exact hours collapse; 90 minutes shown as "1.5 hour" would not survive a round trip
     * through an integer field.
     */
    public static function minutesToPair(?int $minutes): array
    {
        $minutes = (int) $minutes;

        return $minutes >= 60 && $minutes % 60 === 0
            ? ['value' => intdiv($minutes, 60), 'unit' => 'hour']
            : ['value' => $minutes, 'unit' => 'min'];
    }

    /** The form's value + unit back to the minutes the column stores. */
    public static function pairToMinutes(mixed $value, mixed $unit = 'min'): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value) * ($unit === 'hour' ? 60 : 1);
    }
}
