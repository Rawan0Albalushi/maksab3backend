<?php

namespace App\Services\Store;

use App\CentralLogics\Helpers;
use App\Http\Resources\Common\Store\StoreListResource;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\BaseService;
use App\Services\Item\CategoryService;
use App\Services\Item\ItemService;
use App\Services\Marketing\AdvertisementService;
use App\Services\Order\OrderTransactionService;
use App\Services\System\DataSettingService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Store\StoreDataTrait;
use App\Traits\Store\StorePayloadTrait;
use App\Traits\System\PrioritySettingsTrait;
use App\Traits\System\TranslationsTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Cache\ApiCache;
use App\Services\System\ModuleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use App\Services\System\BusinessSettingService;
use App\Support\Storage\FileStorage;

class StoreService extends BaseService
{
    use StorePayloadTrait, PrioritySettingsTrait;
    use PersonalizationTrait;
    use StoreDataTrait;
    use TranslationsTrait;
    private const AI_LIMIT_SETTING = [
        'image' => 'image_upload_limit_for_ai',
        'section' => 'section_wise_ai_limit',
    ];
    private const AI_USE_COLUMN = [
        'image' => 'image_wise_ai_use_count',
        'section' => 'section_wise_ai_use_count',
    ];
    private const PROVIDER_COUNTS = ['vehicles', 'campaigns'];
    private const PROVIDER_RELATIONS = ['storage', 'storeConfig:id,store_id,verified_seller'];
    private const OFFER_ITEM_LIMIT = 5;
    private const REORDER_ITEM_LIMIT = 5;
    public function findWithSubscriptionRelations(mixed $storeId, array $relations = []): mixed
    {
        return $this->findWithRelations($storeId, $relations);
    }
    public function countActiveIn(array $storeIds, mixed $moduleId): int
    {
        return Store::active()
            ->whereIn('id', $storeIds)
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->count();
    }
    public function findWithOpenState(mixed $storeId, mixed $longitude, mixed $latitude): mixed
    {
        return Store::withOpen($longitude ?? 0, $latitude ?? 0)->find($storeId);
    }
    public function findWithRelations(mixed $storeId, array $relations = [], bool $withoutTranslate = false): mixed
    {
        return Store::when($withoutTranslate, fn ($query) => $query->withoutGlobalScope('translate'))
            ->where('id', $storeId)
            ->with($relations)
            ->first();
    }
    public function getBannerStores(array $ids, mixed $moduleId): mixed
    {
        return Store::active()
            ->withStorage()
            ->when($moduleId, fn ($query) => $query->whereHas('zone.modules', fn ($z) => $z->where('modules.id', $moduleId)))
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
    public function findModuleId(mixed $storeId): ?int
    {
        return $this->byConditions(['id' => $storeId])->value('module_id');
    }
    public function findModuleTypeOnly(mixed $storeId): mixed
    {
        return $this->findModuleType($storeId);
    }
    public function getCartStores(array $storeIds, mixed $longitude, mixed $latitude): mixed
    {
        return Store::withStorage()
            ->WithOpenWithDeliveryTime($longitude, $latitude)
            ->with('module:id,module_type')
            ->whereIn('id', $storeIds)
            ->get()
            ->keyBy('id');
    }
    public function findIdBySlug(mixed $slug): mixed
    {
        return $this->byConditions(['slug' => $slug])->value('id');
    }
    public function getCouponCandidates(array $candidateIds, array $filters = []): mixed
    {
        return Store::active()
            ->withStorage()
            ->with('storeConfig:id,store_id,verified_seller')
            ->whereIn('id', $candidateIds)
            ->when($filters['store_id'] ?? null, fn ($q, $storeId) => $q->where('id', $storeId))
            ->when(
                ($filters['module_id'] ?? null) && ! ($filters['all_zone_service'] ?? false),
                fn ($q) => $q->whereIn('zone_id', $filters['zone_ids'] ?? [])
            )
            ->select('id', 'name', 'status', 'zone_id')
            ->get()
            ->keyBy('id');
    }
    public function existsInZones(mixed $storeId, array $zoneIds): bool
    {
        return Store::whereIn('zone_id', $zoneIds)->where('id', $storeId)->exists();
    }

    /**
     * An active store the caller can actually reach, addressed by id OR slug.
     *
     * Both spellings are accepted because every customer-facing store identifier on this API is:
     * a client holding a slug from a share link must not have to resolve it to an id first. The
     * zone and module narrowing is what makes "reach" true rather than merely "exists" -- a store
     * in another zone is not found rather than forbidden, since it does not serve this customer
     * at all.
     */
    public function findActiveInZones(mixed $identifier, array $zoneIds, mixed $moduleId = null): ?Store
    {
        if ($identifier === null || $identifier === '' || empty($zoneIds)) {
            return null;
        }

        return Store::active()
            ->whereIn('zone_id', $zoneIds)
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->where(fn ($query) => $query->where('id', $identifier)->orWhere('slug', $identifier))
            ->first();
    }
    public function findModuleTypeBasic(mixed $storeId): mixed
    {
        return $this->findModuleType($storeId);
    }
    public function find(mixed $id): mixed
    {
        return $this->findWithRelations($id);
    }
    public function findModuleType(mixed $id): mixed
    {
        return app(ModuleService::class)->findForStore($id)?->module_type;
    }
    public function getMetricsByModule(array $moduleIds): array
    {
        return Store::query()
            ->leftJoin('vendors', 'vendors.id', '=', 'stores.vendor_id')
            ->whereIn('stores.module_id', $moduleIds)
            ->groupBy('stores.module_id')
            ->selectRaw('stores.module_id AS module_id, COUNT(*) AS store_count, '
                .'SUM(CASE WHEN stores.status = 1 THEN 1 ELSE 0 END) AS active_store_count, '
                .'COUNT(DISTINCT CASE WHEN vendors.status = 1 THEN vendors.id END) AS vendor_count')
            ->toBase()->get()
            ->mapWithKeys(fn ($row) => [$row->module_id => [
                'stores' => (int) $row->store_count,
                'active_stores' => (int) $row->active_store_count,
                'vendors' => (int) $row->vendor_count,
            ]])->all();
    }
    public function updateProfile(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            $store->name = $data['name'];
            $store->address = $data['address'];
            $store->phone = $data['phone'];
            $store->meta_title = $data['meta_title'];
            $store->meta_description = $data['meta_description'];
            $store->meta_data = $data['meta_data'];
            $this->applyStoreMedia($store, $data);
            $store->save();

            $this->syncStoreTranslations($store, $data['translations'] ?? []);
            $this->syncVendorUserInfo($store);

            return $store;
        });
    }
    public function updateBusinessSetup(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            foreach ($data['store'] as $column => $value) {
                $store->{$column} = $value;
            }
            $this->applyStoreMedia($store, $data);
            $store->save();

            app(StoreConfigService::class)->ensureForStore($store->id);
            $this->syncStoreTranslations($store, $data['translations'] ?? [], false);

            return $store;
        });
    }
    public function updateOperationSetup(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            foreach ($data['store'] as $column => $value) {
                $store->{$column} = $value;
            }
            $store->save();

            $config = app(StoreConfigService::class)->findOrNewForStore($store->id);
            foreach ($data['config'] as $column => $value) {
                $config->{$column} = $value;
            }
            $config->minimum_stock_for_warning = $data['config']['minimum_stock_for_warning']
                ?? ($config->minimum_stock_for_warning ?? 0);
            $config->show_low_stock_count = $data['config']['show_low_stock_count']
                ?? ($config->show_low_stock_count ?? 1);
            $config->save();

            return $store;
        });
    }
    public function getVisitAgainList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $query = Store::withOpen($filters['longitude'] ?? 0, $filters['latitude'] ?? 0)
            ->whereHas('orders', fn ($order) => $order->where('user_id', $filters['user_id'] ?? null)->where('is_guest', 0))
            ->where('module_id', $filters['module_id'] ?? null)
            ->whereIn('zone_id', json_decode((string) ($filters['zone_id'] ?? ''), true) ?: [])
            ->withCount(['items', 'reviews as approved_reviews_count' => fn ($review) => $review->where('reviews.status', 1)])
            ->with([
                'storage',
                'storeConfig',
                'discount',
                'module:id,module_type',
                'itemsForReorder' => fn ($item) => $item->with(['unit', 'storage', 'storeCategory.storage']),
            ])
            ->Active();

        $paginator = $this->applyStorePersonalization($query, $filters['user_id'] ?? null)
            ->orderBy('open', 'desc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        foreach ($paginator->getCollection() as $store) {
            $store->setRelation('reorder_items', $store->itemsForReorder->take(self::REORDER_ITEM_LIMIT)->values());
            $store->unsetRelation('itemsForReorder');
        }

        return $paginator;
    }
    public function buildVisitAgainRows(mixed $stores, mixed $zoneId = null): array
    {
        $stores = collect($stores)->values();

        if ($stores->isEmpty()) {
            return [];
        }

        $storeIds = $stores->pluck('id')->map(fn ($id) => (int) $id)->filter()->all();
        $offers = $this->offersByStore($storeIds);
        $categories = $this->topCategories($storeIds, 5);
        $advertised = $this->advertisedStoreIds($zoneId, $storeIds);
        $this->loadListRelations($stores);

        return $stores->map(function ($store) use ($advertised, $offers, $categories) {
            $rating = $this->calculateRating($store->rating ?: [0, 0, 0, 0, 0]);

            return array_merge(
                (new StoreListResource($store))->withOptions([
                    'advertised_store_ids' => $advertised,
                    'offers' => $offers[(int) $store->id] ?? [],
                    'category_data' => $categories[(int) $store->id] ?? [],
                ])->render(),
                [
                    'avg_rating' => (float) $rating['rating'],
                    'rating_count' => $store->module?->module_type === 'service'
                        ? (int) $rating['total']
                        : (int) ($store->approved_reviews_count ?? 0),
                    'positive_rating' => $rating['positive_rating'],
                    'items' => $this->reorderItemRows($store),
                ]
            );
        })->all();
    }
    public function getOfferList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $moduleId = $filters['module_id'] ?? null;
        $zones = $filters['zone_ids'] ?? [];
        $longitude = (float) ($filters['longitude'] ?? 0);
        $latitude = (float) ($filters['latitude'] ?? 0);
        $filter = $filters['filter'] ?? [];

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '' && empty($filter['search'])) {
            $filter['search'] = $search;
        }

        $query = Store::withStorage()->WithOpenWithDeliveryTime($longitude, $latitude)
            ->Active()
            ->withCount('reviews')
            ->withItemRatingAvg('avg_r')
            ->when(is_numeric($moduleId), fn ($qq) => $qq->where('module_id', $moduleId))
            ->when(! empty($zones), fn ($qq) => $qq->whereIn('zone_id', $zones))
            ->whereHas('items', function ($qq) {
                $qq->active();
            })
            ->with(['items' => function ($qq) {
                $qq->active()->withStorage()
                    ->orderByDesc('discount')->orderByDesc('id');
            }])
            ->whereHas('discount', function ($q) {
                $q->validate();
            });

        if ($filter) {
            $query = $query->applyStoreFilter($filter);
        }

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $paginator->setCollection(
            collect($paginator->items())->map(fn ($store) => $this->formatStore($store))->values()
        );
    }
    public function searchSuggestList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $zones = json_decode((string) ($filters['zone_id'] ?? ''), true) ?: [];
        $module = $filters['module'] ?? null;
        $priceInput = $filters['price'] ?? [];
        $hasPrice = ($priceInput['min_price'] ?? null) || ($priceInput['max_price'] ?? null) || ($priceInput['price'] ?? null);
        $scopeInput = new Fluent($filters['scope_input'] ?? []);

        $paginator = Store::withOpen((float) ($filters['longitude'] ?? 0), (float) ($filters['latitude'] ?? 0))
            ->withStorage()->with(['module.storage'])->weekday()
            ->search(keywords: $filters['name'] ?? '', relations: [
                'translations' => 'value',
                'items.nutritions' => 'nutrition',
                'items.allergies' => 'allergy',
                'items.generic' => 'generic_name',
                'items.ecommerce_item_details.brand' => 'name',
                'items.pharmacy_item_details.common_condition' => 'name',
            ])
            ->when($module, function ($query) use ($zones, $module) {
                $query->whereHas('zone.modules', fn ($q) => $q->where('modules.id', $module['id']))->module($module['id']);
                if (! $module['all_zone_service']) {
                    $query->whereIn('zone_id', $zones);
                }
            })
            ->when(! $module, fn ($query) => $query->whereIn('zone_id', $zones))
            ->active()
            ->when($hasPrice, function ($query) use ($scopeInput, $priceInput) {
                $query->where(function ($q) use ($scopeInput, $priceInput) {
                    $q->whereHas('items', fn ($i) => $i->applyPriceRange($scopeInput));
                    if (addon_published_status('Service')) {
                        $q->orWhereHas('services', fn ($s) => $s->applyPriceRange(
                            $priceInput['min_price'] ?? null,
                            $priceInput['max_price'] ?? null,
                            $priceInput['price'] ?? null
                        ));
                    }
                });
            })
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn ($store) => [
                'id' => $store->id,
                'name' => $store->name,
                'logo' => $store->logo,
                'logo_full_url' => $store->logo_full_url,
                'module_id' => $store->module_id,
                'open' => (int) ($store->open ?? 0),
                'distance' => isset($store->distance) ? (float) $store->distance : null,
                'distance_km' => isset($store->distance) ? round(((float) $store->distance) / 1000, 2) : null,
                'module' => $store->module ? [
                    'id' => $store->module->id,
                    'name' => $store->module->module_name,
                    'image' => $store->module->icon_full_url,
                    'type' => $store->module->module_type,
                ] : null,
            ])
        );
    }
    public function advertisedIdsFor(mixed $stores, mixed $zoneId = null): array
    {
        $ids = collect($stores)
            ->filter(fn ($store) => $store instanceof Store)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->advertisedStoreIds($zoneId, $ids);
    }

    public function loadListRelations(mixed $stores): mixed
    {
        $collection = collect($stores)->filter(fn ($store) => $store instanceof Store);

        if ($collection->isNotEmpty()) {
            (new EloquentCollection($collection->all()))
                // happyHourEnrollments.happyHour so StoreListResource can report the window
                // without asking per row -- that would be one query per store on every listing
                // in the app. Batched here it is one more query for the whole page.
                ->loadMissing(['storage', 'schedules', 'storeConfig', 'discount', 'module.storage',
                    'happyHourEnrollments.happyHour.dates']);
        }

        return $stores;
    }
    public function buildRowsWithTopItems(mixed $stores, mixed $zoneId = null): array
    {
        $stores = collect($stores)->values();

        if ($stores->isEmpty()) {
            return [];
        }

        $storeIds = $stores->pluck('id')->map(fn ($id) => (int) $id)->filter()->all();
        $topItems = $this->topItemRows($storeIds, 5);
        $offers = $this->offersByStore($storeIds);
        $categories = $this->topCategories($storeIds, 5);
        $advertised = $this->advertisedStoreIds($zoneId, $storeIds);
        $this->loadListRelations($stores);

        return $stores->map(fn ($store) => (new StoreListResource($store))->withOptions([
            'advertised_store_ids' => $advertised,
            'with_items' => true,
            'top_items' => $topItems[(int) $store->id] ?? [],
            'offers' => $offers[(int) $store->id] ?? [],
            'category_data' => $categories[(int) $store->id] ?? [],
        ])->render())->all();
    }
    public function loadDetailRelations(mixed $store): mixed
    {
        if ($store instanceof Store) {
            $store->loadMissing(['storage', 'schedules', 'storeConfig', 'discount', 'module.storage', 'store_sub', 'activeCoupons']);
        }

        return $store;
    }
    public function getList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->buildAllStoresQuery(
            $filters['zone_id'] ?? null, $filters['filter_data'] ?? 'all', $filters['type'] ?? 'all',
            $filters['store_type'] ?? 'all', $this->pageSize($paginate), $this->pageNumber($paginate),
            $filters['featured'] ?? null, $filters['longitude'] ?? null, $filters['latitude'] ?? null,
            $filters['filter'] ?? '', $filters['rating_count'] ?? null, $filters['store_filter'] ?? [],
            $filters['user_id'] ?? null, $filters['module_id'] ?? null
        ), $paginate);
    }
    public function getLatestList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->withMainCategories(
            $this->buildLatestQuery(...$this->commonListArgs($filters, $paginate, [$filters['user_id'] ?? null]))
        ), $paginate);
    }
    public function getDistanceWiseList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->storeListPayload($filters, $paginate, [
            'with_count' => ['items', 'campaigns', 'reviews'],
            'scope' => fn ($query) => $query->when($filters['name'] ?? null, function ($q) use ($filters) {
                $keywords = explode(' ', $filters['name']);
                $q->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('name', 'like', "%{$keyword}%");
                    }

                    return $q->applyRelationShipSearch(relationships: ['translations' => 'value'], searchParameter: $keywords);
                });
            }),
            'order' => fn ($query) => $query->orderBy('distance'),
        ]), $paginate);
    }
    public function getPopularList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator(
            $this->buildPopularQuery(...$this->commonListArgs($filters, $paginate, [$filters['user_id'] ?? null])),
            $paginate
        );
    }
    public function getDiscountedList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->buildDiscountedQuery(...$this->commonListArgs($filters, $paginate, [
            $filters['filter'] ?? '', $filters['rating_count'] ?? null, $filters['store_filter'] ?? [],
            $filters['user_id'] ?? null,
        ])), $paginate);
    }
    public function getTopRatedList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->storeListPayload($filters, $paginate, [
            'with_count' => ['items', 'campaigns'],
            'scope' => fn ($query) => $query->whereNotNull('rating')->whereRaw('LENGTH(rating) > 0'),
            'personalise' => true,
        ]), $paginate);
    }
    public function getRecommendedList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->withMainCategories(
            $this->buildRecommendedQuery(...$this->commonListArgs($filters, $paginate, [$filters['user_id'] ?? null]))
        ), $paginate);
    }
    public function searchList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->buildSearchQuery(
            $filters['name'] ?? null, $filters['zone_id'] ?? null, $filters['category_id'] ?? null,
            $this->pageSize($paginate), $this->pageNumber($paginate), $filters['type'] ?? 'all',
            $filters['longitude'] ?? null, $filters['latitude'] ?? null, $filters['filter'] ?? '',
            $filters['rating_count'] ?? null, $filters['category_ids'] ?? null, $filters['store_filter'] ?? [],
            $filters['user_id'] ?? null, new Fluent($filters['scope_input'] ?? []),
            ['sort_by' => $filters['sort_by'] ?? 'default', 'filter_by' => $filters['filter_by'] ?? []]
        ), $paginate);
    }
    public function findDetail(mixed $identifier, mixed $longitude = null, mixed $latitude = null): mixed
    {
        return $this->fetchStoreDetail($identifier, $longitude, $latitude);
    }
    public function getVerifiedList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->storeListPayload($filters, $paginate, [
            'with_count' => ['items', 'campaigns', 'reviews'],
            'scope' => fn ($query) => $query->whereHas('storeConfig', fn ($q) => $q->where('verified_seller', 1)),
            'order' => fn ($query) => $query->orderBy('name'),
            'attach_categories' => true,
        ]), $paginate);
    }
    public function getTopOfferNearMeList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->buildTopOfferQuery(
            zone_id: $filters['zone_id'] ?? null, limit: $this->pageSize($paginate),
            offset: $this->pageNumber($paginate), type: $filters['type'] ?? 'all',
            longitude: $filters['longitude'] ?? null, latitude: $filters['latitude'] ?? null,
            name: $filters['name'] ?? null, sort: $filters['sort_by'] ?? null,
            halal: $filters['halal'] ?? null, user_id: $filters['user_id'] ?? null
        ), $paginate);
    }
    public function getQuickDeliveryList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->buildQuickDeliveryQuery(...$this->commonListArgs($filters, $paginate, [
            $filters['is_ad'] ?? null, $filters['store_filter'] ?? [], $filters['with_items'] ?? null,
            $filters['user_id'] ?? null,
        ])), $paginate);
    }
    public function getExclusiveDealsList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->buildExclusiveDealsQuery(
            $filters['zone_id'] ?? null, $filters['module_id'] ?? null,
            $filters['longitude'] ?? null, $filters['latitude'] ?? null,
            $this->pageSize($paginate), $this->pageNumber($paginate),
            $filters['store_filter'] ?? [], $filters['user_id'] ?? null
        ), $paginate);
    }
    public function findUnscopedWithModule(mixed $storeId): mixed
    {
        return $this->unscopedQuery()->with('module:id,module_type')->find($storeId);
    }
    public function isServiceModuleStore(mixed $identifier): mixed
    {
        $store = $this->unscopedQuery()
            ->with('module:id,module_type')
            ->where(fn ($q) => is_numeric($identifier) ? $q->where('id', $identifier) : $q->where('slug', $identifier))
            ->first();

        return $store && $store->module?->module_type === 'service' ? $store->module : null;
    }
    public function recordVisit(mixed $storeId, mixed $userId): void
    {
        if (! $userId) {
            return;
        }

        Helpers::visitor_log(model: 'store', user_id: $userId, visitor_log_id: $storeId, order_count: false);
        $this->recordStoreAction($userId, $storeId, 'store_view');
    }
    public function getDetailExtras(Store $store): array
    {
        $storeId = $store->id;
        $categoryIds = array_map('intval', DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->selectRaw('categories.position as positions, IF((categories.position = "0"), categories.id, categories.parent_id) as categories')
            ->where('items.store_id', $storeId)
            ->where('categories.status', 1)
            ->groupBy('categories', 'positions')
            ->get()
            ->pluck('categories')
            ->toArray());

        return [
            'category_ids' => $categoryIds,
            'category_details' => app(CategoryService::class)->getByIdsWithStorage($categoryIds),
            'price_range' => app(ItemService::class)->getPriceRangeForStore($storeId),
            'store_categories' => Helpers::storeCategoryStatus()
                ? app(StoreCategoryService::class)->getActiveForStore($storeId)
                : [],
        ];
    }
    public function earningSummary($vendorId): array
    {
        return app(OrderTransactionService::class)->earningSummaryForVendor($vendorId);
    }
    public function getExportData($vendors): array
    {
        $rows = [];

        foreach ($vendors as $vendor) {
            if ($vendor->stores->count() < 1) {
                break;
            }

            $store = $vendor->stores[0];
            $rows[] = [
                'Id' => $store->id,
                'OwnerId' => $vendor->id,
                'OwnerFirstName' => $vendor->f_name,
                'OwnerLastName' => $vendor->l_name,
                'ProviderName' => $store->name,
                'Phone' => $vendor->phone,
                'Email' => $vendor->email,
                'Logo' => $store->logo,
                'CoverPhoto' => $store->cover_photo,
                'Latitude' => $store->latitude,
                'Longitude' => $store->longitude,
                'Address' => $store->address ?? null,
                'ZoneId' => $store->zone_id,
                'ModuleId' => $store->module_id,
                'Comission' => $store->comission ?? 0,
                'Tax' => $store->tax ?? 0,
                'PickupTime' => $store->delivery_time ?? '20-30',
                'ScheduleTrip' => $store->schedule_order == 1 ? 'yes' : 'no',
                'Status' => $store->status == 1 ? 'active' : 'inactive',
                'ReviewsSection' => $store->reviews_section == 1 ? 'active' : 'inactive',
                'storeOpen' => $store->active == 1 ? 'yes' : 'no',
            ];
        }

        return $rows;
    }
    public function createFromVendorRegistration(Vendor $vendor, array $data, array $translations): Store
    {
        $store = new Store;
        $store->name = $translations[0]['value'];
        $store->phone = $data['phone'];
        $store->email = $data['email'];
        $store->logo = FileStorage::upload('store/', $data['logo']);
        $store->cover_photo = FileStorage::upload('store/cover/', $data['cover_photo'] ?? null);
        $store->address = $translations[1]['value'] ?? null;
        $store->latitude = $data['latitude'];
        $store->longitude = $data['longitude'];
        $store->vendor_id = $vendor->id;
        $store->zone_id = $data['zone_id'];
        $store->tin = $data['tin'] ?? null;
        $store->tin_expire_date = ($data['tin_expire_date'] ?? null) == 'null' ? null : ($data['tin_expire_date'] ?? null);
        $store->tin_certificate_image = FileStorage::upload('store/', $data['tin_certificate_image'] ?? null);
        $store->delivery_time = $data['minimum_delivery_time'].'-'.$data['maximum_delivery_time'].' '.$data['delivery_time_type'];
        $store->module_id = $data['module_id'];
        $store->status = 0;
        $store->store_business_model = 'none';
        $store->pickup_zone_id = $data['pickup_zone_id'] ?? json_encode([]);
        $store->save();

        if (config('module.'.$store->module->module_type)['always_open']) {
            $this->createSchedule($store->id);
        }

        $this->insertTranslations($store, $translations);

        return $store;
    }
    public function setBusinessModel(Store $store, mixed $plan, mixed $packageId): string
    {
        if (! Helpers::subscription_check()) {
            $store->store_business_model = 'commission';
            $store->save();

            return 'commission';
        }

        if ($plan == 'subscription' && $packageId != null) {
            $store->package_id = $packageId;
            $store->save();

            return 'subscription';
        }

        if ($plan == 'commission') {
            $store->store_business_model = 'commission';
            $store->save();

            return 'commission';
        }

        return 'business_model_fail';
    }
    public function createSchedule(int $storeId, array $days = [0, 1, 2, 3, 4, 5, 6], string $openingTime = '00:00:00', string $closingTime = '23:59:59')
    {
        $rows = array_map(fn ($day) => [
            'store_id' => $storeId,
            'day' => $day,
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
        ], $days);

        try {
            app(StoreScheduleService::class)->upsertMany($rows);

            return true;
        } catch (\Exception $exception) {
            return $exception;
        }
    }
    public function findForVendorProfile(mixed $storeId): ?Store
    {
        return $this->findWithRelations(
            $storeId,
            ['translations', 'store_sub_update_application.package', 'module', 'storeConfig'],
            true
        );
    }
    public function toggleActive(mixed $store): bool
    {
        $store->active = $store->active ? 0 : 1;
        $store->save();

        return (bool) $store->active;
    }
    public function updateAnnouncement(mixed $store, array $data): void
    {
        $store->announcement = $data['announcement_status'];
        $store->announcement_message = $data['announcement_message'];
        $store->save();
    }
    public function providerEarningSummary(mixed $store): array
    {
        return [
            'todays_earning' => (float) $store->todays_trip_earning()->sum('store_amount'),
            'this_week_earning' => (float) $store->this_week_trip_earning()->sum('store_amount'),
            'this_month_earning' => (float) $store->this_month_trip_earning()->sum('store_amount'),
        ];
    }
    public function findByIdOrSlug(mixed $identifier): ?Store
    {
        return $this->applyIdOrSlug(Store::query(), $identifier)->first();
    }
    public function findProviderDetail(mixed $identifier): ?Store
    {
        return Store::where(fn ($query) => $query->where('id', $identifier)->orWhere('slug', $identifier))
            ->when(config('module.current_module_data'), fn ($query) => $query->module(config('module.current_module_data')['id']))
            ->withCount([
                'vehicle_identity as total_vehicle_count',
                'vehicles as brand_count' => fn ($query) => $query->select(DB::raw('COUNT(DISTINCT(brand_id))')),
            ])
            ->with(['discount' => fn ($query) => $query->validate()])
            ->first()
            ?->loadMissing(['storage', 'module.storage', 'schedules', 'store_sub', 'storeConfig']);
    }
    public function getProviderLatestList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->storeListPayload($filters, $paginate, [
            'with_count' => self::PROVIDER_COUNTS,
            'with' => self::PROVIDER_RELATIONS,
            'order' => fn ($query) => $query->latest(),
            'personalise' => true,
        ]), $paginate);
    }
    public function getProviderPopularList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->storeListPayload($filters, $paginate, [
            'with_count' => array_merge(self::PROVIDER_COUNTS, ['reviews', 'trips']),
            'with' => self::PROVIDER_RELATIONS,
            'order' => fn ($query) => $query
                ->orderBy('trips_count', 'desc')
                ->orderBy('open', 'desc')
                ->orderBy('distance'),
            'personalise' => true,
            'verified_seller' => true,
        ]), $paginate);
    }
    public function getProviderVerifiedList(array $filters = [], array $paginate = []): array
    {
        return $this->withPaginator($this->storeListPayload($filters, $paginate, [
            'with_count' => array_merge(self::PROVIDER_COUNTS, ['reviews', 'trips']),
            'with' => self::PROVIDER_RELATIONS,
            'scope' => fn ($query) => $query->whereHas('storeConfig', fn ($q) => $q->where('verified_seller', 1)),
            'order' => fn ($query) => $query->orderBy('open', 'desc')->orderBy('distance'),
            'personalise' => true,
            'verified_seller' => true,
        ]), $paginate);
    }
    public function aiUsageAllowance(mixed $storeId, string $kind): array
    {
        if (! $storeId) {
            return ['allowed' => true, 'config' => null];
        }

        $config = app(StoreConfigService::class)->findOrNewForStore($storeId);
        $limit = app(BusinessSettingService::class)->value(self::AI_LIMIT_SETTING[$kind]);
        $used = (int) ($config->{self::AI_USE_COLUMN[$kind]} ?? 0);

        if (! $limit || $limit <= $used) {
            return ['allowed' => false, 'config' => null];
        }

        return ['allowed' => true, 'config' => $config];
    }
    public function recordAiUsage(mixed $config, string $kind, int $by): void
    {
        if (! $config) {
            return;
        }

        $column = self::AI_USE_COLUMN[$kind];

        if ($config->exists) {
            $config->increment($column, $by);

            return;
        }

        $config->{$column} = $by;
        $config->save();
    }
    public function getStoresForCategories($categoryIds, $zoneId, int $limit,int $offset, $type,$longitude=0,$latitude=0,$filter=null,$ratingCount=null, ?array $storeFilter = null, $userId = null)
    {
        $categoryIds = isset($categoryIds)?(is_array($categoryIds)?$categoryIds:json_decode($categoryIds)):[];
        $paginator = Store::
        WithOpenWithDeliveryTime($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['items' => fn ($query) => $query->select('id', 'store_id', 'discount')])
            ->when(isset($categoryIds) && (count($categoryIds)>0), function($query)use($categoryIds){
                return $query->whereHas('items.category',function($q)use($categoryIds){
                    return $q->whereIn('id',$categoryIds)->orWhereIn('parent_id', $categoryIds);
                });
            })
            ->when(config('module.current_module_data'), function($query)use($zoneId){
                return  $query->whereHas('zone.modules', function($query){
                    return $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    return  $query->whereIn('zone_id', json_decode($zoneId, true));
                }
            })
            ->active()->type($type)
            ->when($filter && in_array('free_delivery',$filter),function ($qurey){
                return $qurey->where('free_delivery',1);
            })
            ->when($filter && in_array('coupon',$filter),function ($qurey){
                return $qurey->has('activeCoupons');
            })
            ->when($ratingCount, function($query) use ($ratingCount){
                return $query->selectSub(function ($query) use ($ratingCount){
                    return  $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$ratingCount]);
                }, 'avg_r')->having('avg_r', '>=', $ratingCount);
            })
            ->when($filter && in_array('top_rated',$filter),function ($qurey){
                return $qurey->whereNotNull('rating')->whereRaw("LENGTH(rating) > 0");
            })
            ->when($filter && in_array('discounted',$filter),function ($qurey){
                return  $qurey->where(function ($query) {
                    return  $query->whereHas('items', function ($q) {
                        return $q->Discounted();
                    });
                });
            })
            ->when($filter && in_array('currently_open',$filter),function ($qurey){
                return $qurey->having('open', '>', 0);
            })
            ->orderBy('open', 'desc')
            ->when($filter && in_array('popular',$filter),function ($qurey){
                // stores.total_order (maintained in PlaceNewOrderTrait) rather than
                // withCount('orders'), which counted the orders table per candidate store
                // before the limit could apply.
                return $qurey->orderBy('total_order', 'desc');
            })
            ->when(($filter && in_array('nearby',$filter)) ,function ($qurey){
                return $qurey->orderBy('distance');
            })
            ->when($filter && in_array('fast_delivery',$filter),function ($qurey){
                return $qurey->orderBy('min_delivery_time');
            })
            ->when($storeFilter, fn ($q) => $q->applyStoreFilter($storeFilter));

            $paginator = $this->applyStorePersonalization($paginator, $userId, $filter);
            $paginator = $paginator->latest()->paginate($limit, ['*'], 'page', $offset);

        $this->attachStoreCategoryIds($paginator);

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'stores' => $paginator->items()
        ];
    }
    public function getCategoryStores($categoryId, $zoneId, int $limit,int $offset, $type,$longitude=0,$latitude=0)
    {
        $paginator = Store::
        withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['items' => fn ($query) => $query->select('id', 'store_id', 'discount')])
            ->whereHas('items.category',function($q)use($categoryId){
                return $q->when(is_numeric($categoryId),function ($qurey) use($categoryId){
                    return $qurey->whereId($categoryId)->orWhere('parent_id', $categoryId);
                })
                    ->when(!is_numeric($categoryId),function ($qurey) use($categoryId){
                        $qurey->where('slug', $categoryId);
                    });
            })
            ->when(config('module.current_module_data'), function($query)use($zoneId){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zoneId, true));
                }
            })
            ->active()->type($type)
            ->latest()->paginate($limit, ['*'], 'page', $offset);

        $this->attachStoreCategoryIds($paginator);

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'stores' => $paginator->items()
        ];
    }
    public function findWithZone(mixed $id): mixed
    {
        return $this->findWithRelations($id, ['zone']);
    }
    public function findWithDiscountAndSubscription(mixed $storeId): mixed
    {
        return $this->findWithRelations($storeId, ['discount', 'store_sub']);
    }
    public function findWithOpenStateAt(mixed $storeId, mixed $scheduleAt): mixed
    {
        return Store::with(['discount', 'store_sub'])
            ->selectRaw(...$this->openStateSelect($scheduleAt))
            ->where('id', $storeId)
            ->first();
    }
    public function countWithPendingVendor(mixed $moduleId): int
    {
        return Store::whereHas('vendor', fn ($query) => $query->where('status', null))->module($moduleId)->count();
    }
    public function findForVendor(mixed $vendorId): mixed
    {
        return $this->byConditions(['vendor_id' => $vendorId])->first();
    }
    public function unsubscribeExpired(mixed $currentDate): int
    {
        return $this->unscopedQuery()
            ->whereHas('store_subs', function ($query) use ($currentDate) {
                $query->withoutGlobalScopes()
                    ->where('status', 1)
                    ->whereDate('expiry_date', '<=', $currentDate);
            })
            ->update([
                'status' => 0,
                'pos_system' => 1,
                'self_delivery_system' => 1,
                'reviews_section' => 1,
                'free_delivery' => 0,
                'store_business_model' => 'unsubscribed',
            ]);
    }
    public function findActiveOpenAt(mixed $storeId, mixed $scheduleAt): ?Store
    {
        return Store::selectRaw(...$this->openStateSelect($scheduleAt))
            ->where('id', $storeId)
            ->active()
            ->first();
    }
    public function getServiceModuleProviders(): mixed
    {
        return Store::where('module_id', app(\App\Services\System\ModuleService::class)->findServiceModuleId())
            ->orderBy('name')
            ->get(['id', 'name', 'zone_id']);
    }
    private function byConditions(array $conditions): mixed
    {
        return Store::where($conditions);
    }
    private function commonListArgs(array $filters, array $paginate, array $extra = []): array
    {
        return array_merge([
            $filters['zone_id'] ?? null,
            $this->pageSize($paginate),
            $this->pageNumber($paginate),
            $filters['type'] ?? 'all',
            $filters['longitude'] ?? null,
            $filters['latitude'] ?? null,
        ], $extra);
    }
    private function unscopedQuery(): mixed
    {
        return Store::withoutGlobalScopes();
    }
    private function applyIdOrSlug(mixed $query, mixed $identifier): mixed
    {
        return $query->when(is_numeric($identifier), fn ($scoped) => $scoped->where('id', $identifier))
            ->when(! is_numeric($identifier), fn ($scoped) => $scoped->where('slug', $identifier));
    }
    private function openStateSelect(mixed $scheduleAt): array
    {
        return [
            '*, IF(((select count(*) from `store_schedule` where `stores`.`id` = `store_schedule`.`store_id`'
            . ' and `store_schedule`.`day` = ? and `store_schedule`.`opening_time` < ?'
            . ' and `store_schedule`.`closing_time` > ?) > 0), true, false) as open',
            [$scheduleAt->format('w'), $scheduleAt->format('H:i:s'), $scheduleAt->format('H:i:s')],
        ];
    }
    private function applyStoreMedia(Store $store, array $data): void
    {
        $store->logo = $data['logo_file']
            ? FileStorage::update('store/', $store->logo, $data['logo_file'])
            : $store->logo;
        $store->cover_photo = $data['cover_photo_file']
            ? FileStorage::update('store/cover/', $store->cover_photo, $data['cover_photo_file'])
            : $store->cover_photo;
        $store->meta_image = $data['meta_image_file']
            ? FileStorage::update('store/', $store->meta_image, $data['meta_image_file'])
            : $store->meta_image;
    }
    private function syncStoreTranslations(Store $store, array $rows, bool $dropDefaultLocale = true): void
    {
        $defaultLocale = Helpers::system_default_language();

        foreach ($rows as $row) {
            if (! isset($row['locale'], $row['key'])) {
                continue;
            }

            if ($dropDefaultLocale && $row['locale'] === $defaultLocale) {
                $store->translations()
                    ->where('locale', $row['locale'])
                    ->where('key', $row['key'])
                    ->delete();

                continue;
            }

            $store->translations()->updateOrCreate(
                ['locale' => $row['locale'], 'key' => $row['key']],
                ['value' => $row['value'] ?? null]
            );
        }
    }
    private function syncVendorUserInfo(Store $store): void
    {
        $userinfo = $store->vendor?->userinfo;

        if (! $userinfo) {
            return;
        }

        $userinfo->f_name = $store->name;
        $userinfo->image = $store->logo;
        $userinfo->save();
    }
    private function reorderItemRows(mixed $store): array
    {
        return collect($store->reorder_items ?? [])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => $item->name,
                'image_full_url' => $item->image_full_url,
                'price' => (float) $item->price,
                'discount' => (float) $item->discount,
                'discount_type' => $item->discount_type,
            ])
            ->values()
            ->all();
    }
    private function formatStore(Store $store): array
    {
        $offerItems = $store->relationLoaded('items')
            ? collect($store->getRelation('items'))->take(self::OFFER_ITEM_LIMIT)->values()
            : collect();

        return (new StoreListResource($store))->withOptions([
            'top_items' => $offerItems->map(fn ($item) => $this->formatOfferItem($item, $store))->values()->all(),
            'with_items' => true,
        ])->render();
    }
    private function formatOfferItem(mixed $item, Store $store): array
    {
        $discount = Helpers::product_discount_calculate($item, $item->price, $store, true);
        $price = (float) $item->price;

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'image_full_url' => $item->image_full_url,
            'price' => $price,
            'discounted_price' => max(0, round($price - (float) ($discount['discount_amount'] ?? 0), 2)),
            'discount' => (float) ($discount['discount_percentage'] ?? 0),
            'discount_type' => $discount['original_discount_type'] ?? $item->discount_type,
        ];
    }
    private function advertisedStoreIds(mixed $zoneId, array $storeIds): array
    {
        if (empty($storeIds)) {
            return [];
        }

        $zones = json_decode((string) $zoneId, true) ?: [];
        $module = config('module.current_module_data');

        return app(AdvertisementService::class)->getValidStoreIds([
            'store_ids' => $storeIds,
            'module_id' => $module ? $module['id'] : null,
            'zone_ids' => $zones,
            'apply_zones' => ! empty($zones) && (! $module || ! ($module['all_zone_service'] ?? false)),
        ]);
    }
    private function withMainCategories(array $payload): array
    {
        $payload['stores'] = $this->attachMainCategories($payload['stores']);

        return $payload;
    }
    private function storeSortSettings(string $prefix): array
    {
        return [
            'default_status' => app(BusinessSettingService::class)->value($prefix . '_default_status') ?? 1,
            'general' => $this->findPrioritySetting(name: $prefix . '_sort_by_general', type: 'general'),
            'unavailable' => $this->findPrioritySetting(name: $prefix . '_sort_by_unavailable', type: 'unavailable'),
            'temp_closed' => $this->findPrioritySetting(name: $prefix . '_sort_by_temp_closed', type: 'temp_closed'),
            'rating' => $this->findPrioritySetting(name: $prefix . '_sort_by_rating', type: 'rating'),
        ];
    }
    private function storeRatingThreshold($setting): float
    {
        if (! $setting || $setting === 'none') {
            return 0;
        }

        return (float) match ($setting) {
            'four_plus' => 4,
            'three_half_plus' => 3.5,
            'three_plus' => 3,
            'two_plus' => 2,
            default => 0,
        };
    }
    private function selectRatingAverage($query, string $alias, float $threshold = 0)
    {
        return $query->selectSub(function ($sub) use ($threshold) {
            $sub->selectRaw('AVG(reviews.rating)')
                ->from('reviews')
                ->join('items', 'items.id', '=', 'reviews.item_id')
                ->whereColumn('items.store_id', 'stores.id')
                ->groupBy('items.store_id')
                ->when($threshold > 0, fn ($q) => $q->havingRaw('AVG(reviews.rating) >= ?', [$threshold]));
        }, $alias);
    }
    private function storeListPayload(array $filters, array $paginate, array $spec): array
    {
        $query = Store::withOpen($filters['longitude'] ?? 0, $filters['latitude'] ?? 0)
            ->withCount($spec['with_count'])
            ->with(array_merge(['discount' => fn ($q) => $q->validate()], $spec['with'] ?? []));

        if (isset($spec['scope'])) {
            $spec['scope']($query);
        }

        $query->when(config('module.current_module_data'), function ($query) use ($filters) {
            $query->whereHas('zone.modules', fn ($q) => $q->where('modules.id', config('module.current_module_data')['id']))
                ->module(config('module.current_module_data')['id']);
            if (! config('module.current_module_data')['all_zone_service']) {
                $query->whereIn('zone_id', json_decode($filters['zone_id'], true));
            }
        })->Active()->type($filters['type'] ?? 'all');

        if (isset($spec['order'])) {
            $spec['order']($query);
        }

        if ($spec['personalise'] ?? false) {
            $query = $this->applyStorePersonalization($query, $filters['user_id'] ?? null);
        }

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        if ($spec['attach_categories'] ?? false) {
            $this->attachTopCategoryIds($paginator);
        }

        if ($spec['verified_seller'] ?? false) {
            $paginator->getCollection()->transform(function ($store) {
                $store['verified_seller'] = Helpers::get_verified_seller_status($store, $store?->storeConfig);

                return $store;
            });
        }

        return [
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'stores' => $paginator->items(),
        ];
    }
    private function withPaginator(array $payload, array $paginate): array
    {
        $payload['paginator'] = new LengthAwarePaginator(
            $payload['stores'] ?? $payload['data'] ?? [],
            (int) ($payload['total_size'] ?? 0),
            $this->pageSize($paginate),
            $this->pageNumber($paginate)
        );

        return $payload;
    }
    private function buildLatestQuery($zone_id, $limit = 50, $offset = 1, $type='all',$longitude=0,$latitude=0,$user_id=null)
    {
    $sortSettings = $this->storeSortSettings('latest_stores');

    $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns','reviews'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), fn ($query) => $this->scopeToCurrentModule($query, $zone_id))
            ->Active()
            ->type($type);

            if($sortSettings['default_status'] == '1'){
                $query = $this->applyStorePersonalization($query, $user_id);
                $query = $query->latest();
            } else{

                if($sortSettings['default_status'] != '1') {
                    $query = $this->applyClosedAndUnavailableSort($query, $sortSettings);

                    if($sortSettings['general'] == 'rating') {
                        $query = $this->selectRatingAverage($query, 'avg_r')->orderBy('avg_r', 'desc');
                    }elseif($sortSettings['general'] == 'review_count') {
                        $query = $query->orderByDesc('reviews_count');
                    }elseif($sortSettings['general'] == 'order_count') {
                        $query = $query->orderBy('orders_count', 'desc');
                    }elseif($sortSettings['general'] == 'latest_created') {
                        $query = $query->latest();
                    }elseif($sortSettings['general'] == 'first_created') {
                        $query = $query->oldest();
                    }elseif($sortSettings['general'] == 'a_to_z') {
                        $query = $query->orderBy('name');
                    }elseif($sortSettings['general'] == 'z_to_a') {
                        $query = $query->orderByDesc('name');
                    }
                }
            }

            $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return $this->paginatedStorePayload($paginator, $limit, $offset);
    }
    private function buildPopularQuery($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$user_id=null)
    {
        $sortSettings = $this->storeSortSettings('popular_store');

        $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), fn ($query) => $this->scopeToCurrentModule($query, $zone_id))
            ->type($type)
            ->withCount('reviews')
            ->withCount('orders')->Active();

            if($sortSettings['default_status'] == '1') {
                $query = $this->applyStorePersonalization($query, $user_id);
                $query = $query->orderBy('open', 'desc')
                        ->orderBy('distance')
                        ->orderBy('orders_count', 'desc');
            }else{
                $query = $this->applyClosedAndUnavailableSort($query, $sortSettings);

                $rating_threshold = $this->storeRatingThreshold($sortSettings['rating']);

                if($rating_threshold > 0 || $sortSettings['general'] == 'rating') {
                    $query = $this->selectRatingAverage($query, 'store_rating', $rating_threshold);

                    if($rating_threshold > 0) {
                        $query->having('store_rating', '>=', $rating_threshold);
                    }

                    if($sortSettings['general'] == 'rating') {
                        $query->orderBy('store_rating', 'desc');
                    }
                } elseif($sortSettings['general'] == 'review_count') {
                    $query = $query->orderByDesc('reviews_count');
                } elseif($sortSettings['general'] == 'order_count') {
                    $query = $query->orderBy('orders_count', 'desc');
                } elseif($sortSettings['general'] == 'nearest_first') {
                    $query = $query->orderBy('distance');
                }
            }

        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return $this->paginatedStorePayload($paginator, $limit, $offset);
    }
    private function buildRecommendedQuery($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$user_id=null)
    {
        $sortSettings = $this->storeSortSettings('recommended_store');

        $shuffle=null;
        if(config('module.current_module_data')){
            $shuffle = app(DataSettingService::class)->findValueByKeyAndType('shuffle_recommended_store', config('module.current_module_data')['id']);
        }
        $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->wherehas('storeConfig', function ($q){
                $q->where(['is_recommended_deleted'=> 0 , 'is_recommended' => 1]);
            })
            ->when(config('module.current_module_data'), fn ($query) => $this->scopeToCurrentModule($query, $zone_id))
            ->type($type)
            ->when($shuffle == 1 && $sortSettings['default_status'] == 1, function($q){
                $q->inRandomOrder();
            })
            ->withCount('reviews')
            ->withCount('orders')->Active();

        if($sortSettings['default_status'] == '1') {
            $query = $this->applyStorePersonalization($query, $user_id);
        }else{

            $query = $this->applyClosedAndUnavailableSort($query, $sortSettings);

            if($sortSettings['rating'] && ($sortSettings['rating'] != 'none')){
                $rating_count = $this->storeRatingThreshold($sortSettings['rating']);

                $query = $this->selectRatingAverage($query, 'avg_r', $rating_count)
                    ->having('avg_r', '>=', $rating_count);
            }

            if($sortSettings['general'] == 'rating') {
                $query = $this->selectRatingAverage($query, 'avg_rat')->orderBy('avg_rat', 'desc');
            }elseif($sortSettings['general'] == 'review_count') {
                $query = $query->orderByDesc('reviews_count');
            }elseif($sortSettings['general'] == 'order_count') {
                $query = $query->orderBy('orders_count', 'desc');
            }

        }
        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return $this->paginatedStorePayload($paginator, $limit, $offset);
    }
    private function buildTopOfferQuery($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0 , $name = null ,$sort = null, $halal = null, $user_id = null)
    {

        $sortSettings = $this->storeSortSettings('top_offer_near_me_stores');

        $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns','reviews'])
            ->with('discount')
            ->whereHas('discount' , function($q){
                $q->validate();
            })
            ->when(config('module.current_module_data'), fn ($query) => $this->scopeToCurrentModule($query, $zone_id))
            ->type($type)->Active()->Halal($halal);
            if($name){
                $key = explode(' ', $name);
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                    $relationships = [
                        'translations' => 'value',
                    ];
                    return  $q->applyRelationShipSearch(relationships:$relationships ,searchParameter:$key);
                }) ->orderByRaw("CASE WHEN name = ? THEN 1 WHEN name LIKE ? THEN 2 ELSE 3 END, LENGTH(name) ASC, name ASC ", [$name, "%{$name}%"]);
            }

            // The temporarily-off / currently-closed rules are part of the admin's priority
            // setup, so they stay in force even when the customer picks their own sort order.
            if($sortSettings['default_status'] != 1){
                $query = $this->applyClosedAndUnavailableSort($query, $sortSettings);
            }

            if($sort)
            {
                $query->orderBy('name',$sort);

            } elseif($sortSettings['default_status'] == 1){
                $query = $this->applyStorePersonalization($query, $user_id);
                $query= $query->orderByDesc('open')->orderby('distance');
            } else {
                    if($sortSettings['general'] == 'rating') {
                        $query = $this->selectRatingAverage($query, 'avg_rat')->orderBy('avg_rat', 'desc');
                    }elseif($sortSettings['general'] == 'review_count') {
                        $query = $query->orderByDesc('reviews_count');
                    }elseif($sortSettings['general'] == 'asc_discount') {

                        // Aliased away from `discount` so the sub-select does not shadow the
                        // eager-loaded `discount` relation on the Store model.
                        $query = $query->selectSub(function ($query) {
                            $query->selectRaw('MAX(discounts.discount)')
                                ->from('discounts')
                                ->whereColumn('discounts.store_id', 'stores.id');
                        }, 'max_store_discount')
                        ->orderBy('max_store_discount', 'asc');

                    }elseif($sortSettings['general'] == 'desc_discount') {
                        $query = $query->selectSub(function ($query) {
                            $query->selectRaw('MAX(discounts.discount)')
                                ->from('discounts')
                                ->whereColumn('discounts.store_id', 'stores.id');
                        }, 'max_store_discount')
                        ->orderBy('max_store_discount', 'desc');
                    }
            }

        $paginator= $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return $this->paginatedStorePayload($paginator, $limit, $offset);
    }
    private function buildQuickDeliveryQuery($zone_id, $limit = 10, $offset = 1, $type = 'all', $longitude = 0, $latitude = 0, $isAd = false, ?array $filter = null, $withItems = false, $user_id = null)
    {
        $currentModule = config('module.current_module_data');
        $advertised_store_ids = app(AdvertisementService::class)->getValidStoreIds([
            'module_id' => $currentModule ? $currentModule['id'] : null,
            'zone_ids' => json_decode($zone_id, true),
            'apply_zones' => ! $currentModule || ! $currentModule['all_zone_service'],
        ]);

        $query = Store::withStorage()->WithOpenWithDeliveryTime($longitude ?? 0, $latitude ?? 0)
            ->withCount(['items', 'campaigns', 'reviews'])
            ->with(['discount' => function ($q) {
                return $q->validate();
            }])
            ->selectSub(function ($q) {
                $q->selectRaw('AVG(reviews.rating)')
                    ->from('reviews')
                    ->join('items', 'items.id', '=', 'reviews.item_id')
                    ->whereColumn('items.store_id', 'stores.id')
                    ->groupBy('items.store_id');
            }, 'avg_r')
            ->when(config('module.current_module_data'), function ($query) use ($zone_id) {
                $query->whereHas('zone.modules', function ($query) {
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if (!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            }, function ($query) use ($zone_id) {
                $query->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->Active()
            ->type($type)
            ->has('items');

        if ($filter) {
            $query = $query->applyStoreFilter($filter);
        }

        if ($isAd && !empty($advertised_store_ids)) {
            $placeholders = implode(',', array_fill(0, count($advertised_store_ids), '?'));
            $query->orderByRaw("CASE WHEN stores.id IN ($placeholders) THEN 0 ELSE 1 END", $advertised_store_ids);
        }

        $query = $query->orderBy('open', 'desc');

        $query = $this->applyStorePersonalization($query, $user_id);

        $stores = $query->orderBy('min_delivery_time', 'asc')
            ->orderBy('distance', 'asc')
            ->limit($limit ?? 10)
            ->offset((($offset ?? 1) - 1) * ($limit ?? 10))
            ->get();

        $stores->loadMissing(['module', 'schedules']);

        $new_store_days = (int) (app(BusinessSettingService::class)->value('new_store_tag_days') ?? 30);
        $new_threshold = now()->subDays($new_store_days);

        $top_items_by_store = $withItems && $stores->isNotEmpty()
            ? $this->topItemModels($stores->pluck('id')->all(), 5)
            : collect();

        $categories_by_store = $stores->isNotEmpty()
            ? $this->topCategories($stores->pluck('id')->all(), 5)
            : collect();

        $offers_by_store = $this->offersByStore($stores->pluck('id')->all());

        $formatted = $stores->map(function ($store) use ($advertised_store_ids, $new_threshold, $withItems, $top_items_by_store, $categories_by_store, $offers_by_store) {
            $top_items = null;
            if ($withItems) {
                $top_items = ($top_items_by_store[$store->id] ?? collect())->map(function ($item) {
                    return [
                        'id' => (int) $item->id,
                        'name' => $item->name,
                        'image_full_url' => $item->image_full_url,
                        'price' => (float) $item->price,
                        'discount' => (float) $item->discount,
                        'discount_type' => $item->discount_type,
                        'order_count' => (int) $item->order_count,
                        'avg_rating' => (float) ($item->avg_rating ?? 0),
                    ];
                })->values()->all();
            }

            return (new StoreListResource($store))->withOptions([
                'advertised_store_ids' => $advertised_store_ids,
                'new_threshold' => $new_threshold,
                'top_items' => $top_items,
                'with_items' => $withItems,
                'offers' => $offers_by_store[$store->id] ?? [],
                'category_data' => $categories_by_store[$store->id] ?? [],
            ])->render();
        })->values()->all();

        return [
            'total_size' => count($formatted),
            'limit' => $limit ?? 10,
            'offset' => $offset ?? 1,
            'stores' => $formatted,
        ];
    }
    private function buildExclusiveDealsQuery($zone_id, $moduleId = null, $longitude = 0, $latitude = 0, int $limit = 25, $offset = 1, ?array $filter = null, $user_id = null)
    {
        $zones = $zone_id ? (json_decode($zone_id, true) ?: []) : [];
        $today = date('Y-m-d');
        $now = date('H:i:s');

        $advertised_store_ids = app(AdvertisementService::class)->getValidStoreIds([
            'module_id' => is_numeric($moduleId) ? $moduleId : null,
            'zone_ids' => $zones,
            'apply_zones' => ! empty($zones),
        ]);

        $query = Store::withStorage()->WithOpenWithDeliveryTime($longitude ?? 0, $latitude ?? 0)
            ->Active()
            ->when(is_numeric($moduleId), fn ($q) => $q->where('module_id', $moduleId))
            ->when(! empty($zones), fn ($q) => $q->whereIn('zone_id', $zones))
            ->whereHas('discount', fn ($q) => $q->validate())
            ->with(['discount' => fn ($q) => $q->validate(), 'schedules'])
            ->withCount('reviews')
            ->selectSub(function ($q) {
                $q->selectRaw('AVG(reviews.rating)')
                    ->from('reviews')
                    ->join('items', 'items.id', '=', 'reviews.item_id')
                    ->whereColumn('items.store_id', 'stores.id')
                    ->groupBy('items.store_id');
            }, 'avg_r')
            ->selectSub(function ($q) use ($today, $now) {
                $q->select('discount')
                    ->from('discounts')
                    ->whereColumn('discounts.store_id', 'stores.id')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->whereTime('start_time', '<=', $now)
                    ->whereTime('end_time', '>=', $now)
                    ->orderByDesc('discount')
                    ->limit(1);
            }, 'store_discount_value');

        if ($filter) {
            $query = $query->applyStoreFilter($filter);
        }

        $query = $query->orderByDesc('store_discount_value');

        $query = $this->applyStorePersonalization($query, $user_id);

        $stores = $query->limit(max(1, $limit))
            ->offset((($offset ?? 1) - 1) * ($limit ?? 10))
            ->get();

        $stores->loadMissing(['module', 'schedules']);

        $new_store_days = (int) (app(BusinessSettingService::class)->value('new_store_tag_days') ?? 30);
        $new_threshold = now()->subDays($new_store_days);

        $categories_by_store = $stores->isNotEmpty()
            ? $this->topCategories($stores->pluck('id')->all(), 5)
            : collect();

        $offers_by_store = $this->offersByStore($stores->pluck('id')->all());

        $formatted = $stores->map(fn ($s) => (new StoreListResource($s))->withOptions([
            'advertised_store_ids' => $advertised_store_ids,
            'new_threshold' => $new_threshold,
            'offers' => $offers_by_store[$s->id] ?? [],
            'category_data' => $categories_by_store[$s->id] ?? [],
        ])->render())->values()->all();

        return [
            'total_size' => count($formatted),
            'limit' => $limit,
            'stores' => $formatted,
        ];
    }
    private function attachMainCategories($stores)
    {
        $store_ids = collect($stores)->pluck('id')->filter()->unique()->values()->all();
        if (empty($store_ids)) {
            return $stores;
        }

        $items = app(ItemService::class)->getActiveForStores($store_ids);

        $store_category_orders = [];
        $all_ids = [];
        foreach ($items as $item) {
            foreach (Helpers::decodeJsonToArray($item['category_ids']) as $value) {
                if ((int) data_get($value, 'position') === 1) {
                    $category_id = (int) data_get($value, 'id');
                    $store_category_orders[$item['store_id']][$category_id] = ($store_category_orders[$item['store_id']][$category_id] ?? 0) + (int) $item['order_count'];
                    $all_ids[$category_id] = true;
                }
            }
        }

        $category_names = app(CategoryService::class)->getNamesByIds(array_keys($all_ids));

        foreach ($stores as $store) {
            $order_counts = $store_category_orders[$store['id']] ?? [];
            arsort($order_counts);
            $categories = [];
            foreach (array_slice(array_keys($order_counts), 0, 5) as $category_id) {
                $categories[] = [
                    'id' => $category_id,
                    'name' => $category_names[$category_id] ?? 'NA',
                ];
            }
            $store['categories'] = $categories;
        }

        return $stores;
    }
    private function buildAllStoresQuery( $zone_id, $filter_data, $type, $store_type, $limit = 10, $offset = 1, $featured=false,$longitude=0,$latitude=0,$filter=null,$rating_count=null, ?array $store_filter = null, $user_id = null, $module_id = null)
    {

        $all_stores_default_status = app(BusinessSettingService::class)->value('all_stores_default_status') ?? 1;
        $all_stores_sort_by_general = $this->findPrioritySetting(name: 'all_stores_sort_by_general', type: 'general');
        $all_stores_sort_by_unavailable = $this->findPrioritySetting(name: 'all_stores_sort_by_unavailable', type: 'unavailable');
        $all_stores_sort_by_temp_closed = $this->findPrioritySetting(name: 'all_stores_sort_by_temp_closed', type: 'temp_closed');

        $query = Store::type($type)->
        WithOpenWithDeliveryTime($longitude??0,$latitude??0)
            ->when($all_stores_default_status == '1', fn ($q) => $q->withAdExists())
            ->withCount(['items','campaigns','reviews','orders'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->whereHas('module',function($query){
                return  $query->active();
            })
            ->Active();
        if(config('module.current_module_data')) {
            $query = $query->whereHas('zone.modules', function($query){
                return  $query->where('modules.id', config('module.current_module_data')['id']);
            })->module(config('module.current_module_data')['id'])
                ->when(!config('module.current_module_data')['all_zone_service'], function($query)use($zone_id){
                    return  $query->whereIn('zone_id', json_decode($zone_id,true));
                });
        } else {
            $query = $query->whereIn('zone_id', json_decode($zone_id,true));
            $query = $query->when(is_numeric($module_id), fn ($q) => $q->where('module_id', $module_id));
        }

            if($all_stores_default_status != '1') {
                if($all_stores_sort_by_temp_closed == 'remove'){
                    $query = $query->where('active', '>', 0);
                }elseif($all_stores_sort_by_temp_closed == 'last'){
                    $query = $query->orderByDesc('active');
                }

                if($all_stores_sort_by_unavailable == 'remove'){
                    $query = $query->having('open', '>', 0);
                }elseif($all_stores_sort_by_unavailable == 'last'){
                    $query = $query->orderBy('open', 'desc');
                }

                if($all_stores_sort_by_general == 'rating') {
                    // Aliased apart from the `avg_r` used by the customer rating filter below:
                    // two sub-selects sharing one alias make MySQL reject the query (1060).
                    $query = $query->selectSub(function ($query) {
                        $query->selectRaw('AVG(reviews.rating)')
                            ->from('reviews')
                            ->join('items', 'items.id', '=', 'reviews.item_id')
                            ->whereColumn('items.store_id', 'stores.id')
                            ->groupBy('items.store_id');
                    }, 'avg_rat')->orderBy('avg_rat', 'desc');
                }elseif($all_stores_sort_by_general == 'review_count') {
                    $query = $query->orderByDesc('reviews_count');
                }elseif($all_stores_sort_by_general == 'order_count') {
                    $query = $query->orderBy('orders_count', 'desc');
                }elseif($all_stores_sort_by_general == 'latest_created') {
                    $query = $query->latest();
                }elseif($all_stores_sort_by_general == 'first_created') {
                    $query = $query->oldest();
                }elseif($all_stores_sort_by_general == 'a_to_z') {
                    $query = $query->orderBy('name');
                }elseif($all_stores_sort_by_general == 'z_to_a') {
                    $query = $query->orderByDesc('name');
                }
            }
            $query = $query->when($filter && in_array('free_delivery',$filter),function ($qurey){
                return $qurey->where('free_delivery',1);
            });
            $query = $query->when($filter && in_array('coupon', $filter), function ($query) {
                return $query->has('activeCoupons');
            });
            $query = $query->when($store_type == 'all' && $filter && !in_array('fast_delivery',$filter), function($q){
                return $q->orderBy('open', 'desc')->orderBy('distance');
            });
            $query = $query->when($filter && (in_array('currently_open', $filter) || in_array('open_now', $filter)), function ($query) {
                return $query->having('open', '>', 0);
            });
            $query = $query->when($filter && in_array('rx_accepted', $filter), function ($query) {
                return $query->whereHas('items.pharmacy_item_details', function ($q) {
                    $q->where('is_prescription_required', 1);
                });
            });
            $query = $query->when($store_type == 'newly_joined', function($q){
                return $q->latest();
            });
            $query = $query->when($rating_count, function($query) use ($rating_count){
                return  $query->selectSub(function ($query) use ($rating_count){
                    return $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            });
            $query = $query->when(($filter && in_array('top_rated',$filter) ) || $store_type == 'top_rated' ,function ($qurey){
                return $qurey->quickActionFilter('top_rated');
            });
            $query = $query->when(($filter && in_array('popular',$filter)) || $store_type == 'popular'  ,function ($qurey){
                // stores.total_order -- see the popular branch above. This path can run
                // alongside the model-level popular filter, and two withCount('orders')
                // calls failed the request with "Duplicate column name orders_count".
                return  $qurey->orderBy('total_order', 'desc');
            });
            $query = $query->when($filter && in_array('discounted',$filter),function ($qurey){
                return $qurey->where(function ($query) {
                    return $query->whereHas('items', function ($q) {
                        $q->Discounted();
                    });
                });
            });
            $query = $query->when($filter && in_array('open',$filter),function ($qurey){
                return $qurey->orderBy('open', 'desc');
            });
            $query = $query->when(($filter && in_array('nearby',$filter)) || $store_type == 'nearby'  ,function ($qurey){
                return  $qurey->quickActionFilter('nearby');
            });
            $query = $query->when($filter_data=='delivery', function($q){
                return $q->delivery();
            });

            $query = $query->when($filter_data=='take_away', function($q){
                return $q->takeaway();
            });
            $query = $query->when($featured, function($query){
                return $query->featured();
            });
            $query = $query->when($filter && in_array('fast_delivery',$filter) , function($q) {
                return $q->orderBy('open', 'desc')->orderBy('min_delivery_time');
            });

            if($all_stores_default_status == '1') {
                $query = $query->orderByDesc('advertisements_exists');

                $query = $this->applyStorePersonalization($query, $user_id, $filter);
                $query = $query->orderBy('open', 'desc');
            }

            if ($store_filter) {
                $query = $query->applyStoreFilter($store_filter);
            }

        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        $store_ids = collect($paginator->items())->pluck('id')->all();
        $top_items_by_store = ! empty($store_ids)
            ? $this->topItemModels($store_ids, 5)
            : collect();

        $this->attachTopCategoryIds($paginator);

        $discounted_store_ids = $this->discountedStoreIds($paginator);

        $paginator->each(function ($store) use ($top_items_by_store, $discounted_store_ids) {
            $items = $top_items_by_store[(int) $store->id] ?? collect();
            $store->top_items = $items->map(function ($item) use ($store) {
                $discountData = Helpers::product_discount_calculate($item, $item->price, $store, true);
                $price = (float) $item->price;
                $discounted = max(0, round($price - (float) ($discountData['discount_amount'] ?? 0), 2));

                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'image_full_url' => $item->image_full_url,
                    'price' => $price,
                    'discounted_price' => $discounted,
                    'discount' => (float) ($discountData['discount_percentage'] ?? $item->discount),
                    'discount_type' => $discountData['original_discount_type'] ?? $item->discount_type,
                    'order_count' => (int) $item->order_count,
                    'avg_rating' => (float) ($item->avg_rating ?? 0),
                ];
            })->values()->all();

            $store->discount_status = $discounted_store_ids->has((int) $store->id);
            unset($store['items']);
        });
        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'stores' => $paginator->items()
        ];
    }
    private function discountedStoreIds($paginator)
    {
        $store_ids = collect($paginator->items())->pluck('id')->filter()->unique()->values()->all();

        if ($store_ids === []) {
            return collect();
        }

        return DB::table('items')
            ->whereIn('store_id', $store_ids)
            ->where('discount', '>', 0)
            ->distinct()
            ->pluck('store_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
    }
    private function attachTopCategoryIds($paginator): void
    {
        $store_ids = collect($paginator->items())->pluck('id')->all();
        if (empty($store_ids)) {
            return;
        }

        $categoriesByStore = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('order_details', 'order_details.item_id', '=', 'items.id')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->selectRaw('
                items.store_id as store_id,
                CAST(categories.id AS UNSIGNED) as id,
                categories.parent_id,
                categories.name,
                COUNT(order_details.id) as order_count
            ')
            ->whereIn('items.store_id', $store_ids)
            ->where('categories.status', 1)
            ->whereNotIn('orders.order_status', ['failed', 'canceled'])
            ->groupBy('items.store_id', 'id', 'categories.parent_id', 'categories.name')
            ->orderByDesc('order_count')
            ->get()
            ->groupBy('store_id');

        $paginator->each(function ($store) use ($categoriesByStore) {
            $mergedIds = [];
            $mergedCategoryNames = [];

            foreach (($categoriesByStore[$store->id] ?? collect())->take(5) as $item) {
                if ($item->id != 0) {
                    $mergedIds[] = $item->id;
                    $mergedCategoryNames[] = $item->name;
                }
                if ($item->parent_id != 0) {
                    $mergedIds[] = $item->parent_id;
                    $mergedCategoryNames[] = $item->name;
                }
            }

            $store->category_ids = array_values(array_unique($mergedIds));
            $store->category_names = array_values(array_unique($mergedCategoryNames));
        });
    }
    private function buildDiscountedQuery($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$filter=null,$rating_count=null, ?array $store_filter = null, $user_id = null)
    {
        $query = Store::withStorage()->WithOpenWithDeliveryTime($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                return   $query->whereHas('zone.modules', function($query){
                    return $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    return  $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->where(function ($query) {
                return  $query->whereHas('items', function ($q) {
                    $q->Discounted();
                });
            })
            ->Active()
            ->type($type)
            ->when($filter && in_array('free_delivery',$filter),function ($qurey){
                return $qurey->where('free_delivery',1);
            })
            ->when($filter && in_array('coupon',$filter),function ($qurey){
                return $qurey->has('activeCoupons');
            })
            ->when($rating_count, function($query) use ($rating_count){
                return  $query->selectSub(function ($query) use ($rating_count){
                    return  $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            })
            ->when($filter && in_array('top_rated',$filter),function ($qurey){
                return $qurey->whereNotNull('rating')->whereRaw("LENGTH(rating) > 0");
            })
            ->when($filter && in_array('currently_open',$filter),function ($qurey){
                return $qurey->having('open', '>', 0);
            })
            ->orderBy('open', 'desc')
            ->when($filter && in_array('popular',$filter),function ($qurey){
                // stores.total_order (maintained in PlaceNewOrderTrait) rather than
                // withCount('orders'), which counted the orders table per candidate store
                // before the limit could apply.
                return $qurey->orderBy('total_order', 'desc');
            })
            ->when(($filter && in_array('nearby',$filter))   ,function ($qurey){
                return  $qurey->orderBy('distance');
            })
            ->when($filter && in_array('fast_delivery',$filter),function ($qurey){
                return $qurey->orderBy('min_delivery_time');
            })
            ->when($store_filter, fn ($q) => $q->applyStoreFilter($store_filter));

        $query = $this->applyStorePersonalization($query, $user_id);

        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        $discounted_store_ids = $this->discountedStoreIds($paginator);

        $store_ids = collect($paginator->items())->pluck('id')->filter()->unique()->values()->all();
        $categories_by_store = empty($store_ids)
            ? collect()
            : app(ItemService::class)->getStoreCategoryIdRows($store_ids);

        $paginator->each(function ($store) use ($discounted_store_ids, $categories_by_store) {
            $mergedIds = [];

            foreach (($categories_by_store[$store->id] ?? collect()) as $item) {
                if ($item->id != 0) {
                    $mergedIds[] = (int) $item->id;
                }
                if ($item->parent_id != 0) {
                    $mergedIds[] = (int) $item->parent_id;
                }
            }

            $store->category_ids = array_values(array_unique($mergedIds));

            $store->discount_status = $discounted_store_ids->has((int) $store->id);
            unset($store['items']);
        });

        return $this->paginatedStorePayload($paginator, $limit, $offset);
    }
    private function fetchStoreDetail($store_id,$longitude=0,$latitude=0)
    {
        return Store::withOpen($longitude??0,$latitude??0)->with(['discount'=>function($q){
            return $q->validate();
        }, 'campaigns', 'schedules','activeCoupons','store_sub'])
            ->withCount(['items','campaigns','reviews_comments'])
            ->when(config('module.current_module_data'), function($query){
                $query->module(config('module.current_module_data')['id']);
            })
            ->tap(fn ($query) => $this->applyIdOrSlug($query, $store_id))
            ->first();
    }
    private function buildSearchQuery($name, $zone_id, $category_id= null,$limit = 10, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$filter=null,$rating_count=null,$category_ids=null, ?array $store_filter = null, $user_id = null, $request = null, $additional_data = [])
    {
        $key = explode(' ', $name);
        if (empty($additional_data['filter_by']) && is_array($filter) && !empty($filter)) {
            $additional_data['filter_by'] = $filter;
        }
        $paginator = Store::withStorage()->WithOpenWithDeliveryTime($longitude??0,$latitude??0)
        ->whereHas('zone.modules', function($query){
            return $query->where('modules.id', config('module.current_module_data')['id']);
        })
        ->withCount(['items','campaigns'])->with(['discount'=>function($q){
            return $q->validate();
        }])->weekday()
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                return   $query->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    return   $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->when($category_id, function($query)use($category_id){
                return $query->whereHas('items.category', function($q)use($category_id){
                    return $q->whereId($category_id)->orWhere('parent_id', $category_id);
                });
            })
            ->when($category_ids && is_array($category_ids), function($query)use($category_ids){
                return $query->whereHas('items.category', function($q)use($category_ids){
                    return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
                });
            })
            ->active()
            ->when($rating_count, function($query) use ($rating_count){
                return $query->selectSub(function ($query) use ($rating_count){
                    return  $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            })
            ->orderBy('open', 'desc')
            ->type($type)
            ->applyFilters($additional_data)
            ->applySorting($additional_data['sort_by'] ?? 'default')
            ->applyRating($request)
            ->applyPriceRange($request)
            ->search(keywords: $key, relations: [
                'translations' => 'value',
                'items' => 'name',
                'items.translations' => 'value',
                'items.tags' => 'tag',
                'items.category' => 'name',
                'items.category.parent' => 'name',
                'items.nutritions' => 'nutrition',
                'items.allergies' => 'allergy',
                'items.generic' => 'generic_name',
                'items.ecommerce_item_details.brand' => 'name',
                'items.pharmacy_item_details.common_condition' => 'name',
            ]);

            $paginator = $this->applyStorePersonalization($paginator, $user_id, $filter);
            $paginator = $paginator->paginate($limit, ['*'], 'page', $offset);

        $store_ids = $paginator->getCollection()->pluck('id')->all();

        $top_items_by_store = $this->topItemRows($store_ids, 5);

        $discount_store_ids = empty($store_ids) ? [] : DB::table('items')
            ->whereIn('store_id', $store_ids)
            ->where('discount', '>', 0)
            ->distinct()
            ->pluck('store_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $categories_by_store = empty($store_ids) ? collect() : DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->selectRaw('
                items.store_id as store_id,
                CAST(categories.id AS UNSIGNED) as id,
                categories.parent_id
            ')
            ->whereIn('items.store_id', $store_ids)
            ->where('categories.status', 1)
            ->groupBy('store_id', 'id', 'categories.parent_id')
            ->get()
            ->groupBy('store_id');

        $paginator->each(function ($store) use ($top_items_by_store, $discount_store_ids, $categories_by_store) {
            $mergedIds = [];

            foreach (($categories_by_store[$store->id] ?? collect()) as $row) {
                if ($row->id != 0) {
                    $mergedIds[] = (int) $row->id;
                }
                if ($row->parent_id != 0) {
                    $mergedIds[] = (int) $row->parent_id;
                }
            }

            $store->category_ids = array_values(array_unique($mergedIds));
            $store->discount_status = in_array((int) $store->id, $discount_store_ids, true);
            $store->top_items = $top_items_by_store[$store->id] ?? [];
            unset($store['items']);
        });

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'stores' => $paginator->items()
        ];
    }
    private function attachStoreCategoryIds($paginator): void
    {
        $storeIds = collect($paginator->items())->pluck('id')->filter()->unique()->values()->all();

        if (empty($storeIds)) {
            return;
        }

        $rows = app(ItemService::class)->getStoreCategoryIdRows($storeIds);

        foreach ($paginator as $store) {
            $mergedIds = [];

            foreach ($rows[$store->id] ?? [] as $row) {
                if ($row->id != 0) {
                    $mergedIds[] = (int) $row->id;
                }
                if ($row->parent_id != 0) {
                    $mergedIds[] = (int) $row->parent_id;
                }
            }

            $store->category_ids = array_values(array_unique($mergedIds));
            $store->discount_status = ! empty($store->items->where('discount', '>', 0));
            unset($store['items']);
        }
    }

    public function getNamesByIds(mixed $stores): string
    {
        $names = is_array($stores)
            ? Store::whereIn('id', $stores)->pluck('name')->toArray()
            : Store::where('id', $stores)->pluck('name')->toArray();

        return implode(', ', $names);
    }

    public function getVerifiedSellerEligibleStores(bool $countOnly = false, ?string $moduleId = null): mixed
    {
        $config = config('verified_seller.stores', []);
        $minimumOrders = (int) ($config['minimum_total_orders'] ?? 10);
        $minimumRating = (float) ($config['minimum_avg_rating'] ?? 2);
        $minimumSuccessRate = (float) ($config['minimum_success_rate'] ?? 40);
        $minimumAccountAgeMonths = (int) ($config['minimum_account_age_months'] ?? 3);
        $context = ['stores', app()->getLocale(), $countOnly, $moduleId ?? 'all', $minimumOrders, $minimumRating, $minimumSuccessRate, $minimumAccountAgeMonths];

        return ApiCache::remember('verified_seller', $context, function () use ($countOnly, $moduleId) {
            $config = config('verified_seller.stores', []);
            $minimumOrders = (int) ($config['minimum_total_orders'] ?? 10);
            $minimumRating = (float) ($config['minimum_avg_rating'] ?? 2);
            $minimumSuccessRate = (float) ($config['minimum_success_rate'] ?? 40);
            $minimumAccountAgeMonths = (int) ($config['minimum_account_age_months'] ?? 3);

            $orderStats = DB::table('orders')
                ->selectRaw('store_id, COUNT(*) as total_orders, SUM(CASE WHEN order_status = "delivered" THEN 1 ELSE 0 END) as delivered_orders, SUM(CASE WHEN order_status = "canceled" THEN 1 ELSE 0 END) as canceled_orders')
                ->groupBy('store_id');

            $moduleType = $moduleId ? app(ModuleService::class)->findTypeById($moduleId) : null;
            if ($moduleType === 'service' && service_addon_active()) {
                $reviewStats = DB::table('service_reviews')
                    ->where('status', 1)
                    ->selectRaw('store_id, COALESCE(AVG(rating), 0) as avg_rating')
                    ->groupBy('store_id');
            } else {
                $reviewStats = DB::table('reviews')
                    ->join('items', 'items.id', '=', 'reviews.item_id')
                    ->where('reviews.status', 1)
                    ->selectRaw('items.store_id, COALESCE(AVG(reviews.rating), 0) as avg_rating')
                    ->groupBy('items.store_id');
            }

            $stores = Store::withoutGlobalScopes()
                ->select('stores.id', 'stores.name', 'stores.logo', 'stores.created_at')
                ->when(! $countOnly, function ($query) {
                    $query->with(['storage' => function ($storageQuery) {
                        $storageQuery->where('key', 'logo')->select('id', 'data_type', 'data_id', 'key', 'value');
                    }, 'translations' => function ($translationQuery) {
                        $translationQuery->where('locale', app()->getLocale());
                    }]);
                })
                ->addSelect(DB::raw('COALESCE(order_stats.total_orders, 0) as total_orders'))
                ->addSelect(DB::raw('COALESCE(review_stats.avg_rating, 0) as avg_rating'))
                ->leftJoin('store_configs', 'store_configs.store_id', '=', 'stores.id')
                ->leftJoin('modules', 'modules.id', '=', 'stores.module_id')
                ->joinSub($orderStats, 'order_stats', function ($join) {
                    $join->on('order_stats.store_id', '=', 'stores.id');
                })
                ->joinSub($reviewStats, 'review_stats', function ($join) {
                    $join->on('review_stats.store_id', '=', 'stores.id');
                })
                ->when($moduleId, function ($query) use ($moduleId) {
                    $query->where('modules.id', $moduleId);
                })
                ->where(function ($query) {
                    $query->whereNull('store_configs.id')
                        ->orWhere('store_configs.verified_seller', '!=', 1);
                })
                ->where('stores.created_at', '<=', now()->subMonths($minimumAccountAgeMonths))
                ->whereRaw('COALESCE(order_stats.total_orders, 0) >= ?', [$minimumOrders])
                ->whereRaw('COALESCE(review_stats.avg_rating, 0) >= ?', [$minimumRating])
                ->whereRaw('COALESCE((order_stats.delivered_orders / NULLIF(order_stats.delivered_orders + order_stats.canceled_orders, 0)) * 100, 0) >= ?', [$minimumSuccessRate]);

            if ($countOnly) {
                return $stores->count('stores.id');
            }

            return $stores->get()->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'logo_full_url' => Helpers::get_full_url('store', $store->logo, $store->storage->pluck('value')->first() ?? 'public'),
                    'total_orders' => (int) $store->total_orders,
                    'avg_rating' => (float) $store->avg_rating,
                ];
            });
        });
    }

    public function getVerifiedSellerEligibleProviders(bool $countOnly = false, ?string $moduleId = null): mixed
    {
        $config = config('verified_seller.providers', []);
        $minimumTrips = (int) ($config['minimum_total_trips'] ?? 10);
        $minimumRating = (float) ($config['minimum_avg_rating'] ?? 2);
        $minimumSuccessRate = (float) ($config['minimum_success_rate'] ?? 40);
        $minimumAccountAgeMonths = (int) ($config['minimum_account_age_months'] ?? 3);
        $context = ['providers', app()->getLocale(), $countOnly, $moduleId ?? 'all', $minimumTrips, $minimumRating, $minimumSuccessRate, $minimumAccountAgeMonths];

        return ApiCache::remember('verified_seller', $context, function () use ($countOnly, $moduleId) {
            $config = config('verified_seller.providers', []);
            $minimumTrips = (int) ($config['minimum_total_trips'] ?? 10);
            $minimumRating = (float) ($config['minimum_avg_rating'] ?? 2);
            $minimumSuccessRate = (float) ($config['minimum_success_rate'] ?? 40);
            $minimumAccountAgeMonths = (int) ($config['minimum_account_age_months'] ?? 3);

            $tripStats = DB::table('trips')
                ->selectRaw('provider_id, COUNT(*) as total_trips, SUM(CASE WHEN trip_status = "completed" THEN 1 ELSE 0 END) as completed_trips, SUM(CASE WHEN trip_status = "canceled" THEN 1 ELSE 0 END) as canceled_trips')
                ->when($moduleId, function ($query) use ($moduleId) {
                    $query->where('module_id', $moduleId);
                })
                ->groupBy('provider_id');

            $reviewStats = DB::table('vehicle_reviews')
                ->where('status', 1)
                ->selectRaw('provider_id, COALESCE(AVG(rating), 0) as avg_rating')
                ->when($moduleId, function ($query) use ($moduleId) {
                    $query->where('module_id', $moduleId);
                })
                ->groupBy('provider_id');

            $stores = Store::withoutGlobalScopes()
                ->where('stores.status', 1)
                ->select('stores.id', 'stores.name', 'stores.logo', 'stores.created_at')
                ->when(! $countOnly, function ($query) {
                    $query->with(['storage' => function ($storageQuery) {
                        $storageQuery->where('key', 'logo')->select('id', 'data_type', 'data_id', 'key', 'value');
                    }]);
                })
                ->addSelect(DB::raw('COALESCE(trip_stats.total_trips, 0) as total_trips'))
                ->addSelect(DB::raw('COALESCE(review_stats.avg_rating, 0) as avg_rating'))
                ->leftJoin('store_configs', 'store_configs.store_id', '=', 'stores.id')
                ->leftJoin('modules', 'modules.id', '=', 'stores.module_id')
                ->joinSub($tripStats, 'trip_stats', function ($join) {
                    $join->on('trip_stats.provider_id', '=', 'stores.id');
                })
                ->joinSub($reviewStats, 'review_stats', function ($join) {
                    $join->on('review_stats.provider_id', '=', 'stores.id');
                })
                ->when($moduleId, function ($query) use ($moduleId) {
                    $query->where('modules.id', $moduleId);
                })
                ->where(function ($query) {
                    $query->whereNull('store_configs.id')
                        ->orWhere('store_configs.verified_seller', '!=', 1);
                })
                ->where('stores.created_at', '<=', now()->subMonths($minimumAccountAgeMonths))
                ->whereRaw('COALESCE(trip_stats.total_trips, 0) >= ?', [$minimumTrips])
                ->whereRaw('COALESCE(review_stats.avg_rating, 0) >= ?', [$minimumRating])
                ->whereRaw('COALESCE((trip_stats.completed_trips / NULLIF(trip_stats.completed_trips + trip_stats.canceled_trips, 0)) * 100, 0) >= ?', [$minimumSuccessRate]);

            if ($countOnly) {
                return $stores->count('stores.id');
            }

            return $stores->get()->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'logo_full_url' => Helpers::get_full_url('store', $store->logo, $store->storage->pluck('value')->first() ?? 'public'),
                    'total_trips' => (int) $store->total_trips,
                    'avg_rating' => (float) $store->avg_rating,
                ];
            });
        });
    }

    private function scopeToCurrentModule($query, $zone_id)
    {
        $moduleData = config('module.current_module_data');

        $query->whereHas('zone.modules', function ($query) use ($moduleData) {
            $query->where('modules.id', $moduleData['id']);
        })->module($moduleData['id']);

        if (! $moduleData['all_zone_service']) {
            $query->whereIn('zone_id', json_decode($zone_id, true));
        }

        return $query;
    }

    private function applyClosedAndUnavailableSort($query, array $sortSettings)
    {
        if ($sortSettings['temp_closed'] == 'remove') {
            $query = $query->where('active', '>', 0);
        } elseif ($sortSettings['temp_closed'] == 'last') {
            $query = $query->orderByDesc('active');
        }

        if ($sortSettings['unavailable'] == 'remove') {
            $query = $query->having('open', '>', 0);
        } elseif ($sortSettings['unavailable'] == 'last') {
            $query = $query->orderBy('open', 'desc');
        }

        return $query;
    }

    private function paginatedStorePayload($paginator, $limit, $offset): array
    {
        return [
            'total_size' => $paginator->total(),
            'limit' => $limit ?? 50,
            'offset' => $offset ?? 1,
            'stores' => $paginator->items(),
        ];
    }

    public function hasOrderAllowance($store): bool
    {
        if ($store->store_business_model === 'commission') {
            return true;
        }

        $subscription = $store->store_sub;

        return $subscription
            && ($subscription->max_order === 'unlimited' || (int) $subscription->max_order > 0);
    }
}
