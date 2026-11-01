<?php

namespace App\Services\Zone;

use App\Exceptions\DuplicateAdditionalDeliveryChargeException;
use App\Models\AdditionalDeliveryCharge;
use App\Services\BaseService;
use App\Traits\Zone\ResolvesSoloModuleCoverTrait;
use App\Services\System\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\AdditionalDeliveryCharge — the express and slightly-delayed offers a
 * (zone, module) makes alongside standard delivery.
 *
 * This is CRUD plus resolution. What an offer does to a price lives in the fee engine and the
 * order paths; they reach this through activeSetup() and nothing else, so the pipeline itself
 * never learns where the numbers are stored.
 */
class AdditionalDeliveryChargeService extends BaseService
{
    use ResolvesSoloModuleCoverTrait;

    /**
     * The modules this setup is the only active additional charge for in its zone.
     *
     * An add-on like free delivery — see FreeDeliveryService::soloModules(). Availability is
     * untouched by it.
     *
     * @return array<int, string> module id => module name
     */
    public function soloModules(AdditionalDeliveryCharge $setup): array
    {
        return $this->soloModuleCover($setup->loadMissing('modules'), AdditionalDeliveryCharge::class);
    }

    /**
     * Resolved setups, keyed "zoneId:moduleId".
     *
     * Static for the same reason EtaConfigurationService memoises: an order list asks the same
     * question once per row, and without this a page of twenty-five orders issues twenty-five
     * identical queries (rule 11). Every write below clears it.
     */
    private static array $activeMemo = [];

    public function forgetActive(): void
    {
        self::$activeMemo = [];
    }

    public function create(array $data): AdditionalDeliveryCharge
    {
        return DB::transaction(function () use ($data) {
            // The controller checked this already; this is the same rule re-checked under a lock,
            // so only a save that RACED another one for the same pair arrives here.
            $clashes = $this->conflictingModuleNames(
                $data['zone_id'] ?? null,
                array_map('intval', (array) ($data['module_ids'] ?? [])),
                null,
                lock: true,
            );

            if ($clashes !== []) {
                throw new DuplicateAdditionalDeliveryChargeException(
                    translate('An additional delivery charge already exists in this zone for').' '.implode(', ', $clashes),
                );
            }

            $setup = AdditionalDeliveryCharge::create($this->attributes($data) + ['status' => $data['status'] ?? true]);
            $setup->modules()->sync($this->ids($data['module_ids'] ?? []));
            $setup->vehicles()->sync($this->ids($data['vehicle_ids'] ?? []));
            $this->forgetActive();

            return $setup->load(['modules', 'vehicles']);
        });
    }

    public function update(mixed $id, array $data): ?AdditionalDeliveryCharge
    {
        return DB::transaction(function () use ($id, $data) {
            $setup = AdditionalDeliveryCharge::find($id);

            if (! $setup) {
                return null;
            }

            $setup->update($this->attributes($data));
            $setup->modules()->sync($this->ids($data['module_ids'] ?? []));
            $setup->vehicles()->sync($this->ids($data['vehicle_ids'] ?? []));
            $this->forgetActive();

            return $setup->load(['modules', 'vehicles']);
        });
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $setup = AdditionalDeliveryCharge::find($id);

            if (! $setup) {
                return false;
            }

            $setup->modules()->detach();
            $setup->vehicles()->detach();
            $this->forgetActive();

            return (bool) $setup->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $setup = AdditionalDeliveryCharge::find($id);
        $this->forgetActive();

        return $setup ? $setup->update(['status' => (bool) $status]) : false;
    }

