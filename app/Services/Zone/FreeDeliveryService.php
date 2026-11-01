<?php

namespace App\Services\Zone;

use App\Models\FreeDelivery;
use App\Services\BaseService;
use App\Traits\Zone\ResolvesSoloModuleCoverTrait;
use App\Services\System\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\FreeDelivery — the per-(zone, module) free delivery setups.
 *
 * This is step 7a of §12: it runs AFTER the engine has produced a charge, and overrides it.
 * It never participates in computing the base — a free delivery is an override, not a rate.
 */
class FreeDeliveryService extends BaseService
{
    use ResolvesSoloModuleCoverTrait;

    /**
     * The modules this setup is the only active free delivery for in its zone.
     *
     * Free delivery is an ADD-ON, not a gate: a module losing it is still available in the zone,
     * it simply pays for delivery again. The warning says that and nothing stronger.
     *
     * @return array<int, string> module id => module name
     */
    public function soloModules(FreeDelivery $setup): array
    {
        return $this->soloModuleCover($setup->loadMissing('modules'), FreeDelivery::class);
    }

    public function create(array $data): FreeDelivery
    {
        return DB::transaction(function () use ($data) {
            $setup = FreeDelivery::create($this->attributes($data) + ['status' => $data['status'] ?? true]);
            $setup->modules()->sync($this->moduleIds($data));

            return $setup->load('modules');
        });
    }

    public function update(mixed $id, array $data): ?FreeDelivery
    {
        return DB::transaction(function () use ($id, $data) {
            $setup = FreeDelivery::find($id);

            if (! $setup) {
                return null;
            }

            $setup->update($this->attributes($data));
            $setup->modules()->sync($this->moduleIds($data));

            return $setup->load('modules');
        });
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $setup = FreeDelivery::find($id);

            if (! $setup) {
                return false;
            }

            $setup->modules()->detach();

            return (bool) $setup->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $setup = FreeDelivery::find($id);

        return $setup ? $setup->update(['status' => (bool) $status]) : false;
    }

    public function find(mixed $id, array $with = ['zone', 'modules']): ?FreeDelivery
    {
        return FreeDelivery::with($with)->find($id);
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
     * The active setup for this (zone, module), or null.
     *
     * §10.3 — the `zoneId` header may carry several ids for overlapping zones, and the FIRST
     * zone with a setup wins. The caller passes them in order; this walks them in that order
     * rather than letting the database decide which overlapping zone answers.
     */
    public function activeSetup(mixed $zoneIds, mixed $moduleId): ?FreeDelivery
    {
        foreach (array_filter((array) $zoneIds) as $zoneId) {
            $setup = FreeDelivery::active()->forZoneModule($zoneId, $moduleId)->first();

            if ($setup) {
                return $setup;
            }
        }

        return null;
    }

    /**
     * The active setup for every (zone, module) pair across these zones, keyed "zoneId:moduleId".
     *
     * activeSetup() answers one pair at a time, which is right for order placement -- it prices
     * one order, in one zone, for one module. A listing that describes every module of every
     * zone a customer's coordinates fall in would call it once per cell, so this reads the same
     * rows in a single query and lets the caller index into the result (rule 11).
     *
     * @param  array<int, int>  $zoneIds
     * @return array<string, FreeDelivery>
     */
    public function activeSetupsForZones(array $zoneIds): array
    {
        if ($zoneIds === []) {
            return [];
        }

        $indexed = [];

        foreach (FreeDelivery::active()->whereIn('zone_id', $zoneIds)->with('modules:id')->get() as $setup) {
            foreach ($setup->modules as $module) {
                // DESIGN RULE F1 allows only one setup per (zone, module); `??=` keeps the first
                // rather than letting a duplicate left over from before that rule silently win.
                $indexed[$setup->zone_id.':'.$module->id] ??= $setup;
            }
        }

        return $indexed;
    }

    /**
     * Does a setup free this order? Step 7a's whole question.
     *
     * The amount is measured POST-discount (§10.3), which the caller is responsible for — this
     * takes the number it is given.
     */
    public function frees(mixed $zoneIds, mixed $moduleId, float $orderAmount): bool
    {
        return (bool) $this->activeSetup($zoneIds, $moduleId)?->frees($orderAmount);
    }

    /**
     * DESIGN RULE F1 — "only one Free Delivery per Zone & Module combination".
     *
     * Because a setup claims a SET of modules the test is an OVERLAP, exactly as D1 is for
     * delivery rules, and the message names the clashing modules so the admin can deselect them
     * rather than guess.
     */
    public function conflictingModuleNames(mixed $zoneId, array $moduleIds, mixed $exceptId = null): array
    {
        if (empty($zoneId) || $moduleIds === []) {
            return [];
        }

        $taken = FreeDelivery::query()
            ->where('zone_id', $zoneId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->with('modules:id,module_name')
            ->get()
            ->flatMap(fn ($setup) => $setup->modules)
            ->unique('id');

        return $taken->whereIn('id', $moduleIds)->pluck('module_name')->values()->all();
    }

    /**
     * The modules a setup may still claim in this zone: connected to the zone (D7's rule,
     * applied here too) minus those another setup already covers.
     *
     * @return array{modules: Collection, state: 'available'|'all_taken'|'none_connected'}
     */
    public function modulePickerForZone(mixed $zoneId, mixed $exceptId = null): array
    {
        // Rental, ride-share and service are filtered out first: neither setting reaches them,
        // so offering them would let an admin configure something that never applies. Capability,
        // not a name list — `config('module.<type>.delivery_charge_setup')`.
        $capable = app(ModuleService::class)->deliveryChargeSetupModuleIds();

        $all = app(ModuleService::class)->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, $capable, true))
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
     * The picked modules that can carry neither setting, by name.
     *
     * The picker never offers them; this is what refuses a crafted POST naming one.
     *
     * @return array<int, string>
     */
    public function incapableModuleNames(array $moduleIds): array
    {
        if ($moduleIds === []) {
            return [];
        }

        $modules = app(ModuleService::class);
        $capable = $modules->deliveryChargeSetupModuleIds();
        $picked = array_map('intval', $moduleIds);

        return $modules->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, $picked, true))
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
        return FreeDelivery::query()
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
        $type = $data['type'] ?? FreeDelivery::TYPE_ALL;

        return [
            'zone_id' => $data['zone_id'],
            'type' => $type,
            // The amount belongs to `specific_criteria` alone; nulling it on the other type
            // stops a stale threshold reappearing if the admin switches back.
            'minimum_order_amount' => $type === FreeDelivery::TYPE_CRITERIA
                ? ($data['minimum_order_amount'] === null || $data['minimum_order_amount'] === ''
                    ? null
                    : (float) $data['minimum_order_amount'])
                : null,
        ];
    }

    private function moduleIds(array $data): array
    {
        return array_values(array_filter(array_map('intval', (array) ($data['module_ids'] ?? []))));
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return FreeDelivery::query()
            ->with($with)
            ->withCount($withCount)
            ->when(! empty($filters['zone_id']), fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->when(! empty($filters['module_id']), fn ($q) => $q->whereHas('modules', fn ($m) => $m->where('modules.id', $filters['module_id'])))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), fn ($q) => $q->whereHas('zone', fn ($z) => $z->where('name', 'like', '%'.$filters['search'].'%')))
            ->orderBy('id', 'desc');
    }
}
