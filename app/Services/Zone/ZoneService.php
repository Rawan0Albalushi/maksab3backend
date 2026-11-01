<?php

namespace App\Services\Zone;

use App\Exceptions\ZoneModuleException;
use App\Models\Zone;
use App\Scopes\ZoneScope;
use App\Services\BaseService;
use App\Services\Builder\StorefrontVisibilityService;
use App\Services\System\BusinessSettingService;
use App\Services\System\ModuleService;
use App\Support\Cache\ApiCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\AxisOrder;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class ZoneService extends BaseService
{
    public function getDropdown(bool $activeOnly = false): mixed
    {
        $zones = ApiCache::remember('zones', 'dropdown_'.app()->getLocale(), function () {
            return Zone::withoutGlobalScope(ZoneScope::class)
                ->orderBy('name')
                ->get(['id', 'name', 'status']);
        });

        if ($activeOnly) {
            $zones = $zones->where('status', 1);
        }

        $admin = auth('admin')->user();
        if ($admin && $admin->role_id != 1 && $admin->zone_id) {
            $zones = $zones->where('id', $admin->zone_id);
        }

        return $zones->values();
    }

    public function getNamesByIds(mixed $zones): string
    {
        $names = is_array($zones)
            ? Zone::whereIn('id', $zones)->pluck('name')->toArray()
            : Zone::where('id', $zones)->pluck('name')->toArray();

        return implode(', ', $names);
    }

    public function findActiveContaining(mixed $lat, mixed $lng): ?Zone
    {
        return Zone::whereContains('coordinates', new Point($lat, $lng, POINT_SRID))->where('status', 1)->first();
    }

    public function getAddData(array $input, int|string $zoneId): array
    {
        $polygon = $this->polygonFromInput($input);

        return [
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'display_name' => ($input['display_name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'coordinates' => $polygon,
            'store_wise_topic' => 'zone_'.$zoneId.'_store',
            'customer_wise_topic' => 'zone_'.$zoneId.'_customer',
            'deliveryman_wise_topic' => 'zone_'.$zoneId.'_delivery_man',
            'rider_wise_topic' => 'zone_'.$zoneId.'_rider',
            'cash_on_delivery' => ($input['cash_on_delivery'] ?? null) ? 1 : 0,
            'digital_payment' => ($input['digital_payment'] ?? null) ? 1 : 0,
            // OFF, explicitly. `zones.status` defaults to 1 in the schema, so a zone used to be
            // created switched on — before it had a delivery rule or an ETA, which a brand new
            // zone cannot have. Z3 guarded the toggle and the status URL but never creation, so
            // the one route that could not possibly produce a ready zone was the one that
            // skipped the check. The admin connects modules, adds a rule and an ETA, and then
            // switches it on — which is the flow the drawer that opens next describes.
            'status' => 0,
        ];
    }

    public function getUpdateData(array $input, int|string $zoneId): array
    {
        $polygon = $this->polygonFromInput($input);

        return [
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'display_name' => ($input['display_name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'store_wise_topic' => 'zone_'.$zoneId.'_store',
            'customer_wise_topic' => 'zone_'.$zoneId.'_customer',
            'deliveryman_wise_topic' => 'zone_'.$zoneId.'_delivery_man',
            'rider_wise_topic' => 'zone_'.$zoneId.'_rider',
            'coordinates' => $polygon,
        ];
    }

    public function formatCoordinates(array $coordinates): array
    {
        $data = [];
        foreach ($coordinates as $coordinate) {
            $data[] = (object) ['lat' => $coordinate[1], 'lng' => $coordinate[0]];
        }

        return $data;
    }

    public function formatZoneCoordinates(object $zones): array
    {
        $data = [];
        foreach ($zones as $zone) {
            $area = json_decode($zone->coordinates[0]->toJson(), true);
            $data[] = self::formatCoordinates(coordinates: $area['coordinates']);
        }

        return $data;
    }

    public function validateModuleDeliveryCharge(array $moduleData, array $selectedModules, array $serviceModuleIds): array
    {
        foreach ($moduleData as $moduleId => $data) {
            if (in_array($moduleId, $selectedModules) && ! in_array((int) $moduleId, $serviceModuleIds)) {
                $type = $data['delivery_charge_type'] ?? null;

                if ($type === 'fixed') {
                    if (empty($data['fixed_shipping_charge'])) {
                        return ['flag' => 'fixed_required', 'module_id' => $moduleId];
                    }
                } elseif ($type === 'distance') {
                    if (empty($data['per_km_shipping_charge']) || empty($data['minimum_shipping_charge'])) {
                        return ['flag' => 'distance_required', 'module_id' => $moduleId];
                    }

                    if (
                        isset($data['maximum_shipping_charge']) &&
                        is_numeric($data['maximum_shipping_charge']) &&
                        is_numeric($data['minimum_shipping_charge']) &&
                        (float) $data['maximum_shipping_charge'] < (float) $data['minimum_shipping_charge']
                    ) {
                        return ['flag' => 'max_delivery_charge', 'module_id' => $moduleId];
                    }
                } else {
                    return ['flag' => 'unknown_type', 'module_id' => $moduleId];
                }
            }
        }

        return [];
    }

    public function getSearchOptions(mixed $search): mixed
    {
        return Zone::when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);
    }

    public function clearDefaultExcept(mixed $zoneId): void
    {
        Zone::where('id', '!=', $zoneId)->update(['is_default' => 0]);
    }

    public function getCountsByModule(array $moduleIds): array
    {
        return Zone::join('module_zone', 'module_zone.zone_id', '=', 'zones.id')
            ->whereIn('module_zone.module_id', $moduleIds)
            ->groupBy('module_zone.module_id')
            ->selectRaw('module_zone.module_id AS module_id, COUNT(DISTINCT zones.id) AS zone_count')
            ->toBase()->get()
            ->mapWithKeys(fn ($row) => [$row->module_id => (int) $row->zone_count])->all();
    }

    public function findSmallestContaining(mixed $longitude, mixed $latitude): mixed
    {
        return Zone::where('status', 1)
            ->whereContains('coordinates', new Point($longitude, $latitude, POINT_SRID))
            ->selectRaw('zones.*, ABS(ST_Area(coordinates)) as area')
            ->orderBy('area', 'asc')
            ->first();
    }

    public function getActiveWithModules(): array
    {
        return Zone::where('status', 1)->with('modules')->get()
            ->map(fn ($zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'display_name' => $zone->display_name ?: $zone->name,
                'modules' => $zone->modules->pluck('module_name'),
            ])->all();
    }

    /**
     * Active zones as id + name only, for a select. Deliberately separate from getActiveList(),
     * which eager-loads modules with their pivot and storage for the zone screens — far too much
     * for a dropdown, and paginated besides.
     */
    /** Is this the zone customers land in before choosing a location? */
    public function isDefault(mixed $zoneId): bool
    {
        return Zone::whereKey($zoneId)->where('is_default', 1)->exists();
    }

    /**
     * The zone picker on every Delivery Management form.
     *
     * Deliberately NOT filtered by status. Z3 will not let a zone be switched on until one of
     * its modules carries both a delivery rule and an ETA configuration — so an inactive zone is
     * precisely the one an admin has come here to set up, and hiding it makes the requirement
     * impossible to satisfy: the zone stays off because it has no rule, and it can have no rule
     * because it is off.
     *
     * Customer-facing zone lookups are a different question and keep their filter — `active()`
     * still guards routing, the default-zone fallback and the app's zone list, none of which may
     * offer a zone that is switched off.
     */
    public function getSelectOptions(): Collection
    {
        return Zone::orderBy('name')->get(['id', 'name']);
    }

    public function getActiveList(
        array $paginate = []
    ): LengthAwarePaginator {
        $zones = Zone::where('status', 1)
            ->with([
                'modules' => fn ($query) => $query->with('storage')->withPivot([
                    'per_km_shipping_charge',
                    'minimum_shipping_charge',
                    'maximum_shipping_charge',
                    'maximum_cod_order_amount',
                    'delivery_charge_type',
                    'fixed_shipping_charge',
                    'additional_delivery_option_status',
                    'minimum_delivery_time',
                    'minimum_delivery_charge',
                ]),
            ])
            // `display_name` is selected, not just `name`: it is a translated attribute, and an
            // unselected column is null before the accessor ever runs — so the customer payload
            // carried display_name=null for every zone however well it had been translated.
            ->select('id', 'name', 'display_name', 'coordinates')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        foreach ($zones as $zone) {
            $zone->setAttribute('delivery_options_matrix', $this->deliveryOptionsMatrix($zone));
        }

        return $zones;
    }

    public function resolveZoneIds(mixed $zoneIdHeader, mixed $moduleIdHeader = null): array
    {
        if ($zoneIdHeader === null || $zoneIdHeader === '') {
            $zone = Zone::where('status', 1)->where('is_default', 1)->first() ?? Zone::first();

            if (! $zone) {
                throw new ZoneModuleException(translate('No zone is available'));
            }

            if ($moduleIdHeader !== null) {
                $moduleId = getModuleId($moduleIdHeader);

                if (! in_array($moduleId, $zone->modules()?->pluck('module_id')?->toArray())) {
                    throw new ZoneModuleException(translate('Currently this module is available'));
                }
            }

            return [$zone->id];
        }

        $zoneIds = json_decode($zoneIdHeader, true);

        if (is_int($zoneIds)) {
            $zoneIds = [$zoneIds];
        }

        if (! is_array($zoneIds)) {
            $zoneIds = [];
        }

        $zoneIds = Zone::whereIn('id', $zoneIds)->where('status', 1)->pluck('id')->toArray();

        if (empty($zoneIds)) {
            throw new ZoneModuleException(translate('Zone is not available right now'));
        }

        return $zoneIds;
    }

    public function defaultZoneId(): mixed
    {
        return Zone::where('status', 1)->where('is_default', 1)->orderBy('id')->value('id')
            ?? Zone::orderBy('id')->value('id');
    }

    public function findIdsByCoordinates(mixed $latitude, mixed $longitude): array
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return [];
        }

        return Zone::whereContains('coordinates', new Point($latitude, $longitude, POINT_SRID))
            ->pluck('id')
            ->all();
    }

    public function outOfServiceArea(array $serviceZones, array $candidateZones): bool
    {
        return $serviceZones && $candidateZones && ! array_intersect($serviceZones, $candidateZones);
    }

    public function containsCoordinates(mixed $zoneId, mixed $latitude, mixed $longitude): bool
    {
        return $this->containingPointQuery($zoneId, $latitude, $longitude)->exists();
    }

    public function attachIdsByCoordinates(mixed $rows): mixed
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $byIndex = $this->mapIdsByCoordinates(
            $rows->values()->map(fn ($row) => ['latitude' => $row->latitude, 'longitude' => $row->longitude])->all()
        );

        foreach ($rows->values() as $index => $row) {
            $row->setAttribute('zone_ids', $byIndex[$index] ?? []);
        }

        return $rows;
    }

    /**
     * Z3 readiness for a page of zones, in two queries rather than two per row.
     *
     * `Zone::readinessGaps()` costs two `exists()` calls, which is fine for one zone and an N+1
     * across a list (rule 11). The list asks here instead and the Blade only reads the answer.
     *
     * @param  iterable<int, Zone>  $zones
     * @return array<int, array{ready:bool, gaps:array<int,string>, message:string, toggleEnabled:bool}>
     */
    /**
     * Connect Module — the zone's payment methods, the modules it serves, and each module's COD
     * ceiling.
     *
     * `sync` UPDATES the pivot row of a module that stays connected rather than replacing it, so
     * the delivery-charge columns §4.3 keeps for one more release survive a save from this drawer.
     * A module the admin removes loses its pivot row, which is what disconnecting means.
     *
     * @param  array<string, int>  $payments  the three flags, zeros included
     * @param  array<int, float>  $codLimits  keyed by module id
     */
    public function connectModules(mixed $zoneId, array $payments, array $moduleIds, array $codLimits): ?Zone
    {
        return DB::transaction(function () use ($zoneId, $payments, $moduleIds, $codLimits) {
            $zone = Zone::find($zoneId);

            if (! $zone) {
                return null;
            }

            $zone->forceFill($payments)->save();

            // Which modules this sync() is about to detach. Read BEFORE it runs, because after
            // it the pivot no longer says they were ever here -- and a store whose module has
            // left the zone has no route left to serve its storefront through, so those sites
            // come down with the connection. The drawer warns first; this is the part that acts.
            $detached = array_values(array_diff(
                $zone->modules()->pluck('modules.id')->map(fn ($id) => (int) $id)->all(),
                array_map('intval', $moduleIds),
            ));

            $zone->modules()->sync(array_reduce(
                $moduleIds,
                function (array $carry, int $moduleId) use ($codLimits) {
                    $carry[$moduleId] = ['maximum_cod_order_amount' => $codLimits[$moduleId] ?? 0];

                    return $carry;
                },
                [],
            ));

            if ($detached) {
                app(StorefrontVisibilityService::class)->hideStorefronts($zone->id, $detached);
            }

            return $zone->load('modules');
        });
    }

    /**
     * Modules with at least one currently active store in this zone.
     *
     * Connect Module disconnects a module by simply not resubmitting it in the picker — nothing
     * stops the save, so an admin can silently orphan every store using that module in this zone.
     * The drawer reads this to warn before that save goes through, per TC_98.
     *
     * @return array<int, int> module ids
     */
    public function activeStoreModuleIds(mixed $zoneId): array
    {
        if (empty($zoneId)) {
            return [];
        }

        return DB::table('stores')
            ->where('zone_id', $zoneId)
            ->where('status', 1)
            ->distinct()
            ->pluck('module_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Tell every vendor with an active store in this zone that it just went inactive.
     *
     * Deactivating a zone silently stops new orders resolving into it (Zone::resolve() refuses
     * an inactive zone's coordinates, §TC_17) — until this, nothing told the affected vendors,
     * so a store's orders could stop with no signal at all (TC_18). One batched query for every
     * vendor in the zone (rule 11), a push per vendor with a token — the same shape
     * BundleService::notifyStoreOfNewBundle() already uses for a single-store notice.
     */
    public function notifyVendorsOfDeactivation(mixed $zoneId, string $zoneName): void
    {
        if (empty($zoneId)) {
            return;
        }

        $vendors = \App\Models\Vendor::query()
            ->whereHas('stores', fn ($q) => $q->where('zone_id', $zoneId)->where('status', 1))
            ->whereNotNull('firebase_token')
            ->where('firebase_token', '!=', '@')
            ->get(['id', 'firebase_token']);

        foreach ($vendors as $vendor) {
            try {
                \App\Support\Notification\SendNotification::pushToVendorPanel(
                    $vendor->id,
                    $vendor->firebase_token,
                    \App\Support\Notification\NotificationMessages::zoneDeactivated($zoneName),
                );
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::channel(config('notification.log_channel', 'stack'))
                    ->warning('zone.deactivation_notify_failed', ['zone_id' => $zoneId, 'vendor_id' => $vendor->id, 'error' => $exception->getMessage()]);
            }
        }
    }

    /**
     * Rows of {zone_id, module_id} folded into module ids keyed by zone.
     *
     * @return array<int, array<int, int>>
     */
    private function moduleIdsByZone(iterable $rows): array
    {
        $byZone = [];

        foreach ($rows as $row) {
            $byZone[(int) $row->zone_id][(int) $row->module_id] = (int) $row->module_id;
        }

        return array_map('array_values', $byZone);
    }

    /**
     * The three module-id lists every availability question is answered from, for a set of zones.
     *
     * THREE queries for the whole set, never one per zone (rule 11). Readiness is a
     * (zone, module) question, so these collect module ids per zone rather than a yes/no per zone.
     *
     * @return array{0: array<int, array<int, int>>, 1: array<int, array<int, int>>, 2: array<int, array<int, int>>}
     *         [connected, priced, timed], each keyed by zone id
     */
    private function setupModuleIdsByZone(array $zoneIds): array
    {
        $priced = $this->moduleIdsByZone(
            DB::table('delivery_rule_module')
                ->join('delivery_rules', 'delivery_rules.id', '=', 'delivery_rule_module.delivery_rule_id')
                ->whereIn('delivery_rules.zone_id', $zoneIds)
                ->where('delivery_rules.status', 1)
                ->get(['delivery_rules.zone_id', 'delivery_rule_module.module_id'])
        );

        $timed = $this->moduleIdsByZone(
            DB::table('eta_configuration_module')
                ->join('eta_configurations', 'eta_configurations.id', '=', 'eta_configuration_module.eta_configuration_id')
                ->whereIn('eta_configurations.zone_id', $zoneIds)
                ->where('eta_configurations.status', 1)
                ->get(['eta_configurations.zone_id', 'eta_configuration_module.module_id'])
        );

        $connected = $this->moduleIdsByZone(
            DB::table('module_zone')->whereIn('zone_id', $zoneIds)->get(['zone_id', 'module_id'])
        );

        return [$connected, $priced, $timed];
    }

    /**
     * Which modules each of these zones can actually serve — `Zone::completeBetween()`, batched.
     *
     * The same answer `Zone::effectiveModuleIds()` gives one zone, for many, in three queries
     * instead of four per zone. `findByCoordinates()` asks it of every zone containing the
     * customer's point, which is the hottest path in the app; asking per zone there turned a
     * one-`exists()`-per-zone filter into an N+1 the moment availability grew a second half.
     *
     * @return array<int, array<int, int>> zone id => available module ids
     */
    public function availableModuleIdsByZone(array $zoneIds): array
    {
        if ($zoneIds === []) {
            return [];
        }

        [$connected, $priced, $timed] = $this->setupModuleIdsByZone($zoneIds);

        $out = [];

        foreach ($zoneIds as $zoneId) {
            $out[(int) $zoneId] = Zone::completeBetween(
                $connected[$zoneId] ?? [],
                $priced[$zoneId] ?? [],
                $timed[$zoneId] ?? [],
            );
        }

        return $out;
    }

    public function readinessFor(iterable $zones): array
    {
        $byId = [];

        foreach ($zones as $zone) {
            $byId[(int) $zone->id] = (int) $zone->status === 1;
        }

        $zoneIds = array_keys($byId);

        if ($zoneIds === []) {
            return [];
        }

        [$connected, $priced, $timed] = $this->setupModuleIdsByZone($zoneIds);

        // Names for the modules a zone cannot serve, so the list and the confirm dialog can say
        // "Pharmacy, Parcel" rather than "2, 5". One memoised query for the whole page — this is
        // the same list the module pickers read, so it is usually already in memory.
        //
        // Keyed by id and holding ACTIVE modules only: a globally disabled module is not served
        // anywhere, and naming it among the ones this zone will leave dark reads as a zone
        // problem the admin cannot fix here.
        $moduleNames = app(ModuleService::class)->getSelectOptions()->pluck('module_name', 'id');

        // How many storefronts each zone would take down if it were switched off. One grouped
        // query for the page rather than one per row -- the deactivate confirm needs it on every
        // row, and a storefront is the one thing deactivating a zone silently leaves answering.
        $liveStorefronts = app(StorefrontVisibilityService::class)->liveStorefrontCountsByZone($zoneIds);

        $out = [];

        foreach ($zoneIds as $zoneId) {
            // The model owns the rule; this only feeds it the three lists.
            $gaps = Zone::gapsBetween($connected[$zoneId] ?? [], $priced[$zoneId] ?? [], $timed[$zoneId] ?? []);
            $missing = Zone::missingByModule($connected[$zoneId] ?? [], $priced[$zoneId] ?? [], $timed[$zoneId] ?? []);
            $unavailable = Zone::incompleteBetween($connected[$zoneId] ?? [], $priced[$zoneId] ?? [], $timed[$zoneId] ?? []);

            // Named, and only the ones a customer could otherwise have been offered.
            $unavailableNames = array_values(array_filter(array_map(
                fn ($moduleId) => $moduleNames[$moduleId] ?? null,
                $unavailable
            )));

            // The confirm is about switching ON. An active zone's toggle switches it OFF, which
            // takes nothing dark that is not dark already, so it stays a plain toggle.
            $partial = $gaps === [] && $unavailableNames !== [] && ! $byId[$zoneId];

            // The message comes from the model so the list and the two guards that actually
            // refuse a switch-on cannot drift into saying different things.
            $out[$zoneId] = [
                'ready' => $gaps === [],
                'gaps' => $gaps,
                // What each surface says, resolved once: the toggle guard's message, the row
                // mark's notice, and the dialog's lead line. Each names only what is missing.
                'message' => translate((new Zone)->readinessMessageKey($gaps)),
                'notice' => translate((new Zone)->readinessNoticeKey($gaps)),
                'prompt' => translate((new Zone)->readinessPromptKey($gaps)),
                // The partial-availability wordings, resolved from the same four methods so the
                // confirm dialog cannot say something the Toastr behind it does not.
                'partialNotice' => translate(
                    (new Zone)->readinessNoticeKey($gaps, partial: true),
                    ['modules' => implode(', ', $unavailableNames)]
                ),
                'partialPrompt' => translate((new Zone)->readinessPromptKey($gaps, partial: true)),
                'partialTitleKey' => (new Zone)->readinessTitleKey($gaps, partial: true),
                // The KEY, not the finished sentence: resolving it needs the zone's name, and
                // reading `name` here would run the translation accessor and cost this method a
                // query per page it does not otherwise need. The caller already has the name.
                'titleKey' => (new Zone)->readinessTitleKey($gaps),
                // The row needs these to decide which shortcuts to offer. Read from the
                // per-module answer rather than from `gaps`, because after S19 gaps is empty
                // whenever ONE module is complete — and the dialog on a partly-configured zone
                // is exactly where the links to the two setups are most wanted.
                'hasRule' => $missing['delivery_rule'] === [],
                'hasEta' => $missing['eta'] === [],
                // WHICH modules are short of what, so a screen can name them rather than leave the
                // admin comparing two lists to find the one holding the zone back.
                'missingModuleIds' => $missing,
                // S19 — the third state. `ready` still means "the toggle may proceed"; `complete`
                // means nothing is left dark behind it. A zone can be the first without being the
                // second, and that is exactly when the confirm dialog is shown.
                'complete' => $gaps === [] && $unavailable === [],
                'unavailableModuleIds' => $unavailable,
                'unavailableModuleNames' => $unavailableNames,
                // Storefronts this zone would take down if switched off. The deactivate confirm
                // names the number; nothing else about deactivating mentions them.
                'liveStorefronts' => $liveStorefronts[$zoneId] ?? 0,
                'requiresConfirmation' => $partial,
                // An already-active zone keeps its toggle whatever its gaps: it must always be
                // possible to switch a zone OFF, and only switching ON is what Z3 guards.
                'toggleEnabled' => $gaps === [] || $byId[$zoneId],
            ];
        }

        return $out;
    }

    public function findByCoordinates(mixed $latitude, mixed $longitude): array
    {
        $containing = Zone::whereContains('coordinates', new Point($latitude, $longitude, POINT_SRID))
            ->with([
                'modules' => fn ($query) => $query->select(
                    'modules.id', 'modules.module_name', 'modules.module_type', 'modules.stores_count', 'modules.theme_id'
                ),
            ])
            ->select([
                'id', 'status', 'cash_on_delivery', 'digital_payment', 'offline_payment',
                'increased_delivery_fee_status', 'increase_delivery_charge_message',
            ])
            ->selectRaw('ABS(ST_Area(coordinates)) as area')
            ->orderBy('area')
            ->latest()
            ->get();

        // PORT DOC A15 — a zone only serves once it is switched on AND can serve at least one
        // module. An unserviceable zone reads to the customer exactly as a switched-off one.
        //
        // S19: the test is now the COMPLETE one, delivery rule AND ETA configuration per module,
        // for the modules whose type can hold them. Filtering on a delivery rule alone offered a
        // module that could be priced but not timed. A zone whose every module falls short is
        // dropped here rather than returned with an empty module list a client would render as
        // "nothing here"; the customer gets the existing 403.
        // Batched, not per zone: `Zone::effectiveModuleIds()` costs four queries a zone, and this
        // runs for every zone containing the point on every location the customer sets.
        $availableByZone = $this->availableModuleIdsByZone(
            $containing->where('status', 1)->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $active = $containing
            ->where('status', 1)
            ->filter(fn (Zone $zone) => ($availableByZone[(int) $zone->id] ?? []) !== [])
            ->values();

        // Reads the same (zone, module) offers the Additional Charge screen writes — the
        // `moduleDeliveryOptions` relation instead answers from the frozen predecessor table,
        // which drifts from whatever the admin actually configures the moment they save.
        $deliveryOptions = app(ModuleZoneDeliveryOptionService::class);

        // Read for every (zone, module) at once rather than per cell: a customer standing in
        // overlapping zones can have a dozen of them, and activeSetup() is one query each.
        $freeDeliverySetups = app(FreeDeliveryService::class)->activeSetupsForZones(
            $active->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        foreach ($active as $zone) {
            // A15 at MODULE granularity — mart prices per (zone, module), so a zone can be live
            // for Food and dark for Grocery. A module this zone cannot fully serve is dropped
            // from the list the customer is offered rather than being offered and then failing
            // to price or to quote a time.
            $servable = $availableByZone[(int) $zone->id] ?? [];
            $zone->setRelation(
                'modules',
                $zone->modules->filter(fn ($module) => in_array($module->id, $servable, true))->values(),
            );

            foreach ($zone->modules as $module) {
                $module->setAttribute('delivery_options', $deliveryOptions->optionsFor($module->id, $zone->id)->values()->all());
                // Per (zone, module), because that is the granularity the setup is written at:
                // the same zone can give Grocery free delivery outright and Food only over an
                // amount. Null where no setup covers the pair; the resource renders the absence.
                $module->setAttribute(
                    'free_delivery_setup',
                    $freeDeliverySetups[(int) $zone->id.':'.(int) $module->id] ?? null
                );
            }
        }

        // A zone whose every module is unpriced can serve nobody, so it is dropped rather than
        // returned with an empty module list a client would render as "nothing here".
        $active = $active->filter(fn (Zone $zone) => $zone->modules->isNotEmpty())->values();

        return ['containing' => $containing, 'active' => $active];
    }

    /**
     * Which payment methods may be used in this zone.
     *
     * A method is available only where the platform allows it AND the zone allows it: the zone
     * columns NARROW the third-party settings, they never widen them. Switching COD off globally
     * turns it off everywhere regardless of what any zone row says, which is why the global
     * setting is the first term of every pair.
     *
     * Kept here, on the service that owns Zone, because four callers need the same answer and had
     * been giving three different ones -- the panel's Connect Module drawer, the customer
     * `/config` payload, the order-placement guard, and PaymentFailedResource, which was the only
     * one combining the two terms correctly.
     *
     * `wallet` is absent on purpose. There is no zone column for it: a wallet balance is the
     * customer's money and does not belong to an area.
     *
     * @param  Zone|null  $zone  null means the zone could not be resolved, and the global settings
     *                          answer alone -- a client that has not chosen a location yet must
     *                          still be told what the platform supports.
     * @return array{cash_on_delivery: bool, digital_payment: bool, offline_payment: bool}
     */
    public function allowedPaymentMethods(?Zone $zone): array
    {
        $settings = app(BusinessSettingService::class);

        $platform = [
            'cash_on_delivery' => (bool) ($settings->value('cash_on_delivery')['status'] ?? 0),
            'digital_payment' => (bool) ($settings->value('digital_payment')['status'] ?? 0),
            'offline_payment' => (bool) $settings->value('offline_payment_status'),
        ];

        if (! $zone) {
            return $platform;
        }

        return [
            'cash_on_delivery' => $platform['cash_on_delivery'] && (bool) $zone->cash_on_delivery,
            'digital_payment' => $platform['digital_payment'] && (bool) $zone->digital_payment,
            'offline_payment' => $platform['offline_payment'] && (bool) $zone->offline_payment,
        ];
    }

    /**
     * The same answer for a `zoneId` header, which may carry several ids for overlapping zones.
     *
     * §10.3 -- the ids are walked in the order the client sent them and the first that resolves
     * wins, matching FreeDeliveryService::activeSetup(). No id resolving means no zone, and the
     * global settings answer alone.
     *
     * @return array{cash_on_delivery: bool, digital_payment: bool, offline_payment: bool}
     */
    public function allowedPaymentMethodsForZoneIds(mixed $zoneIds): array
    {
        foreach (array_filter((array) $zoneIds) as $zoneId) {
            // `withoutTranslation` for the reason EtaConfigurationService::activeConfig() gives:
            // this reads three booleans and never the name, and the trait's eager load would put
            // a second query on the path every /config call takes (rule 11).
            $zone = Zone::withoutTranslation()
                ->withoutGlobalScope(ZoneScope::class)
                ->select('id', 'cash_on_delivery', 'digital_payment', 'offline_payment')
                ->find($zoneId);

            if ($zone) {
                return $this->allowedPaymentMethods($zone);
            }
        }

        return $this->allowedPaymentMethods(null);
    }

    public function findContaining(mixed $zoneId, mixed $latitude, mixed $longitude): ?Zone
    {
        return $this->containingPointQuery($zoneId, $latitude, $longitude)->first();
    }

    public function findSmallestContainingInZones(?array $zoneIds, mixed $latitude, mixed $longitude, ?string $moduleType = null): ?Zone
    {
        return Zone::active()
            ->when($zoneIds !== null, fn ($query) => $query->whereIn('id', $zoneIds))
            ->whereContains('coordinates', new Point($latitude, $longitude, POINT_SRID))
            ->selectRaw('zones.*, ABS(ST_Area(coordinates)) as area')
            ->orderBy('area', 'asc')
            ->when($moduleType, fn ($query) => $query
                ->whereHas('modules', fn ($sub) => $sub->where('module_type', $moduleType)))
            ->first();
    }

    private function polygonFromInput(array $input): Polygon
    {
        $value = ($input['coordinates'] ?? null);
        $polygon = [];

        foreach (explode('),(', trim($value, '()')) as $index => $single_array) {
            if ($index == 0) {
                $lastCord = explode(',', $single_array);
            }
            $coords = explode(',', $single_array);

            $polygon[] = new Point($coords[0], $coords[1]);
        }
        $polygon[] = new Point($lastCord[0], $lastCord[1]);

        return new Polygon([new LineString($polygon)]);
    }

    private function containingPointQuery(mixed $zoneId, mixed $latitude, mixed $longitude): Builder
    {
        return Zone::where('id', $zoneId)
            ->whereContains('coordinates', new Point($latitude, $longitude, POINT_SRID));
    }

    private function mapIdsByCoordinates(array $points): array
    {
        $selects = [];
        $bindings = [];
        $pointExpression = $this->geomFromTextExpression();

        foreach (array_values($points) as $index => $point) {
            $latitude = $point['latitude'] ?? null;
            $longitude = $point['longitude'] ?? null;

            // A non-numeric or out-of-range coordinate makes ST_GeomFromText throw and would fail
            // the whole batch; such a point simply gets no zones.
            if (! is_numeric($latitude) || ! is_numeric($longitude)
                || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
                continue;
            }

            $selects[] = "SELECT ? AS idx, {$pointExpression} AS pt";
            $bindings[] = $index;
            $bindings[] = sprintf('POINT(%.10F %.10F)', $longitude, $latitude);
            $bindings[] = POINT_SRID;
        }

        if ($selects === []) {
            return [];
        }

        $rows = DB::select(
            'SELECT p.idx AS idx, z.id AS zone_id FROM zones z
             JOIN ('.implode(' UNION ALL ', $selects).') p
             ON ST_Contains(z.coordinates, p.pt)
             ORDER BY z.id DESC',
            $bindings
        );

        $byIndex = [];

        foreach ($rows as $row) {
            $byIndex[(int) $row->idx][] = (int) $row->zone_id;
        }

        return $byIndex;
    }

    private function geomFromTextExpression(): string
    {
        return AxisOrder::supported(DB::connection())
            ? "ST_GeomFromText(?, ?, 'axis-order=long-lat')"
            : 'ST_GeomFromText(?, ?)';
    }

    private function deliveryOptionsMatrix(Zone $zone): array
    {
        $matrix = [];
        $deliveryOptions = app(ModuleZoneDeliveryOptionService::class);

        // Reads the same (zone, module) offers the Additional Charge screen writes — see the
        // note in findByCoordinates(); `moduleDeliveryOptions` answers from the frozen
        // predecessor table instead.
        foreach ($zone->modules as $module) {
            foreach ($deliveryOptions->optionsFor($module->id, $zone->id) as $option) {
                $matrix[(int) $module->id][(string) $option->delivery_type] = [
                    'id' => (int) $option->id,
                    'delivery_type' => (string) $option->delivery_type,
                    'extra_charge' => (float) ($option->extra_charge ?? 0),
                    'reduce_charge' => (float) ($option->reduce_charge ?? 0),
                    'add_delivery_time' => (int) ($option->getRawOriginal('add_delivery_time') ?? 0),
                    'reduce_delivery_time' => (int) ($option->getRawOriginal('reduce_delivery_time') ?? 0),
                ];
            }
        }

        return $matrix;
    }
}