    public function find(mixed $id, array $with = ['zone', 'modules', 'vehicles']): ?AdditionalDeliveryCharge
    {
        return AdditionalDeliveryCharge::with($with)->find($id);
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

    /**
     * The active setup for this (zone, module), or null.
     *
     * Null means the platform makes no saver offer here, and every caller must treat that as
     * "standard delivery only" rather than inventing a default — an express charge nobody
     * configured is money taken for a promise the platform never made.
     *
     * §10.3's overlapping-zone rule applies: the `zoneId` header may carry several ids and the
     * first zone with a setup wins, so they are walked in the order given.
     */
    /**
     * The (zone, module) pairs whose EXPRESS orders this vehicle may not take.
     *
     * The express setup carries an optional list of vehicle categories. Empty means no
     * restriction — every deliveryman may take the order. Non-empty means only deliverymen whose
     * own vehicle is on the list may, so a pair listing categories the vehicle is not on is barred
     * for them.
     *
     * Express is still OFFERED at checkout either way (decided 2026-09-08): a customer may buy it
     * where no eligible deliveryman is online, and the order waits unassigned rather than the
     * option vanishing. The filter is about who SEES the order, not whether it can be placed.
     *
     * Returned as pairs rather than asked per order: one query answers for a whole page of orders
     * (rule 11), and the setups are one per (zone, module) so the list is small.
     *
     * @param  mixed  $vehicleId  the deliveryman's own vehicle, null when they have none
     * @return array<int, array{zone_id: int, module_id: int}>
     */
    public function pairsBarredForExpress(mixed $vehicleId): array
    {
        $barred = [];

        $setups = AdditionalDeliveryCharge::active()
            ->with(['modules:id', 'vehicles:id'])
            ->get();

        foreach ($setups as $setup) {
            $allowed = $setup->vehicles->pluck('id')->all();

            // No categories chosen — the setup places no restriction at all.
            if ($allowed === []) {
                continue;
            }

            if ($vehicleId !== null && in_array((int) $vehicleId, array_map('intval', $allowed), true)) {
                continue;
            }

            foreach ($setup->modules as $module) {
                $barred[] = ['zone_id' => (int) $setup->zone_id, 'module_id' => (int) $module->id];
            }
        }

        return $barred;
    }

    public function activeSetup(mixed $zoneIds, mixed $moduleId): ?AdditionalDeliveryCharge
    {
        foreach (array_filter((array) $zoneIds) as $zoneId) {
            $key = $zoneId.':'.$moduleId;

            if (! array_key_exists($key, self::$activeMemo)) {
                self::$activeMemo[$key] = AdditionalDeliveryCharge::active()
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
     * DESIGN NOTE — "Only one Additional Charge can be created for each Zone & Module
     * combination."
     *
     * Because a setup claims a SET of modules the test is an OVERLAP, exactly as the delivery
     * rule, free delivery and ETA screens are. The message names the clashing modules so the
     * admin can deselect them rather than guess which one collided.
     */
    public function conflictingModuleNames(mixed $zoneId, array $moduleIds, mixed $exceptId = null, bool $lock = false): array
    {
        if (empty($zoneId) || $moduleIds === []) {
            return [];
        }

        return AdditionalDeliveryCharge::query()
            ->where('zone_id', $zoneId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            // Locked only for the re-check inside create()'s transaction — two saves for the same
            // pair arriving at once both read an empty list otherwise, and both write.
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->with('modules:id,module_name')
            ->get()
            ->flatMap(fn ($setup) => $setup->modules)
            ->unique('id')
            ->whereIn('id', $moduleIds)
            ->pluck('module_name')
            ->values()
            ->all();
    }

    /**
     * The modules a setup may still claim in this zone: connected to the zone, minus those
     * another setup already covers.
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

    /** The picked modules the zone is not connected to, by name — the server-side guard. */
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

    /**
     * Setup-time guards, per module — port doc §8.1.
     *
     * Returns `[moduleId => reason]` for every picked module that fails, so the screen can name
     * the module rather than say "something is wrong". An empty array means the setup may save.
     *
     * The floor question — "what is the lowest a delivery here may be priced at" — is the same
     * one POS asks before offering the saver options and placement asks before applying the
     * reduction. All three go through DeliveryChargeService::deliveryFloor() so they cannot
     * drift apart and quote one price while charging another.
     *
     * A pair with no active rule and no pivot floor has nothing to check against, so it is
     * SKIPPED rather than rejected: §8.1 is a guard against pricing a delivery at nothing, not a
     * requirement that every pair be ruled before it can offer a discount.
     *
     * @return array<int, string>
     */
    public function setupErrors(array $data, mixed $zoneId): array
    {
        $errors = [];
        $express = $this->money($data['express_extra_charge'] ?? null);
        $expressTime = AdditionalDeliveryCharge::pairToMinutes(
            $data['express_reduce_delivery_time'] ?? null,
            $data['express_reduce_delivery_time_unit'] ?? 'min',
        );
        $reduce = $this->money($data['delay_reduce_charge'] ?? null);
        $delayTime = AdditionalDeliveryCharge::pairToMinutes(
            $data['delay_add_delivery_time'] ?? null,
            $data['delay_add_delivery_time_unit'] ?? 'min',
        );

        foreach ($this->ids($data['module_ids'] ?? []) as $moduleId) {
            // Both halves of an offer or neither: a charge with no time saved is a price rise
            // the customer gets nothing for, and time saved with no charge is a free upgrade.
            if (($express ?? 0) <= 0 || ($expressTime ?? 0) <= 0) {
                $errors[$moduleId] = 'express_required';

                continue;
            }

            if (($reduce ?? 0) <= 0 || ($delayTime ?? 0) <= 0) {
                $errors[$moduleId] = 'slightly_delay_required';

                continue;
            }

            // Express may not promise away more time than the pair's minimum allows.
            $minimum = app(EtaConfigurationService::class)->minimumDeliveryTimeFloor($zoneId, $moduleId);

            if ($minimum > 0 && $expressTime > $minimum) {
                $errors[$moduleId] = 'min_time_lt_reduce_time';

                continue;
            }

            $floor = $zoneId === null
                ? null
                : app(\App\Services\Order\DeliveryChargeService::class)->deliveryFloor(
                    $zoneId,
                    $moduleId,
                    app(ModuleZoneService::class)->findForModuleAndZone((int) $moduleId, (int) $zoneId),
                );

            if ($floor !== null && $floor > 0 && $reduce > $floor) {
                $errors[$moduleId] = 'reduce_charge_exceeds_minimum';
            }
        }

        return $errors;
    }

    private function takenModuleIds(mixed $zoneId, mixed $exceptId): array
    {
        return AdditionalDeliveryCharge::query()
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
        return [
            'zone_id' => $data['zone_id'],
            'express_extra_charge' => $this->money($data['express_extra_charge'] ?? null),
            'express_reduce_delivery_time' => AdditionalDeliveryCharge::pairToMinutes(
                $data['express_reduce_delivery_time'] ?? null,
                $data['express_reduce_delivery_time_unit'] ?? 'min',
            ),
            'delay_reduce_charge' => $this->money($data['delay_reduce_charge'] ?? null),
            'delay_add_delivery_time' => AdditionalDeliveryCharge::pairToMinutes(
                $data['delay_add_delivery_time'] ?? null,
                $data['delay_add_delivery_time_unit'] ?? 'min',
            ),
        ];
    }

    /** Null for a field the admin left empty — zero and empty are not the same offer. */
    private function money(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : max(0, (float) $value);
    }

    private function ids(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $values))));
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return AdditionalDeliveryCharge::query()
            ->with($with)
            ->withCount($withCount)
            ->when(! empty($filters['zone_id']), fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->when(! empty($filters['module_id']), fn ($q) => $q->whereHas('modules', fn ($m) => $m->where('modules.id', $filters['module_id'])))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), fn ($q) => $q->whereHas('zone', fn ($z) => $z->where('name', 'like', '%'.$filters['search'].'%')))
            ->orderBy('id', 'desc');
    }
}
