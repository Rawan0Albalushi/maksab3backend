<?php

namespace App\Services\System;

use App\Models\Module;
use App\Models\Zone;
use App\Services\BaseService;
use App\Traits\System\MemoizesLookupsTrait;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Collection;

class ModuleService extends BaseService
{
    use MemoizesLookupsTrait;

    public function findForStore(mixed $storeId): ?Module
    {
        return Module::withoutTranslation()
            ->join('stores', 'stores.module_id', '=', 'modules.id')
            ->where('stores.id', $storeId)
            ->first(['modules.id', 'modules.module_type']);
    }
    public function findTypeById(mixed $moduleId): mixed
    {
        return $this->typeMap()[(int) $moduleId] ?? null;
    }
    /**
     * The modules a customer in these zones may be offered.
     *
     * S19 closed the hole here: this asked the `module_zone` pivot, i.e. whether the module was
     * CONNECTED, so a module the zone could not price or time was still listed — the customer
     * picked it and the order failed further in. It now asks the same availability question
     * `Zone::scopeEffective()` and the zone resolver ask, from the module side, so the three
     * agree on one module set for one zone.
     *
     * Still one query: the pair test is a correlated EXISTS inside the `zones` whereHas that was
     * already here, not a second pass.
     */
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $zoneIds = $filters['zone_ids'] ?? [];

        $modules = $this->withMetrics(
            Module::withStorage()
                ->whereHas('zones', fn ($query) => Zone::constrainToAvailable(
                    $query->whereIn('module_zone.zone_id', $zoneIds)
                ))
                ->when($filters['exclude_parcel'] ?? false, fn ($query) => $query->notParcel())
                ->active(),
            $zoneIds
        )->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        Module::attachTopOffers($modules->getCollection(), $zoneIds);

