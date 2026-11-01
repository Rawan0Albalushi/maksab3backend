<?php

namespace App\Services\Zone;

use App\Models\EtaConfiguration;
use App\Services\BaseService;
use App\Traits\Zone\ResolvesSoloModuleCoverTrait;
use App\Services\System\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\EtaConfiguration — how a (zone, module) arrives at a delivery estimate.
 *
 * CRUD and resolution only. The arithmetic that turns a configuration plus an order into a
 * range lives in EtaService, because it needs the order — the store's delivery time, the map's
 * travel duration, the order's status and schedule. Keeping the two apart is what lets the admin
 * screen preview a configuration without inventing an order to preview it against.
 */
class EtaConfigurationService extends BaseService
{
    use ResolvesSoloModuleCoverTrait;

    /**
     * The modules this configuration is the only active estimate for in its zone.
     *
     * Same question `modulesLeftWithoutEta()` answers for the status toggle, keyed by id for the
     * edit form — which asks it about a module the admin is dropping rather than about a row
     * they are switching off.
     *
     * @return array<int, string> module id => module name
     */
    public function soloModules(EtaConfiguration $configuration): array
    {
        return $this->soloModuleCover($configuration->loadMissing('modules'), EtaConfiguration::class);
    }

    /**
     * Resolved configurations, keyed "zoneId:moduleId".
     *
     * Static rather than an instance property so a fresh resolution cannot lose it. An order list
     * asks the same question once per row, and without this a page of twenty-five orders issues
     * twenty-five identical queries (rule 11). Cleared by every write below, so a save-then-render
     * request cannot read a stale configuration.
     */
    private static array $activeMemo = [];

    public function forgetActive(): void
    {
        self::$activeMemo = [];
    }

    /**
     * @throws \App\Exceptions\DuplicateEtaConfigurationException when E1 would be broken
     */
    public function create(array $data): EtaConfiguration
    {
        return DB::transaction(function () use ($data) {
            // E1 is checked AGAIN here, inside the transaction and behind a lock.
            //
            // The form request checks it too, but that is a read followed by a write with nothing
            // between them: two admins saving the same (zone, module) at the same moment both read
            // "no conflict" and both insert. It is not theoretical -- two concurrent POSTs for one
            // pair produced two configurations. The lock serialises them, so the second waits, then
            // sees the first and is refused.
            $moduleIds = $this->moduleIds($data);
            $clashes = $this->conflictingModuleNames($data['zone_id'] ?? null, $moduleIds, null, lock: true);

            if ($clashes !== []) {
                throw new \App\Exceptions\DuplicateEtaConfigurationException(
                    translate('messages.An ETA configuration already exists in this zone for').' '.implode(', ', $clashes)
                );
            }

            $setup = EtaConfiguration::create($this->attributes($data) + ['status' => $data['status'] ?? true]);
            $setup->modules()->sync($moduleIds);
            $this->forgetActive();

            return $setup->load('modules');
        });
    }

    public function update(mixed $id, array $data): ?EtaConfiguration
    {
        return DB::transaction(function () use ($id, $data) {
            $setup = EtaConfiguration::find($id);

            if (! $setup) {
                return null;
            }

            $setup->update($this->attributes($data));
            $setup->modules()->sync($this->moduleIds($data));
            $this->forgetActive();

            return $setup->load('modules');
        });
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $setup = EtaConfiguration::find($id);

            if (! $setup) {
                return false;
            }

            $setup->modules()->detach();
            $setup->translations()->delete();
            $this->forgetActive();

            return (bool) $setup->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $setup = EtaConfiguration::find($id);
        $this->forgetActive();

        return $setup ? $setup->update(['status' => (bool) $status]) : false;
    }

