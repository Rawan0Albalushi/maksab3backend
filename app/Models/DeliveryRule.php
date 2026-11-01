<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How one (zone, module) prices delivery.
 *
 * @property int $id
 * @property int $zone_id
 * @property int $module_id
 * @property string $name
 * @property float $minimum_delivery_charge
 * @property string $pricing_method
 * @property float|null $per_km_charge
 * @property float|null $maximum_delivery_charge
 * @property float|null $fixed_charge
 * @property bool $status
 */
class DeliveryRule extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    /**
     * S19 — availability is now "delivery rule AND ETA configuration for this (zone, module)",
     * and the `/module` list is filtered on it. That answer is cached under the `zone` tag, so a
     * rule going active or inactive has to bust it or the gate is cosmetic: the customer keeps
     * being offered a module the zone stopped being able to serve.
     */
    protected static array $cacheTags = ['zone'];

    /**
     * Constant names follow the ported admin views, which reference METHOD_AREA and friends
     * directly. Keeping the view markup byte-identical to the design is worth more than a
     * name of my own choosing.
     */
    public const METHOD_AREA = 'area_wise';

    public const METHOD_ZIP = 'zip_code_wise';

    public const METHOD_DISTANCE = 'distance_wise';

    public const METHOD_FIXED = 'fixed_amount';

    /** The four base methods. Weight and dimension tiers are deferred with parcel. */
    public const PRICING_METHODS = [
        self::METHOD_AREA,
        self::METHOD_ZIP,
        self::METHOD_DISTANCE,
        self::METHOD_FIXED,
    ];

    /** The two methods that need the customer to pick something. */
    public const COVERAGE_METHODS = [
        self::METHOD_AREA,
        self::METHOD_ZIP,
    ];

    protected $fillable = [
        'zone_id',
        'module_id',
        'name',
        'minimum_delivery_charge',
        'pricing_method',
        'per_km_charge',
        'maximum_delivery_charge',
        'fixed_charge',
        'status',
        // The wizard's two parcel steps. Each is a property of the RULE, not of any one charge
        // row, because the wizard holds everything until Submit (parcel brief §6a question 1).
        'weight_charge_status',
        'dimension_charge_status',
    ];

    protected $casts = [
        'id' => 'integer',
        'zone_id' => 'integer',
        'module_id' => 'integer',
        'minimum_delivery_charge' => 'float',
        'per_km_charge' => 'float',
        'maximum_delivery_charge' => 'float',
        'fixed_charge' => 'float',
        'status' => 'boolean',
        'weight_charge_status' => 'boolean',
        'dimension_charge_status' => 'boolean',
    ];

    /**
     * THE ONE-ACTIVE-RULE INVARIANT (§5.2).
     *
     * Enforced here rather than in the controller because three separate paths can switch a rule
     * on — the status endpoint, a store, and an update that moves a rule to another zone or
     * module. A controller-level check is bypassable by the other two, and the failure is silent:
     * two active rules for one (zone, module) and whichever the query happens to return first
     * decides the price.
     *
     * `saveQuietly` is deliberate on the siblings: re-firing `saved` for each one would recurse.
     */
    protected static function booted(): void
    {
        static::saved(function (self $rule) {
            if (! $rule->status) {
                return;
            }

            // A rule now claims a SET of modules, so activating it switches off every other
            // active rule in the zone that shares ANY module with it — not just one whose
            // single module_id matches. Overlap, not equality.
            $moduleIds = $rule->relationLoaded('modules')
                ? $rule->modules->pluck('id')->all()
                : $rule->modules()->pluck('modules.id')->all();

            if (! $moduleIds && $rule->module_id) {
                $moduleIds = [$rule->module_id];
            }

            if (! $moduleIds) {
                return;
            }

            static::query()
                ->where('zone_id', $rule->zone_id)
                ->whereKeyNot($rule->getKey())
                ->where('status', 1)
                ->where(function (Builder $q) use ($moduleIds) {
                    $q->whereHas('modules', fn (Builder $m) => $m->whereIn('modules.id', $moduleIds))
                        ->orWhereIn('module_id', $moduleIds);
                })
                ->update(['status' => 0]);
        });
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Kept for one release as the fallback for rows written before the pivot existed. New code
     * reads modules(), never this — the design connects a rule to MANY modules.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'delivery_rule_module')->withTimestamps();
    }

    /**
     * The additive parcel tiers. Separate from `charges()`, which selects the BASE — these stack
     * on top of whatever the pricing method produced.
     */
    public function weightCharges(): HasMany
    {
        return $this->hasMany(DeliveryRuleWeightCharge::class);
    }

    public function dimensionCharges(): HasMany
    {
        return $this->hasMany(DeliveryRuleDimensionCharge::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(DeliveryRuleCharge::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Rules in this zone that claim this module. Reads the pivot, falling back to the legacy
     * single column for rows the backfill has not reached (a half-migrated install).
     */
    public function scopeForZoneModule(Builder $query, mixed $zoneId, mixed $moduleId): Builder
    {
        return $query->where('zone_id', $zoneId)
            ->where(function (Builder $q) use ($moduleId) {
                $q->whereHas('modules', fn (Builder $m) => $m->where('modules.id', $moduleId))
                    ->orWhere('module_id', $moduleId);
            });
    }

    public function needsCoveragePick(): bool
    {
        return in_array($this->pricing_method, self::COVERAGE_METHODS, true);
    }

    /** Alias the ported views use. Same question, the design's wording. */
    public function usesChargeTable(): bool
    {
        return $this->needsCoveragePick();
    }

    /** The human label for this rule's method, resolved here so no template computes it. */
    public function methodLabel(): string
    {
        return match ($this->pricing_method) {
            self::METHOD_AREA => translate('Area wise'),
            self::METHOD_ZIP => translate('Zip code wise'),
            self::METHOD_DISTANCE => translate('Distance wise'),
            default => translate('Fixed amount'),
        };
    }
}