        return $modules;
    }
    /**
     * Per-request memo for the active-module list.
     *
     * Static rather than an instance property because this service is resolved with `app()` at
     * each call site rather than bound as a singleton — an instance memo would be thrown away
     * between callers and every screen would re-read the same handful of rows. Several screens
     * ask for this list two or three times in one render (a module picker, a capability check, a
     * label map), and those repeats are duplicate queries, which this repo treats as defects.
     *
     * Same shape as DistanceService::$unitMemo, including an explicit forget for the write paths.
     */
    private static ?Collection $selectOptionsMemo = null;

    /**
     * Active modules as id + name, for a select. getList() paginates and eager-loads storage;
     * that is right for the module screens and far too much for a dropdown.
     */
    public function getSelectOptions(): Collection
    {
        if (self::$selectOptionsMemo !== null) {
            return self::$selectOptionsMemo;
        }

        return self::$selectOptionsMemo = Module::where('status', 1)
            ->orderBy('module_name')
            ->get(['id', 'module_name', 'module_type']);
    }

    /**
     * Cleared by Module::saved() and Module::deleted(), so a create-then-render request in the
     * same cycle cannot read a stale list.
     */
    public function forgetSelectOptions(): void
    {
        self::$selectOptionsMemo = null;
        self::forgetMemo();
    }

    public function find(mixed $id): ?Module
    {
        return Module::find($id);
    }
    public function hasRideShareModule(array $moduleIds): bool
    {
        return $this->byIdsQuery($moduleIds)->where('module_type', 'ride-share')->exists();
    }
    public function getServiceModuleIds(array $moduleIds): array
    {
        return $this->byIdsQuery($moduleIds)->where('module_type', 'service')->pluck('id')->all();
    }
    public function findSoleActive(): ?Module
    {
        return ApiCache::remember('modules', 'config_' . app()->getLocale(), function () {
            return Module::active()->count() == 1 ? Module::withStorage()->active()->first() : null;
        });
    }
    public function findActiveWithOffer(mixed $moduleId, array $zoneIds): ?Module
    {
        $module = Module::withoutGlobalScope('translate')
            ->where('id', $moduleId)
            ->active()
            ->first(['id']);

        if (! $module) {
            return null;
        }

        $modules = collect([$module]);
        Module::attachTopOffers($modules, $zoneIds);

        return $modules->first();
    }
    public function getAddData(array $input): array
    {
        return [
            'module_name' => ($input['module_name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'icon' => FileStorage::upload('module/', ($input['icon'] ?? null)),
            'thumbnail' => FileStorage::upload('module/', ($input['thumbnail'] ?? null)),
            'module_type' => ($input['module_type'] ?? null),
            'theme_id' => 1,
            'description' => ($input['description'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'short_description' => ($input['short_description'] ?? null)[array_search('default', ($input['lang'] ?? null))],
        ];
    }
    public function getUpdateData(array $input, object $module): array
    {
        return [
            'module_name' => ($input['module_name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'icon' => array_key_exists('icon', $input) ? FileStorage::update('module/', $module->icon, ($input['icon'] ?? null)) : $module->icon,
            'thumbnail' => array_key_exists('thumbnail', $input) ? FileStorage::update('module/', $module->thumbnail, ($input['thumbnail'] ?? null)) : $module->thumbnail,
            'theme_id' => 1,
            'description' => ($input['description'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'short_description' => ($input['short_description'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'all_zone_service' => false,
        ];
    }
    public function getActiveTypes(): array
    {
        return $this->byConditions(['status' => 1])->distinct()->pluck('module_type')->all();
    }
    /**
     * Is any ACTIVE module one that carries parcels?
     *
     * Capability-driven, not a `module_type === 'parcel'` string test: `config/module.php` marks
     * the trait with `is_parcel`, and gating on the flag means a new parcel-like type inherits the
     * behaviour without every call site being hunted down (port doc §16.1).
     *
     * Gates the Weight and Dimension Setup menu entries — settings that only mean something when
     * something is being parcelled.
     */
    /**
     * The ids of every ACTIVE module that carries parcels.
     *
     * Capability-driven, same as hasParcelCapability(). The delivery-rule form needs the ids, not
     * just a yes/no, because the wizard appears and disappears as the module multi-select changes
     * — that decision happens client-side, against this list.
     */
    public function parcelCapableModuleIds(): array
    {
        return $this->getSelectOptions()
            ->filter(fn ($module) => (bool) config('module.'.$module->module_type.'.is_parcel'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The modules a (zone, module) ETA configuration may cover.
     *
     * Capability-driven (`config('module.<type>.eta')`), never a module-name test — rental,
     * ride-share and service have no delivery window to estimate. Read by the ETA setup's picker,
     * its server-side guard, and the zone readiness rule, so all three agree on which modules the
     * question even applies to.
     *
     * @return array<int, int>
     */
    public function etaCapableModuleIds(): array
    {
        return $this->getSelectOptions()
            ->filter(fn ($module) => (bool) config('module.'.$module->module_type.'.eta'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The ids of every module of one `module_type` — e.g. "which of these ids is Parcel", for
     * the ETA setup's Parcel-only section, without hand-coding an id that differs per install.
     *
     * @return array<int, int>
     */
    public function moduleIdsOfType(string $type): array
    {
        return $this->getSelectOptions()
            ->filter(fn ($module) => $module->module_type === $type)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The modules a surge price may apply to.
     *
     * Capability-driven (`config('module.<type>.surge')`) for the reason etaCapableModuleIds()
     * gives: a surge is added to the delivery charge, and rental, ride-share and service price
     * their own trips and bookings without ever reaching DeliveryChargeService.
     *
     * @return array<int, int>
     */
    public function surgeCapableModuleIds(): array
    {
        return $this->getSelectOptions()
            ->filter(fn ($module) => (bool) config('module.'.$module->module_type.'.surge'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The modules a free-delivery setup or an additional delivery charge may cover.
     *
     * Capability-driven (`config('module.<type>.delivery_charge_setup')`) for the reason
     * etaCapableModuleIds() gives: both settings move the delivery charge, and rental, ride-share
     * and service price their own trips and bookings without reaching the core order pipeline.
     *
     * @return array<int, int>
     */
    public function deliveryChargeSetupModuleIds(): array
    {
        return $this->getSelectOptions()
            ->filter(fn ($module) => (bool) config('module.'.$module->module_type.'.delivery_charge_setup'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The modules a delivery rule may price.
     *
     * Capability-driven (`config('module.<type>.delivery_rule')`) for the reason
     * etaCapableModuleIds() gives.
     *
     * @return array<int, int>
     */
    public function deliveryRuleModuleIds(): array
    {
        return $this->getSelectOptions()
            ->filter(fn ($module) => (bool) config('module.'.$module->module_type.'.delivery_rule'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function hasParcelCapability(): bool
    {
        return $this->parcelCapableModuleIds() !== [];
    }

    public function getIdsByType(string $moduleType): array
    {
        return $this->byConditions(['module_type' => $moduleType])->pluck('id')->toArray();
    }
    public function allAreOfType(array $moduleIds, string $moduleType): bool
    {
        return $this->byIdsQuery($moduleIds)->exists()
            && ! $this->byIdsQuery($moduleIds)->where('module_type', '!=', $moduleType)->exists();
    }
    public function findServiceModuleId(): mixed
    {
        return $this->byConditions(['module_type' => 'service'])->value('id');
    }

    public function memoizedTypeById(mixed $moduleId): ?string
    {
        if (empty($moduleId)) {
            return null;
        }

        return $this->typeMap()[$moduleId] ?? null;
    }

    public function switcherModules(?int $zoneId = null): Collection
    {
        return $this->memoize('switcher_modules_'.($zoneId ?: 'all'), function () use ($zoneId) {
            $allowed = $zoneId ? $this->moduleIdsInZone($zoneId) : null;

            return collect($this->getCachedList())
                ->filter(fn (Module $module) => (int) $module->status === 1)
                ->filter(fn (Module $module) => $allowed === null || in_array((int) $module->id, $allowed, true))
                ->values();
        });
    }

    private function moduleIdsInZone(int $zoneId): array
    {
        return $this->memoize('module_ids_zone_'.$zoneId, fn () => DB::table('module_zone')
            ->where('zone_id', $zoneId)
            ->pluck('module_id')
            ->map('intval')
            ->all());
    }

    public function getCachedList(): mixed
    {
        return ApiCache::remember('modules', 'list_'.app()->getLocale(), function () {
            return Module::withStorage()->orderBy('module_name')->get();
        });
    }

    public function cachedById(mixed $moduleId): ?Module
    {
        if (empty($moduleId)) {
            return null;
        }

        $module = $this->cachedKeyedById()->get($moduleId);

        return $module ? clone $module : null;
    }

    private function cachedKeyedById(): Collection
    {
        $key = ApiCache::key('modules', 'list_'.app()->getLocale());

        return $this->memoize('modules_keyed_'.$key, fn () => collect($this->getCachedList())->keyBy('id'));
    }

    public function findRideShareModuleId(): ?int
    {
        return $this->memoize('ride_share_module_id', function () {
            $id = addon_published_status('RideShare')
                ? Module::withoutGlobalScopes()->where('module_type', 'ride-share')->value('id')
                : null;

            return $id === null ? null : (int) $id;
        });
    }

    private function typeMap(): array
    {
        return $this->memoize('type_map', fn () => Module::pluck('module_type', 'id')->all());
    }
    private function byConditions(array $conditions): mixed
    {
        return Module::where($conditions);
    }
    private function byIdsQuery(array $moduleIds): mixed
    {
        return Module::whereIn('id', $moduleIds);
    }
    private function withMetrics(mixed $query, array $zoneIds): mixed
    {
        $today = date('Y-m-d');

        return $query
            ->withCount([
                'stores' => fn ($storeQuery) => $storeQuery
                    ->whereIn('zone_id', $zoneIds)
                    ->whereHas('vendor', fn ($vendor) => $vendor->where('status', 1)),
                'stores as free_delivery_count' => fn ($storeQuery) => $storeQuery
                    ->whereIn('zone_id', $zoneIds)
                    ->whereHas('vendor', fn ($vendor) => $vendor->where('status', 1))
                    ->where('free_delivery', 1),
            ])
            ->selectSub(function ($sub) use ($zoneIds) {
                $sub->select('delivery_time')
                    ->from('stores')
                    ->whereColumn('stores.module_id', 'modules.id')
                    ->whereIn('stores.zone_id', $zoneIds)
                    ->where('stores.status', 1)
                    ->whereNotNull('stores.delivery_time')
                    ->orderByRaw('CASE '
                        . 'WHEN delivery_time LIKE "%hours%" THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(delivery_time, "-", 1), " ", 1) AS UNSIGNED) * 60 '
                        . 'WHEN delivery_time LIKE "%min%" OR delivery_time LIKE "%minute%" THEN CAST(SUBSTRING_INDEX(delivery_time, "-", 1) AS UNSIGNED) '
                        . 'ELSE 9999 END ASC')
                    ->limit(1);
            }, 'min_delivery_time_range')
            ->selectSub(function ($sub) use ($today) {
                $sub->selectRaw('COUNT(*)')
                    ->from('flash_sales')
                    ->whereColumn('flash_sales.module_id', 'modules.id')
                    ->where('flash_sales.is_publish', 1)
                    ->whereDate('flash_sales.start_date', '<=', $today)
                    ->whereDate('flash_sales.end_date', '>=', $today);
            }, 'flash_sale_count');
    }
}