    /**
     * The modules this configuration is the ONLY active estimate for.
     *
     * Empty means it may be switched off; anything in it names a module that would be left with no
     * estimate at all. §11.2 has the storefront show nothing rather than invent a delivery time,
     * so switching the last one off does not fail loudly — it quietly removes the estimate from
     * every order in that zone. Hence a refusal rather than a cascade.
     *
     * @return array<int, string> module names, for the message the dialog shows
     */
    public function modulesLeftWithoutEta(mixed $id): array
    {
        $setup = EtaConfiguration::with('modules')->find($id);

        if (! $setup || ! $setup->status) {
            return [];
        }

        return $setup->modules
            ->reject(fn ($module) => EtaConfiguration::query()
                ->where('zone_id', $setup->zone_id)
                ->where('status', 1)
                ->whereKeyNot($setup->getKey())
                ->whereHas('modules', fn ($q) => $q->where('modules.id', $module->id))
                ->exists())
            ->pluck('module_name')
            ->values()
            ->all();
    }

    /**
     * The same answer for a whole page, keyed by configuration id.
     *
     * modulesLeftWithoutEta() asks per row and per module, which on a page of twenty-five is
     * dozens of queries (rule 11). This reads every active configuration for the zones on the page
     * once and decides in PHP.
     *
     * @param  iterable<EtaConfiguration>  $setups  rows with `modules` already loaded
     * @return array<int, array<int, string>> configuration id => module names it alone covers
     */
    public function lockedModulesFor(iterable $setups): array
    {
        $setups = $this->rowsOf($setups);

        if ($setups->isEmpty()) {
            return [];
        }

        // zone id => [module id => count of ACTIVE configurations covering it]
        $activeCover = [];

        foreach (EtaConfiguration::query()
            ->whereIn('zone_id', $setups->pluck('zone_id')->unique()->filter()->all())
            ->where('status', 1)
            ->with('modules:id')
            ->get() as $active) {
            foreach ($active->modules as $module) {
                $activeCover[$active->zone_id][$module->id] = ($activeCover[$active->zone_id][$module->id] ?? 0) + 1;
            }
        }

        return $setups->mapWithKeys(fn (EtaConfiguration $setup) => [
            $setup->id => $setup->status
                ? $setup->modules
                    ->filter(fn ($module) => ($activeCover[$setup->zone_id][$module->id] ?? 0) <= 1)
                    ->pluck('module_name')
                    ->values()
                    ->all()
                : [],
        ])->all();
    }

    public function find(mixed $id, array $with = ['zone', 'modules']): ?EtaConfiguration
    {
        return EtaConfiguration::with($with)->find($id);
    }

    /**
     * For the edit form, which prefills one input per language and so needs every locale — not
     * only the one the panel is being viewed in.
     */
    public function findForEdit(mixed $id): ?EtaConfiguration
    {
        return EtaConfiguration::withAllTranslations()->with(['zone', 'modules'])->find($id);
    }

