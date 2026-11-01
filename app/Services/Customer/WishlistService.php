<?php

namespace App\Services\Customer;

use App\Models\Wishlist;
use App\Services\BaseService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Item\ItemRelationsTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Fluent;

class WishlistService extends BaseService
{
    use ItemRelationsTrait;
    use PersonalizationTrait;

    private const SEARCH_RELATIONS = [
        'translations' => 'value',
        'tags' => 'tag',
        'category.parent' => 'name',
        'category' => 'name',
        'nutritions' => 'nutrition',
        'allergies' => 'allergy',
        'generic' => 'generic_name',
        'ecommerce_item_details.brand' => 'name',
        'pharmacy_item_details.common_condition' => 'name',
    ];

    private const ITEM_COLUMNS = [
        'id', 'name', 'slug', 'price', 'discount', 'discount_type', 'stock', 'maximum_cart_quantity',
        'veg', 'organic', 'is_halal', 'order_count', 'avg_rating', 'available_time_starts',
        'available_time_ends', 'category_ids', 'image', 'images', 'unit_id', 'module_id', 'store_id',
    ];

    public function getItemIds(mixed $userId, array $itemIds): array
    {
        return $this->ownedQuery($userId)->whereIn('item_id', $itemIds)->pluck('item_id')->all();
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $query = $this->ownedQuery($filters['user_id'] ?? null)
            ->where(function ($group) use ($filters) {
                $group->whereHas('item', fn ($item) => $this->constrainItems($item, $filters))
                    ->orWhereHas('store', fn ($store) => $this->constrainStores($store, $filters));

                if (service_addon_active()) {
                    $group->orWhereHas('service', fn ($service) => $this->constrainServices($service, $filters));
                }
            })
            ->with($this->hydrationRelations($filters))
            ->latest();

        return $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findOwned(mixed $userId, array $target): ?Wishlist
    {
        return $this->ownedQuery($userId)
            ->when($target['item_id'] ?? null, fn ($query, $id) => $query->where('item_id', $id))
            ->when($target['store_id'] ?? null, fn ($query, $id) => $query->where('store_id', $id))
            ->when($target['service_id'] ?? null, fn ($query, $id) => $query->where('service_id', $id))
            ->first();
    }

    public function exists(mixed $userId, array $target): bool
    {
        return $this->ownedQuery($userId)
            ->where('item_id', $target['item_id'] ?? null)
            ->where('store_id', $target['store_id'] ?? null)
            ->where('service_id', $target['service_id'] ?? null)
            ->exists();
    }

    public function create(array $data): Wishlist
    {
        $wishlist = new Wishlist;
        $wishlist->user_id = $data['user_id'];
        $wishlist->item_id = $data['item_id'] ?? null;
        $wishlist->store_id = $data['store_id'] ?? null;
        $wishlist->service_id = $data['service_id'] ?? null;
        $wishlist->save();

        $this->recordPersonalization($data);

        return $wishlist;
    }

    public function delete(Wishlist $wishlist): bool
    {
        return (bool) $wishlist->delete();
    }

    public function favoriteStoreIds(mixed $userId, array $storeIds): array
    {
        return $this->favoriteIdsFor($userId, $storeIds, 'store_id');
    }

    private function favoriteIdsFor(mixed $userId, array $ids, string $column): array
    {
        if (! $userId || $ids === []) {
            return [];
        }

        return $this->ownedQuery($userId)
            ->whereIn($column, $ids)
            ->pluck($column)
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function favoriteServiceIds(mixed $userId, array $serviceIds): array
    {
        return $this->favoriteIdsFor($userId, $serviceIds, 'service_id');
    }

    private function ownedQuery(mixed $userId): mixed
    {
        return Wishlist::where('user_id', $userId);
    }

    private function recordPersonalization(array $data): void
    {
        if ($data['item_id'] ?? null) {
            $this->recordItemAction($data['user_id'], (int) $data['item_id'], 'item_wishlist');
        }

        if ($data['store_id'] ?? null) {
            $this->recordStoreAction($data['user_id'], (int) $data['store_id'], 'store_wishlist');
        }

        if ($data['service_id'] ?? null) {
            $this->recordServiceAction($data['user_id'], (int) $data['service_id'], 'item_wishlist');
        }
    }

    private function hydrationRelations(array $filters): array
    {
        $relations = [
            'item' => function ($query) use ($filters) {
                $query->select(self::ITEM_COLUMNS);
                $this->constrainItems($query, $filters);
                $query->withStorage()->with($this->itemRelationSet());
            },
            'store' => function ($query) use ($filters) {
                $this->constrainStores($query, $filters);
                $query->withOpen($filters['longitude'] ?? 0, $filters['latitude'] ?? 0)->withStorage()->with([
                    'storeConfig' => fn ($config) => $config->select('store_id', 'verified_seller', 'extra_packaging_status', 'extra_packaging_amount'),
                    'discount' => fn ($discount) => $discount->validate(),
                    'module' => fn ($module) => $module->withoutGlobalScope('translate')->select('id', 'module_type'),
                ]);
            },
        ];

        if (service_addon_active()) {
            $relations['service'] = function ($query) use ($filters) {
                $this->constrainServices($query, $filters);
                $query->with(['category.storage', 'subCategory.storage', 'storeCategory.storage', 'store.storage', 'module.storage', 'taxVats.tax']);
            };
        } else {
            $relations[] = 'service';
        }

        return $relations;
    }

    private function constrainItems(mixed $query, array $filters): mixed
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;
        $filterList = $filters['filter_list'] ?? [];
        $inputs = new Fluent($filters['inputs'] ?? []);

        $query->active(zone_ids: $zoneIds, module_id: $moduleId)
            ->type($filters['type'] ?? 'all');

        if ($moduleId) {
            $query->where('items.module_id', $moduleId);
        }

        return $query
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->search(keywords: $search, relations: self::SEARCH_RELATIONS))
            ->when(in_array('coupon', $filterList), function ($q) use ($zoneIds) {
                $q->whereHas('module.zones', function ($zone) use ($zoneIds) {
                    if (! empty($zoneIds)) {
                        $zone->whereIn('zones.id', $zoneIds);
                    }
                    $zone->has('activeCoupons');
                });
            })
            ->when(in_array('available_now', $filterList), function ($q) {
                $q->where(function ($window) {
                    $currentTime = now()->format('H:i:s');
                    $window->whereRaw('(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)', [$currentTime])
                        ->orWhereRaw('(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))', [$currentTime, $currentTime]);
                });
            })
            ->applyRating($inputs)
            ->applyFilters($filters['item_filters'] ?? [])
            ->applyPriceRange($inputs);
    }

    private function constrainStores(mixed $query, array $filters): mixed
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;

        $query->where('status', 1)
            ->whereHas('module', fn ($module) => $module->where('status', 1))
            ->whereIn('zone_id', $zoneIds);

        if ($moduleId) {
            $query->where('stores.module_id', $moduleId);
        }

        return $query->applyStoreFilter($filters['store_filters'] ?? []);
    }

    private function constrainServices(mixed $query, array $filters): mixed
    {
        return $query->active(zone_ids: $filters['zone_ids'] ?? [], module_id: $filters['module_id'] ?? null);
    }
}
