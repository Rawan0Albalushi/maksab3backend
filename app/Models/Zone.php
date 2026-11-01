<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;
use Illuminate\Database\Eloquent\Builder;
use App\Scopes\ZoneScope;
use Modules\RideShare\Entities\FareManagement\RideFare;
use Modules\RideShare\Entities\FareManagement\ZoneWiseDefaultRideFare;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use App\Traits\Model\HasTranslationsTrait;

/**
 * Class Zone
 *
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property mixed $coordinates
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $store_wise_topic
 * @property string|null $customer_wise_topic
 * @property string|null $deliveryman_wise_topic
 * @property int $cash_on_delivery
 * @property int $digital_payment
 * @property float $increased_delivery_fee
 * @property int $increased_delivery_fee_status
 * @property string|null $increase_delivery_charge_message
 * @property int $offline_payment
 * @property boolean $is_default
 */
class Zone extends Model
{
    use HasFactory, HasTranslationsTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['zone'];
    use HasSpatial;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'name',
        'display_name',
        'coordinates',
        'status',
        'store_wise_topic',
        'customer_wise_topic',
        'deliveryman_wise_topic',
        'cash_on_delivery',
        'digital_payment',
        'increased_delivery_fee',
        'increased_delivery_fee_status',
        'increase_delivery_charge_message',
        'offline_payment',
        'is_default'
    ];

    protected $casts = [
        'status' => 'integer',
        'increased_delivery_fee_status' => 'integer',
        'increased_delivery_fee' => 'integer',
        'cash_on_delivery' => 'boolean',
        'digital_payment' => 'boolean',
        'offline_payment' => 'boolean',
        'fixed_shipping_charge' => 'float',
        'coordinates' => Polygon::class,
        'is_default' => 'boolean',
    ];

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    public function getDisplayNameAttribute($value)
    {
        return $this->translatedAttribute('display_name', $value);
    }

    /**
     * The Zone Setup search, which the field's own placeholder defines: "Search by Vendor name,
     * owner info...".
     *
     * It used to match `zones.name` alone, so typing a vendor or a store returned "no data found"
     * and an admin reasonably concluded the zone did not exist (QA case TC_152). It now looks at
     * the zone, the stores inside it, and the people behind those stores.
     *
     * Each word is matched independently and ORed, which is how the old query behaved — "Food
     * Fair" finds a zone containing a store called "Food" or "Fair". Kept deliberately: narrowing
     * it to an AND would silently drop results an admin used to get.
     *
     * `whereHas` rather than a join: a zone with three matching stores must appear once, and a
     * join would repeat it per store and break the count badge.
     */
    public function scopeMatchingSearch(Builder $query, ?string $search): Builder
    {
        $words = array_filter(array_map('trim', explode(' ', (string) $search)));

        if ($words === []) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($words) {
            foreach ($words as $word) {
                $like = '%'.$word.'%';

                $outer->orWhere('name', 'like', $like)
                    ->orWhereHas('stores', fn (Builder $store) => $store
                        ->where('stores.name', 'like', $like)
                        ->orWhereHas('vendor', fn (Builder $vendor) => $vendor
                            ->where('f_name', 'like', $like)
                            ->orWhere('l_name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('email', 'like', $like)));
            }
        });
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function deliverymen(): HasMany
    {
        return $this->hasMany(DeliveryMan::class);
    }

    public function surge_prices(): HasMany
    {
        return $this->hasMany(SurgePrice::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Store::class);
    }


    public function campaigns(): HasManyThrough
    {
        return $this->hasManyThrough(Campaigns::class, Store::class);
    }

    public function tripFares()
    {
        return $this->hasMany(RideFare::class, 'zone_id');
    }

    public function tripRequest()
    {
        return $this->hasMany(RideRequest::class, 'zone_id');
    }

    public function defaultFare()
    {
        return $this->hasOne(ZoneWiseDefaultRideFare::class, 'zone_id');
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(DeliveryMan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function scopeContains($query,$abc){
        return $query->whereRaw("ST_Distance_Sphere(coordinates, POINT({$abc}))");
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ZoneScope);




    }

    public function deliveryRules(): HasMany
    {
        return $this->hasMany(DeliveryRule::class);
    }

    public function etaConfigurations(): HasMany
    {
        return $this->hasMany(EtaConfiguration::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Readiness — design rule Z3, and port doc A15
    |--------------------------------------------------------------------------
    |
    | Two different questions, deliberately kept apart, because StackFood learned the difference
    | the hard way:
    |
    |   READY TO ACTIVATE — may an admin switch this zone on? Needs AT LEAST ONE connected module
    |                       that is COMPLETE: it carries every setup its type requires.
    |
    |   AVAILABLE         — may a customer be offered this (zone, module)? The same COMPLETE test,
    |                       per pair, on a zone that is switched on.
    |
    | S19 changed both. Readiness used to demand that EVERY connected module be complete, which
    | held a whole zone dark for one unconfigured module; and availability used to ask for a
    | delivery rule ALONE, which offered a module that could be priced but not timed. Now one
    | complete module opens the zone, and the modules that are not complete are simply unavailable
    | in it — the admin is told which, and confirms.
    |
    | COMPLETE means "carries every setup its type requires", not "carries both". Rental,
    | ride-share and service price their own trips and bookings and can hold neither a delivery
    | rule nor an ETA configuration, so demanding both of them would take those modules dark for
    | ever and make a zone connected only to them permanently unready. The capability lists below
    | are the exemption, and they are the SAME lists the delivery-rule and ETA pickers use, so a
    | module can never be required to hold a setup its own setup screen refuses to offer it.
    */

    /** An active delivery rule anywhere in the zone. */
    public function hasDeliveryChargeSetup(): bool
    {
        return $this->deliveryRules()->where('status', 1)->exists();
    }

    public function scopeWithDeliveryChargeSetup(Builder $query): Builder
    {
        return $query->whereHas('deliveryRules', fn ($q) => $q->where('status', 1));
    }

    public function hasEtaSetup(): bool
    {
        return $this->etaConfigurations()->where('status', 1)->exists();
    }

    public function scopeWithEtaSetup(Builder $query): Builder
    {
        return $query->whereHas('etaConfigurations', fn ($q) => $q->where('status', 1));
    }

    /** Module ids in this zone that carry an ACTIVE delivery rule. */
    public function ruledModuleIds(): array
    {
        return $this->deliveryRules()->where('status', 1)
            ->with('modules:id')->get()
            ->flatMap(fn ($rule) => $rule->modules->pluck('id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Module ids in this zone that carry an ACTIVE ETA configuration. */
    public function timedModuleIds(): array
    {
        return $this->etaConfigurations()->where('status', 1)
            ->with('modules:id')->get()
            ->flatMap(fn ($configuration) => $configuration->modules->pluck('id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * The modules this zone is connected to.
     *
     * Reads the loaded relation when there is one. `findByCoordinates()` eager-loads `modules`
     * and then asks this (through `effectiveModuleIds()`) for every zone containing the point —
     * going back to the database for a list already in memory is a query per zone on the hottest
     * customer path there is.
     *
     * @return array<int, int>
     */
    public function connectedModuleIds(): array
    {
        $ids = $this->relationLoaded('modules')
            ? $this->getRelation('modules')->pluck('id')
            : $this->modules()->pluck('modules.id');

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * The gaps between this zone and serving ANYONE, keyed to what has to be added.
     *
     * A zone is ready when AT LEAST ONE connected module is complete (S19). Demanding it of
     * every connected module — the rule between S18 and S19 — held a whole zone dark for one
     * module nobody had configured yet, which is a heavier answer than the problem: the modules
     * that are not complete simply cannot be served, and that is a per-module fact the zone list
     * and the confirm dialog now state outright.
     *
     * Empty here means "the toggle may proceed", NOT "everything works" — ask
     * `incompleteModuleIds()` for what will still be dark afterwards.
     *
     * @return array<int, string> empty when the zone may be switched on
     */
    public function readinessGaps(): array
    {
        return self::gapsBetween($this->connectedModuleIds(), $this->ruledModuleIds(), $this->timedModuleIds());
    }

    /**
     * Which module ids may carry an ETA, memoised for the row-by-row callers.
     *
     * `gapsBetween()` is static and called once per zone by the list, so resolving the service
     * per call would be a lookup per row. ModuleService memoises the module list itself; this
     * holds the filtered ids for the request.
     *
     * @return array<int, int>
     */
    private static function etaCapableModuleIds(): array
    {
        return static::$etaCapableMemo ??= app(\App\Services\System\ModuleService::class)->etaCapableModuleIds();
    }

    /** @var array<int, int>|null */
    private static ?array $etaCapableMemo = null;

    /** @return array<int, int> */
    private static function deliveryRuleModuleIds(): array
    {
        return static::$deliveryRuleMemo ??= app(\App\Services\System\ModuleService::class)->deliveryRuleModuleIds();
    }

    /** @var array<int, int>|null */
    private static ?array $deliveryRuleMemo = null;

    /** Cleared where the module list itself is — a new module changes these answers. */
    public static function forgetEtaCapableModules(): void
    {
        static::$etaCapableMemo = null;
        static::$deliveryRuleMemo = null;
    }

    /**
     * The gap logic itself, over three module-id lists — shared with the batched lookup in
     * ZoneService so the list and the guards cannot answer differently.
     *
     * @return array<int, string>
     */
    public static function gapsBetween(array $connectedModuleIds, array $ruledModuleIds, array $timedModuleIds): array
    {
        // No module connected, nothing to serve. Checked first because every test below is
        // vacuously satisfied by an empty list, which would let an empty zone switch on.
        if ($connectedModuleIds === []) {
            return ['module'];
        }

        // ONE complete module opens the zone (S19). The rest are not a blocking gap — they are
        // unavailable in it, which is `incompleteBetween()`'s answer, not this one's.
        if (static::completeBetween($connectedModuleIds, $ruledModuleIds, $timedModuleIds) !== []) {
            return [];
        }

        // Nothing is complete. Which of the two setups is short decides the wording, and the
        // three-way split is kept so an admin who has already added one is not told to add it
        // again. Both lists are exempt-aware for the reason the header gives.
        $needsRule = array_intersect($connectedModuleIds, static::deliveryRuleModuleIds());
        $missingRule = array_diff($needsRule, $ruledModuleIds) !== [];

        $needsEta = array_intersect($connectedModuleIds, static::etaCapableModuleIds());
        $missingEta = array_diff($needsEta, $timedModuleIds) !== [];

        // The fourth state: a delivery rule and an ETA configuration BOTH already exist on
        // CONNECTED modules — just never the same one, so no module is complete. Checked against
        // $connectedModuleIds specifically, not raw non-emptiness of $ruledModuleIds/
        // $timedModuleIds: a rule or ETA can exist in the zone for a module that isn't even
        // connected any more (e.g. disconnected after being set up), which is not "paired
        // elsewhere" — it is simply irrelevant, and must still fall through to the genuine
        // both-missing wording below.
        if ($missingRule && $missingEta
            && array_intersect($connectedModuleIds, $ruledModuleIds) !== []
            && array_intersect($connectedModuleIds, $timedModuleIds) !== []) {
            return ['pairing'];
        }

        $gaps = [];

        if ($missingRule) {
            $gaps[] = 'delivery_rule';
        }

        if ($missingEta) {
            $gaps[] = 'eta';
        }

        return $gaps;
    }

    /**
     * The connected modules this zone can actually serve: each carries every setup its type
     * requires — a delivery rule if it can be priced, an ETA configuration if it can be timed.
     *
     * This is the ONE definition of "available". `gapsBetween()` asks whether it is non-empty,
     * `effectiveModuleIds()` returns it, `scopeEffective()` expresses it in SQL, and the zone
     * list reports its complement. Nothing computes availability its own way.
     *
     * Order-independent and re-indexed: callers compare these with `==` and json-encode them.
     *
     * @return array<int, int>
     */
    public static function completeBetween(array $connectedModuleIds, array $ruledModuleIds, array $timedModuleIds): array
    {
        $needsRule = static::deliveryRuleModuleIds();
        $needsEta = static::etaCapableModuleIds();

        $complete = array_filter(
            $connectedModuleIds,
            function ($moduleId) use ($ruledModuleIds, $timedModuleIds, $needsRule, $needsEta) {
                if (in_array($moduleId, $needsRule, true) && ! in_array($moduleId, $ruledModuleIds, true)) {
                    return false;
                }

                if (in_array($moduleId, $needsEta, true) && ! in_array($moduleId, $timedModuleIds, true)) {
                    return false;
                }

                return true;
            }
        );

        sort($complete);

        return array_values($complete);
    }

    /**
     * Connected but not complete — the modules that go dark when this zone is switched on.
     *
     * @return array<int, int>
     */
    public static function incompleteBetween(array $connectedModuleIds, array $ruledModuleIds, array $timedModuleIds): array
    {
        $incomplete = array_diff(
            $connectedModuleIds,
            static::completeBetween($connectedModuleIds, $ruledModuleIds, $timedModuleIds)
        );

        sort($incomplete);

        return array_values($incomplete);
    }

    /** @return array<int, int> */
    public function completeModuleIds(): array
    {
        return static::completeBetween($this->connectedModuleIds(), $this->ruledModuleIds(), $this->timedModuleIds());
    }

    /** @return array<int, int> */
    public function incompleteModuleIds(): array
    {
        return static::incompleteBetween($this->connectedModuleIds(), $this->ruledModuleIds(), $this->timedModuleIds());
    }

    /**
     * Which connected modules are missing which setup.
     *
     * The gap keys above say WHAT is missing; this says WHERE, so the admin is not left comparing
     * two screens to find the one module holding the zone back.
     *
     * Exempt-aware, like everything else here: a module whose type can hold no delivery rule is
     * not "missing" one. Without that, a zone connected to Rental was told to add Rental a
     * delivery rule — advice the delivery-rule picker refuses to let anyone take.
     *
     * @return array{delivery_rule: array<int, int>, eta: array<int, int>} module ids
     */
    public static function missingByModule(array $connectedModuleIds, array $ruledModuleIds, array $timedModuleIds): array
    {
        return [
            'delivery_rule' => array_values(array_diff(
                array_intersect($connectedModuleIds, static::deliveryRuleModuleIds()),
                $ruledModuleIds
            )),
            'eta' => array_values(array_diff(
                array_intersect($connectedModuleIds, static::etaCapableModuleIds()),
                $timedModuleIds
            )),
        ];
    }

    /** May the toggle proceed — i.e. is at least one connected module complete? */
    public function isReadyToActivate(): bool
    {
        return $this->readinessGaps() === [];
    }

    /**
     * Why the zone may not be switched on, as a translation key.
     *
     * Names only what is actually absent — an admin who has already added one of the two is not
     * told to add it again. Every path that switches a zone on reads from here, so they cannot
     * drift into saying different things about the same zone.
     *
     * Pass the gaps when the caller already has them: `readinessGaps()` costs two `exists()`
     * queries and there is no reason to run them twice.
     */
    public function readinessMessageKey(?array $gaps = null, bool $partial = false): string
    {
        // $partial is the S19 state: the zone MAY be switched on, but not everything it is
        // connected to can be served. It is not a gap — gaps refuse a switch-on — so it cannot be
        // expressed in the match below, which is why every one of these four takes the flag.
        if ($partial) {
            return 'messages.Zone switched on. :modules will not be available in it until each has a delivery charge rule and an ETA configuration.';
        }

        return match ($gaps ?? $this->readinessGaps()) {
            ['module'] => 'messages.Connect_at_least_one_module_to_this_zone_before_activating_it',
            ['delivery_rule'] => 'messages.Add_a_delivery_rule_for_this_zone_before_activating_it',
            ['eta'] => 'messages.Add_an_ETA_configuration_for_this_zone_before_activating_it',
            ['pairing'] => 'messages.This zone has a delivery rule and an ETA configuration, but not on the same module yet. Pair one module with both before activating this zone.',
            default => 'messages.Add_a_delivery_rule_and_an_ETA_configuration_for_this_zone_before_activating_it',
        };
    }

    /**
     * The same three-way split as readinessMessageKey(), worded for the row's warning mark.
     *
     * Two different sentences are wanted for the same fact: the toggle guard explains why a
     * switch refused, this explains why a zone is not serving anyone. They stay together so a
     * fourth caller cannot invent a third wording.
     */
    public function readinessNoticeKey(?array $gaps = null, bool $partial = false): string
    {
        if ($partial) {
            return 'messages.These modules are connected to this Business Zone but cannot be served in it yet: :modules';
        }

        return match ($gaps ?? $this->readinessGaps()) {
            ['module'] => 'messages.The_Business_Zone_will_NOT_work_until_a_module_is_connected_to_it.',
            ['delivery_rule'] => 'messages.The_Business_Zone_will_NOT_work_until_you_add_a_delivery_rule_for_it.',
            ['eta'] => 'messages.The_Business_Zone_will_NOT_work_until_you_add_an_ETA_configuration_for_it.',
            ['pairing'] => 'messages.The Business Zone will NOT work until one connected module has both a delivery rule and an ETA configuration — it currently has each on a different module.',
            default => 'messages.The_Business_Zone_will_NOT_work_until_you_add_both_a_delivery_rule_and_an_ETA_configuration_for_it.',
        };
    }

    /**
     * The zone's name as it reads in front of the word "Zone".
     *
     * Every heading that names a zone is written ":zone Zone" — "Connect Module With Dhaka
     * Zone". A zone actually called "Main Demo Zone" then reads "Main Demo Zone Zone", so a
     * name that already ends in the word gives it up here.
     */
    public function labelStem(): string
    {
        return (string) preg_replace('/\s*zone\s*$/i', '', (string) $this->name) ?: (string) $this->name;
    }

    /**
     * The dialog's heading, narrowed to what is actually missing.
     *
     * The design draws the both-missing case; naming "Delivery Charge & ETA" over a body that
     * asks for one of them reads as a mistake, so the other two cases say what they mean.
     */
    public function readinessTitleKey(?array $gaps = null, bool $partial = false): string
    {
        if ($partial) {
            return 'messages.Some Modules Will Be Unavailable In :zone Zone';
        }

        // Every caller already passes ['zone' => $zone->labelStem()] (see the `partial` branch
        // above, and _table_rows.blade.php's data-title) — these just never carried a :zone
        // token to receive it, so the heading silently dropped the zone's name. Fixed for TC_58,
        // whose figma names the zone explicitly ("Set Up Delivery Charge & ETA For Dhaka Zone").
        return match ($gaps ?? $this->readinessGaps()) {
            ['module'] => 'messages.Finish setting up :zone zone',
            ['delivery_rule'] => 'messages.Set up delivery charge for :zone zone',
            ['eta'] => 'messages.Set up ETA for :zone zone',
            ['pairing'] => 'messages.Pair a delivery rule with an ETA for :zone zone',
            default => 'messages.Set up delivery charge & ETA for :zone zone',
        };
    }

    /**
     * The lead line of the dialog the status toggle opens, again by what is missing.
     *
     * The both-missing case is the design's own wording; the other two name the single thing
     * left to do, because an admin who has already added one should not be told to add it again.
     */
    public function readinessPromptKey(?array $gaps = null, bool $partial = false): string
    {
        if ($partial) {
            return 'messages.These modules have no delivery charge rule or ETA configuration for this zone yet. Switching the zone on leaves them unavailable to customers until both are set.';
        }

        return match ($gaps ?? $this->readinessGaps()) {
            ['module'] => 'messages.This_zone_serves_no_module_yet._Connect_one_from_the_Connect_Module_panel,_then_give_it_a_delivery_rule_and_an_ETA_configuration.',
            ['delivery_rule'] => 'messages.This_zone_cannot_be_activated_until_it_can_price_a_delivery._Create_the_charge_rule_below,_then_switch_the_zone_on.',
            ['eta'] => 'messages.This_zone_cannot_be_activated_until_it_can_quote_a_delivery_time._Set_up_the_ETA_below,_then_switch_the_zone_on.',
            // The fourth state (gapsBetween()): a delivery rule and an ETA configuration both
            // already exist in this zone, just never on the same module — telling the admin to
            // "add both" here would be wrong, since neither is actually missing.
            ['pairing'] => 'messages.This zone already has a delivery rule and an ETA configuration, but never on the same module. Add whichever is missing to a module that already has the other, then switch the zone on.',
            default => 'messages.After creating a new zone, you must configure the delivery charge rules and ETA for that zone. Until both are configured, the zone will not be available to customers, vendors, or deliverymen.',
        };
    }

    /**
     * Switched on and able to serve at least one module — the only zones a customer may be routed
     * into (A15).
     *
     * The exact rule, in SQL, not a prefilter. S19 asked for a choice between a cheap zone-level
     * prefilter and a join on the pair; the prefilter is not available here. `withEtaSetup()` and
     * `withDeliveryChargeSetup()` ask whether the ZONE holds a setup, and a zone connected only to
     * rental, ride-share or service holds neither yet serves all three — so any prefilter built
     * from them drops zones that are perfectly effective. The correlated EXISTS below is the same
     * predicate `completeBetween()` applies in PHP, so the two cannot disagree; use
     * `effectiveModuleIds()` when you need WHICH modules rather than WHETHER any.
     *
     * The capability lists are id arrays resolved from config, so they go into the query as
     * `whereNotIn` — a module that can hold neither setup satisfies both halves outright.
     */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->active()->whereHas('modules', fn ($modules) => static::constrainToAvailable($modules));
    }

    /**
     * `completeBetween()`'s predicate, in SQL, for a builder standing on the `module_zone` pivot.
     *
     * Two callers, deliberately one implementation: `scopeEffective()` reaches it through
     * `Zone::whereHas('modules')`, and the `/module` list through `Module::whereHas('zones')`.
     * Both produce a correlated EXISTS in which `module_zone.zone_id` and `modules.id` resolve,
     * the second through the outer query — so the same closure serves either direction, and the
     * zone list, the zone resolver and the module list cannot disagree about what is available.
     *
     * Whoever calls this still has to say WHICH zones; this only adds the pair test.
     */
    public static function constrainToAvailable(mixed $query): mixed
    {
        $needsRule = static::deliveryRuleModuleIds();
        $needsEta = static::etaCapableModuleIds();

        return $query
            ->where(fn ($q) => $q
                ->whereNotIn('modules.id', $needsRule)
                ->orWhereExists(fn ($sub) => $sub
                    ->from('delivery_rules')
                    ->join('delivery_rule_module', 'delivery_rules.id', '=', 'delivery_rule_module.delivery_rule_id')
                    ->whereColumn('delivery_rules.zone_id', 'module_zone.zone_id')
                    ->whereColumn('delivery_rule_module.module_id', 'modules.id')
                    ->where('delivery_rules.status', 1)))
            ->where(fn ($q) => $q
                ->whereNotIn('modules.id', $needsEta)
                ->orWhereExists(fn ($sub) => $sub
                    ->from('eta_configurations')
                    ->join('eta_configuration_module', 'eta_configurations.id', '=', 'eta_configuration_module.eta_configuration_id')
                    ->whereColumn('eta_configurations.zone_id', 'module_zone.zone_id')
                    ->whereColumn('eta_configuration_module.module_id', 'modules.id')
                    ->where('eta_configurations.status', 1)));
    }

    /**
     * The module ids this zone can actually serve — A15 at MODULE granularity, which is where
     * mart differs from the source: pricing here is per (zone, module), so a zone can be live
     * for Food and dark for Grocery.
     *
     * S19: this is now the COMPLETE set, not the ruled set. Asking for a delivery rule alone
     * offered a module the zone could price but not time — the customer was routed in, saw the
     * module, and the order carried no estimate. It is also now intersected with the CONNECTED
     * modules: a rule may name a module the zone is no longer connected to, and that stale pair
     * used to come back here as servable.
     *
     * @return array<int, int>
     */
    public function effectiveModuleIds(): array
    {
        return $this->completeModuleIds();
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)->withPivot(['per_km_shipping_charge','minimum_shipping_charge','maximum_shipping_charge','maximum_cod_order_amount','delivery_charge_type','fixed_shipping_charge','additional_delivery_option_status','minimum_delivery_time','minimum_delivery_charge'])->using('App\Models\ModuleZone');
    }

    public function moduleDeliveryOptions(): HasMany
    {
        return $this->hasMany(ModuleZoneDeliveryOption::class);
    }

    public static function query(): Builder
    {
        return parent::query();
    }
}