    public function getList(
        array $filters = [],
        array $with = ['zone', 'modules'],
        array $withCount = [],
        array $paginate = ['per_page' => 25, 'page' => 1]
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $with, $withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getListData(array $filters = [], array $with = ['zone', 'modules'], array $withCount = []): Collection
    {
        return $this->buildQuery($filters, $with, $withCount)->get();
    }

    public function statusStatistics(array $filters = []): array
    {
        $base = fn () => $this->buildQuery($filters, [], []);

        return [
            'total' => $base()->count(),
            'active' => $base()->where('status', 1)->count(),
            'inactive' => $base()->where('status', 0)->count(),
        ];
    }

    /**
     * The active configuration for this (zone, module), or null.
     *
     * §11.2 — **estimate nothing** where an admin has not set one up. Returning null is the whole
     * contract: a caller must show no estimate rather than invent a default, because a made-up
     * delivery time is a promise the platform never agreed to.
     *
     * §10.3's overlapping-zone rule applies here too: the `zoneId` header may carry several ids,
     * and the first zone with a configuration wins, so they are walked in the order given.
     */
    public function activeConfig(mixed $zoneIds, mixed $moduleId): ?EtaConfiguration
    {
        foreach (array_filter((array) $zoneIds) as $zoneId) {
            $key = $zoneId.':'.$moduleId;

            if (! array_key_exists($key, self::$activeMemo)) {
                // `withoutTranslation` because this runs on the estimate path and reads numbers
                // only — the name is an admin-facing label, and loading it here would add a
                // second query to every quote for nothing (rule 11).
                self::$activeMemo[$key] = EtaConfiguration::withoutTranslation()
                    ->active()
                    ->forZoneModule($zoneId, $moduleId)
                    ->first();
            }

            if (self::$activeMemo[$key]) {
                return self::$activeMemo[$key];
            }
        }

        return null;
    }

    /**
     * The floor Express/Slightly Delay are measured against: how much time the (zone, module)
     * has promised at minimum. Sourced from the live ETA Configuration rather than the old
     * `module_zone.minimum_delivery_time` column, which has no admin screen left to write it —
     * this is the one place an admin can still set that number. Zero when there is no active
     * configuration, meaning the feature imposes no floor rather than an invented one (§11.2).
     */
    /**
     * A configuration can cover Parcel alongside other modules in the same row (E1 keys a config
     * to zone+module, not zone+config), and Parcel's own minimum lives in a separate column —
     * `parcel_minimum_delivery_time`, never `minimum_delivery_time` — the same split
     * EtaService::effectiveTimings() reads from at order time. Reading the general column for a
     * Parcel module here compared its Express "Reduce Delivery Time" against the wrong number
     * (0, or whatever an unrelated module's minimum happened to be), rejecting perfectly valid
     * express setups with "cannot be longer than the minimum delivery time for module: Parcel."
     */
    public function minimumDeliveryTimeFloor(mixed $zoneId, mixed $moduleId): int
    {
        $config = $this->activeConfig($zoneId, $moduleId);

        if (! $config) {
            return 0;
        }

        $isParcel = in_array((int) $moduleId, app(ModuleService::class)->moduleIdsOfType('parcel'), true);

        return (int) ($isParcel ? $config->parcel_minimum_delivery_time : $config->minimum_delivery_time);
    }

    /**
     * DESIGN RULE E1 — "only one ETA configuration per Zone & Module combination".
     *
     * Because a configuration claims a SET of modules the test is an OVERLAP, exactly as D1 and
     * F1 are, and the message names the clashing modules so the admin can deselect them rather
     * than guess.
     */
    /**
     * @param  bool  $lock  hold the zone's rows for the rest of the transaction, so a concurrent
     *                      save of the same pair waits here instead of racing past the check
     */
    public function conflictingModuleNames(mixed $zoneId, array $moduleIds, mixed $exceptId = null, bool $lock = false): array
    {
        if (empty($zoneId) || $moduleIds === []) {
            return [];
        }

        $taken = EtaConfiguration::query()
            ->where('zone_id', $zoneId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->with('modules:id,module_name')
            ->get()
            ->flatMap(fn ($setup) => $setup->modules)
            ->unique('id');

        return $taken->whereIn('id', $moduleIds)->pluck('module_name')->values()->all();
    }

    /**
     * The modules a configuration may still claim in this zone: connected to the zone (D7's rule,
     * applied here too) minus those another configuration already covers.
     *
     * @return array{modules: Collection, state: 'available'|'all_taken'|'none_connected'}
     */
    public function modulePickerForZone(mixed $zoneId, mixed $exceptId = null): array
    {
        // Rental, ride-share and service are filtered out before anything else: they have no
        // delivery window to estimate, so offering them here would let an admin configure a
        // setting nothing reads. Capability, not a name list — see `config('module.<type>.eta')`.
        $etaCapable = app(ModuleService::class)->etaCapableModuleIds();

        $all = app(ModuleService::class)->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, $etaCapable, true))
            ->values();

        if (empty($zoneId)) {
            return ['modules' => $all, 'state' => 'available'];
        }

        $connected = app(ModuleZoneService::class)->connectedModuleIds($zoneId);
        $taken = $this->takenModuleIds($zoneId, $exceptId);

        $available = $all
            ->filter(fn ($module) => in_array($module->id, $connected, true))
            ->reject(fn ($module) => in_array($module->id, $taken, true))
            ->values();

        return [
            'modules' => $available,
            'state' => match (true) {
                $available->isNotEmpty() => 'available',
                $connected === [] => 'none_connected',
                default => 'all_taken',
            },
        ];
    }

    /**
     * The picked modules that cannot carry an ETA at all, by name.
     *
     * Rental, ride-share and service have no delivery window to estimate. The picker never offers
     * them; this is what refuses a crafted POST that names one anyway.
     *
     * @return array<int, string>
     */
    public function etaIncapableModuleNames(array $moduleIds): array
    {
        if ($moduleIds === []) {
            return [];
        }

        $capable = app(ModuleService::class)->etaCapableModuleIds();

        return app(ModuleService::class)->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, array_map('intval', $moduleIds), true))
            ->reject(fn ($module) => in_array((int) $module->id, $capable, true))
            ->pluck('module_name')
            ->values()
            ->all();
    }

    /** The picked modules the zone is not connected to, by name — D7's server-side guard. */
    public function unconnectedModuleNames(mixed $zoneId, array $moduleIds): array
    {
        if (empty($zoneId) || $moduleIds === []) {
            return [];
        }

        $connected = app(ModuleZoneService::class)->connectedModuleIds($zoneId);

        return app(ModuleService::class)->getSelectOptions()
            ->whereIn('id', $moduleIds)
            ->reject(fn ($module) => in_array($module->id, $connected, true))
            ->pluck('module_name')
            ->all();
    }

    private function takenModuleIds(mixed $zoneId, mixed $exceptId): array
    {
        return EtaConfiguration::query()
            ->where('zone_id', $zoneId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->with('modules:id')
            ->get()
            ->flatMap(fn ($setup) => $setup->modules->pluck('id'))
            ->unique()
            ->all();
    }

    private function attributes(array $data): array
    {
        $method = $data['calculation_method'] ?? EtaConfiguration::METHOD_DISTANCE;
        $parcelMinimum = $this->minutes($data['parcel_minimum_delivery_time'] ?? null);

        return [
            'name' => $data['name'] ?? null,
            'zone_id' => $data['zone_id'],
            'calculation_method' => $method,
            'minimum_delivery_time' => $this->minutes($data['minimum_delivery_time'] ?? null),
            'preparation_buffer' => $this->minutes($data['preparation_buffer'] ?? null),
            'transit_buffer' => $this->minutes($data['transit_buffer'] ?? null),
            // The gap belongs to the distance method alone. Nulling it on the other stops a stale
            // value widening a range the moment an admin switches back — the same trap the
            // delivery rule closes by nulling the columns of the method not chosen.
            // Defaulted, not left null: a distance-based configuration with no gap renders a
            // single time instead of a range, because maximumEtaMinutes() adds (int) null.
            'time_gap' => $method === EtaConfiguration::METHOD_DISTANCE
                ? ($this->minutes($data['time_gap'] ?? null) ?? EtaConfiguration::DEFAULT_TIME_GAP)
                : null,
            // Parcel's own three. Null when Parcel isn't among the picked modules — the form
            // hides the section and submits nothing for it, so there is nothing to default here;
            // a defaulted gap only makes sense once minimum/transit are actually set.
            'parcel_minimum_delivery_time' => $parcelMinimum,
            'parcel_transit_buffer' => $this->minutes($data['parcel_transit_buffer'] ?? null),
            'parcel_time_gap' => $parcelMinimum !== null
                ? ($this->minutes($data['parcel_time_gap'] ?? null) ?? EtaConfiguration::DEFAULT_TIME_GAP)
                : null,
        ];
    }

    /** Minutes, or null for a field the admin left empty — zero and empty are not the same. */
    private function minutes(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : max(0, (int) $value);
    }

    private function moduleIds(array $data): array
    {
        return array_values(array_filter(array_map('intval', (array) ($data['module_ids'] ?? []))));
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return EtaConfiguration::query()
            ->with($with)
            ->withCount($withCount)
            ->when(! empty($filters['zone_id']), fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->when(! empty($filters['module_id']), fn ($q) => $q->whereHas('modules', fn ($m) => $m->where('modules.id', $filters['module_id'])))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), fn ($q) => $q->where(function ($sub) use ($filters) {
                $sub->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('zone', fn ($z) => $z->where('name', 'like', '%'.$filters['search'].'%'));
            }))
            ->orderBy('id', 'desc');
    }
}
