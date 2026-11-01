<?php

namespace App\Services\Item;

use App\Models\Category;
use App\Models\Item;
use App\Services\BaseService;
use App\Services\Store\StoreService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Item\ItemRelationsTrait;
use App\Traits\System\MemoizesLookupsTrait;
use App\Traits\System\PrioritySettingsTrait;
use Exception;
use InvalidArgumentException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Services\System\BusinessSettingService;
use App\Support\Storage\FileStorage;

class CategoryService extends BaseService
{
    use ItemRelationsTrait;
    use MemoizesLookupsTrait;
    use PersonalizationTrait, PrioritySettingsTrait;

    private const LIST_COLUMNS = ['id', 'name', 'slug', 'image', 'parent_id', 'priority', 'featured', 'module_id'];

    public function getTrendingRootQuery(mixed $moduleId, callable $hasAvailableItem): mixed
    {
        return Category::translateOnly(['name'])
            ->where('status', 1)
            ->where('position', 0)
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->where(fn ($q) => $q->whereHas('products', $hasAvailableItem)->orWhereHas('childes.products', $hasAvailableItem));
    }

    public function getTopNamesByIds(array $categoryIds, int $limit = 10): array
    {
        return $this->buildByIdsQuery($categoryIds, ['active' => true, 'translate' => true, 'order' => ['priority', 'desc'], 'limit' => $limit])
            ->get(['id', 'name'])
            ->pluck('name')
            ->toArray();
    }

    public function getTreeForItemCategories(array $categoryIds): mixed
    {
        return $this->buildTreeByIdsQuery($categoryIds, [
            'with' => ['childes' => fn ($query) => $query->withCount(['products', 'childes'])],
        ])->get();
    }

    public function getBasicActiveByIds(array $categoryIds): mixed
    {
        return $this->buildByIdsQuery($categoryIds, ['storage' => true, 'active' => true])->get(['id', 'name', 'image']);
    }

    public function getActiveMainByIds(array $ids): mixed
    {
        return $this->buildByIdsQuery($ids, ['storage' => true, 'active' => true, 'main_only' => true, 'order' => ['name', 'asc']])
            ->get(['id', 'name', 'image'])
            ->toBase();
    }

    public function getMainCategoryMap(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return Category::where('status', 1)
            ->whereIn('id', $ids)
            ->pluck('parent_id', 'id')
            ->map(fn ($parentId, $id) => (int) ($parentId ?: $id))
            ->all();
    }

    public function getSidecarTree(array $categoryIds, int $limit): array
    {
        return $this->buildTreeByIdsQuery($categoryIds, [
            'storage' => true,
            'with' => ['childes.storage', 'childes' => fn ($query) => $query->withCount(['products', 'childes'])],
            'limit' => $limit,
        ])->get()->all();
    }

    public function getSearchTree(array $categoryIds, int $limit): array
    {
        return $this->buildTreeByIdsQuery($categoryIds, [
            'storage' => true,
            'with' => ['childes' => fn ($query) => $query->withStorage()->withCount(['products', 'childes'])],
            'limit' => $limit,
        ])->get()->all();
    }

    public function getByIdsWithStorageLimited(array $categoryIds, int $limit): array
    {
        return $this->buildByIdsQuery($categoryIds, ['storage' => true, 'limit' => $limit])->get()->all();
    }

    public function getBasicByIds(array $categoryIds, int $limit): mixed
    {
        return $this->buildByIdsQuery($categoryIds, ['storage' => true, 'order' => ['priority', 'desc'], 'limit' => $limit])
            ->get(['id', 'name', 'image'])
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'image_full_url' => $category->image_full_url,
            ]);
    }

    public function getByIdsWithStorage(array $ids): mixed
    {
        return $this->buildByIdsQuery($ids, ['storage' => true])->get();
    }

    public function findMemoizedNameById(mixed $id): ?string
    {
        $map = $this->memoize('categories', fn () => Category::get()->keyBy('id'));

        return $map->get($id)?->name;
    }

    public function getNamesByIds(array $ids): mixed
    {
        return $this->buildByIdsQuery($ids, ['translate' => true])->get(['id', 'name'])->pluck('name', 'id');
    }

    public function getAddData(array $input, string|null|object $parentCategory): array
    {
        return [
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'image' => FileStorage::upload('category/', ($input['image'] ?? null)),
            'parent_id' => ($input['parent_id'] ?? null) == null ? 0 : ($input['parent_id'] ?? null),
            'position' => ($input['position'] ?? null),
            'priority' => ($input['priority'] ?? null) ?? 0,
            'module_id' => isset($input['parent_id']) ? $parentCategory['module_id'] : Config::get('module.current_module_id'),
        ];
    }

    public function getUpdateData(array $input, object $object): array
    {
        $name = $input['name'][array_search('default', $input['lang'])];
        $slug = Str::slug($name);

        return [
            'slug' => $object->slug ?? "{$slug}{$object->id}",
            'name' => $name,
            'priority' => $input['priority'] ?? 0,
            'status' => $input['status'] ?? 0,
            'parent_id' => $input['parent_id'] ?? 0,
            'image' => array_key_exists('image', $input)
                ? FileStorage::update('category/', $object->image, $input['image'])
                : $object->image,
        ];
    }

    /**
     * Rows the admin category lists need beside each category: how many sub categories sit under
     * it, how many items it holds, and which locales its name is translated into.
     *
     * One query each over the page's ids rather than a per-row count.
     */
    public function getSubCategoryCounts(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        return Category::withoutGlobalScope('translate')
            ->whereIn('parent_id', $categoryIds)
            ->select('parent_id', DB::raw('COUNT(*) AS total'))
            ->groupBy('parent_id')
            ->pluck('total', 'parent_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Items per category. Both columns are indexed, and which one to count over depends on where
     * in the tree the row sits:
     *
     *   top_category_id  a main category -- everything rolling up to it, its sub categories
     *                    included. The stored derived column, kept current by ItemObserver and
     *                    CategoryObserver.
     *   category_id      a sub category -- the items actually assigned to it. An item's
     *                    category_id is the leaf it was filed under, so a main category counted
     *                    this way would report only the items filed directly on it.
     *
     * Goes through the Item model so ZoneScope still narrows a zone-restricted admin to their own
     * zone.
     */
    public function getItemCounts(array $categoryIds, string $column = 'top_category_id'): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        if (! in_array($column, ['top_category_id', 'category_id'], true)) {
            throw new InvalidArgumentException("Cannot count items over [{$column}].");
        }

        return Item::withoutGlobalScope('translate')
            ->whereIn($column, $categoryIds)
            ->select($column, DB::raw('COUNT(*) AS total'))
            ->groupBy($column)
            ->pluck('total', $column)
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function getTranslatedLocales(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        return DB::table('translations')
            ->where('translationable_type', Category::class)
            ->whereIn('translationable_id', $categoryIds)
            ->where('key', 'name')
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->orderBy('locale')
            ->get(['translationable_id', 'locale'])
            ->groupBy('translationable_id')
            ->map(fn ($rows) => $rows->pluck('locale')->unique()->values()->all())
            ->all();
    }

    public function getImportData(mixed $file, bool $toAdd = true): array
    {
        try {
            $collections = (new FastExcel)->import($file);
        } catch (Exception) {
            return ['flag' => 'wrong_format'];
        }
        $moduleId = Config::get('module.current_module_id');

        $requiredColumns = ['Name', 'Image', 'ParentId', 'Position', 'Priority', 'Status'];
        if (! $toAdd) {
            $requiredColumns[] = 'Id';
        }
        $firstRow = $collections->first();
        if ($firstRow !== null) {
            foreach ($requiredColumns as $column) {
                if (! array_key_exists($column, (array) $firstRow)) {
                    return ['flag' => 'wrong_format'];
                }
            }
        }

        $data = [];
        $seenNames = [];
        foreach ($collections as $collection) {
            if ($collection['Name'] === '') {
                return ['flag' => 'required_fields'];
            }

            $position = is_numeric($collection['Position']) ? (int) $collection['Position'] : null;
            if (! in_array($position, [0, 1], true)) {
                return ['flag' => 'invalid_position'];
            }

            $parentId = is_numeric($collection['ParentId']) ? (int) $collection['ParentId'] : 0;
            if ($position === 1) {
                $parentExists = $parentId > 0 && Category::where(['id' => $parentId, 'position' => 0, 'module_id' => $moduleId])->exists();
                if (! $parentExists) {
                    return ['flag' => 'invalid_parent'];
                }
            } else {
                $parentId = 0;
            }

            $ignoreId = (! $toAdd && is_numeric($collection['Id'])) ? (int) $collection['Id'] : null;
            $nameKey = $parentId.'|'.mb_strtolower(trim($collection['Name']));
            if (in_array($nameKey, $seenNames, true) || Category::isDuplicateName($collection['Name'], $moduleId, $parentId, $ignoreId)) {
                return ['flag' => 'duplicate_name'];
            }
            $seenNames[] = $nameKey;

            $array = [
                'name' => $collection['Name'],
                'image' => $collection['Image'],
                'parent_id' => $parentId,
                'module_id' => $moduleId,
                'position' => $position,
                'priority' => is_numeric($collection['Priority']) ? $collection['Priority'] : 0,
                'status' => $collection['Status'] == 'active' ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! $toAdd) {
                $array['id'] = $collection['Id'];
            }

            $data[] = $array;
        }

        return $data;
    }

    public function getExportData(object $collection): array
    {
        $data = [];
        foreach ($collection as $item) {
            $data[] = [
                'Id' => $item->id,
                'Name' => $item->name,
                'Image' => $item->image,
                'ParentId' => $item->parent_id,
                'Position' => $item->position,
                'Priority' => $item->priority,
                'Status' => $item->status == 1 ? 'active' : 'inactive',
            ];
        }

        return $data;
    }

    public function getStoreCategories(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->topLevelListQuery($filters['module_id'] ?? null)
            ->selectRaw($this->storeProductCountSql($filters), [$filters['store_id'] ?? null])
            ->withCount(['childes as childs_count' => fn ($query) => $query->where('status', 1)])
            ->orderByDesc('priority')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getStoreChildes(
        mixed $parent,
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $query = Category::translateOnly(['name'])
            ->withStorage()
            ->select(self::LIST_COLUMNS)
            ->where('status', 1)
            ->when(
                is_numeric($parent),
                fn ($q) => $q->where('parent_id', $parent),
                fn ($q) => $q->whereHas('parent', fn ($p) => $p->where('slug', $parent))
            )
            ->withCount(['childes as childs_count' => fn ($q) => $q->where('status', 1)])
            ->orderByDesc('priority');

        if ($filters['is_service'] ?? false) {
            return $query
                ->selectRaw(
                    '( SELECT COUNT(*) FROM services WHERE services.store_id = ? AND services.sub_category_id = categories.id ) AS products_count',
                    [$filters['store_id'] ?? null]
                )
                ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
        }

        return $query
            ->withCount(['products' => fn ($q) => $q->where('store_id', $filters['store_id'] ?? null)])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        bool $withTrashed = false,
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildCustomerQuery($filters)
            ->with($with)
            ->withCount($withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getChildes(
        mixed $parentId,
        array $paginate = [],
        array $zone_ids = [],
        mixed $module_id = null
    ): LengthAwarePaginator {
        // Same "no live item, don't list it" rule as buildCustomerQuery() -- this is the same
        // sub-category set that would otherwise have been dropped from the nested `childes` array
        // on GET /categories; a client paging into it directly via GET /categories/childes/{id}
        // must not see any that the parent listing already hid.
        $hasAvailableItem = function ($itemQuery) use ($zone_ids, $module_id) {
            $itemQuery->active(zone_ids: $zone_ids, module_id: $module_id);
        };

        return Category::translateOnly(['name'])
            ->withStorage()
            ->select(self::LIST_COLUMNS)
            ->where(['parent_id' => $parentId, 'status' => 1])
            ->whereHas('products', $hasAvailableItem)
            ->orderByDesc('priority')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getPopularList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $categoryIds = app(ItemService::class)->getPopularCategoryIds();

        return Category::translateOnly(['name'])
            ->withStorage()
            ->select(self::LIST_COLUMNS)
            ->when($filters['module_id'] ?? null, fn ($query, $moduleId) => $query->module($moduleId))
            ->whereIn('id', $categoryIds)
            ->where(['position' => 0, 'status' => 1])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getTopList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $moduleId = $filters['module_id'] ?? null;
        $zoneCondition = $this->zoneCondition($filters['zone_ids'] ?? []);
        $serviceOrders = (! $moduleId && addon_published_status('Service'))
            ? "+ (SELECT COALESCE(SUM(services.order_count), 0) FROM services
                    JOIN stores ON stores.id = services.store_id
                    WHERE services.status = 1 AND services.is_approved = 1
                        AND services.category_id = categories.id {$zoneCondition} )"
            : '';

        return $this->topLevelListQuery($moduleId)
            ->with(['childes' => fn ($query) => $query->translateOnly(['name'])->where('status', 1)->select('id', 'name', 'slug', 'parent_id')])
            // items.top_category_id instead of JSON_CONTAINS over items.category_ids, which
            // no index could serve -- one full item scan per top-level category.
            ->selectRaw("(SELECT COALESCE(SUM(items.order_count), 0) FROM items
                            JOIN stores ON stores.id = items.store_id
                            WHERE items.is_approved = 1
                                AND items.top_category_id = categories.id
                                {$zoneCondition} ) {$serviceOrders} AS total_order_count")
            ->orderByDesc('total_order_count')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function loadItemRelations(mixed $items): mixed
    {
        $collection = $items instanceof SupportCollection ? $items : collect($items);

        if ($collection->isNotEmpty()) {
            (new Collection($collection->all()))->loadMissing($this->itemRelationSet());
        }

        return $items;
    }

    public function loadStoreRelations(mixed $stores): mixed
    {
        $collection = $stores instanceof SupportCollection ? $stores : collect($stores);

        if ($collection->isNotEmpty()) {
            (new Collection($collection->all()))->loadMissing([
                'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
                'storeConfig' => fn ($query) => $query->select('store_id', 'verified_seller', 'extra_packaging_status', 'extra_packaging_amount'),
                'discount' => fn ($query) => $query->validate(),
                'module' => fn ($query) => $query->withoutGlobalScope('translate')->select('id', 'module_type'),
            ]);
        }

        return $stores;
    }

    public function getStoreItems(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return app(ItemService::class)->getStoreItems($filters, $paginate);
    }

    public function getCategoryItems($categoryId, $zoneId, int $limit, int $offset, $type, $userId = null)
    {
        return app(ItemService::class)->getCategoryItems($categoryId, $zoneId, $limit, $offset, $type, $userId);
    }

    public function getItemsForCategories($categoryIds, $zoneId, int $limit, int $offset, $type, $filter = null, $min = false, $max = false, $ratingCount = null, $brandIds = null, $userId = null)
    {
        return app(ItemService::class)->getItemsForCategories($categoryIds, $zoneId, $limit, $offset, $type, $filter, $min, $max, $ratingCount, $brandIds, $userId);
    }

    public function getAllCategoryItems($id, $zoneId)
    {
        return app(ItemService::class)->getAllCategoryItems($id, $zoneId);
    }

    public function getFeaturedItems($zoneId, int $limit, int $offset, $type, $userId = null)
    {
        return app(ItemService::class)->getFeaturedItems($zoneId, $limit, $offset, $type, $userId);
    }

    public function getStoresForCategories($categoryIds, $zoneId, int $limit, int $offset, $type, $longitude = 0, $latitude = 0, $filter = null, $ratingCount = null, ?array $storeFilter = null, $userId = null)
    {
        return app(StoreService::class)->getStoresForCategories($categoryIds, $zoneId, $limit, $offset, $type, $longitude, $latitude, $filter, $ratingCount, $storeFilter, $userId);
    }

    public function getCategoryStores($categoryId, $zoneId, int $limit, int $offset, $type, $longitude = 0, $latitude = 0)
    {
        return app(StoreService::class)->getCategoryStores($categoryId, $zoneId, $limit, $offset, $type, $longitude, $latitude);
    }

    public function getChildIds($parentId)
    {
        return Category::where(['parent_id' => $parentId])->get();
    }

    private function buildByIdsQuery(array $categoryIds, array $options = []): Builder
    {
        return Category::when($options['translate'] ?? false, fn ($query) => $query->translateOnly(['name']))
            ->when($options['storage'] ?? false, fn ($query) => $query->withStorage())
            ->when($options['active'] ?? false, fn ($query) => $query->where('status', 1))
            ->when($options['main_only'] ?? false, fn ($query) => $query->where('position', 0))
            ->whereIn('id', $categoryIds)
            ->when($options['order'] ?? null, fn ($query, $order) => $query->orderBy($order[0], $order[1]))
            ->when(($options['limit'] ?? null) !== null, fn ($query) => $query->limit($options['limit']));
    }

    private function buildTreeByIdsQuery(array $categoryIds, array $options = []): Builder
    {
        return Category::when($options['storage'] ?? false, fn ($query) => $query->withStorage())
            ->withCount(['products', 'childes'])
            ->with($options['with'] ?? [])
            ->where(['position' => 0, 'status' => 1])
            ->when(config('module.current_module_data'), fn ($query) => $query->module(config('module.current_module_data')['id']))
            ->whereIn('id', $categoryIds)
            ->orderBy('priority', 'desc')
            ->when(($options['limit'] ?? null) !== null, fn ($query) => $query->limit($options['limit']));
    }

    private function topLevelListQuery(mixed $moduleId): mixed
    {
        return Category::translateOnly(['name'])
            ->withStorage()
            ->select(self::LIST_COLUMNS)
            ->where(['position' => 0, 'status' => 1])
            ->when($moduleId, fn ($query, $id) => $query->module($id));
    }

    private function storeProductCountSql(array $filters): string
    {
        return ($filters['is_service'] ?? false)
            ? '( SELECT COUNT(*) FROM services WHERE services.store_id = ? AND services.category_id = categories.id ) AS products_count'
            : '( SELECT COUNT(*) FROM items WHERE items.store_id = ?
                    AND items.top_category_id = categories.id ) AS products_count';
    }

    private function buildCustomerQuery(array $filters): Builder
    {
        $defaultStatus = app(BusinessSettingService::class)->value('category_list_default_status') ?? 1;
        $sortBy = $this->findPrioritySetting(name: 'category_list_sort_by_general', type: 'general');
        $customSort = $defaultStatus != 1;

        // Same closure shape as getTrendingRootQuery()/SearchLogService::itemFallbackCategories() --
        // an empty category (nothing live to sell) is worse than no category at all, so both a
        // sub-category with no items and a top-level category with no items anywhere under it
        // (itself or every child) are dropped from the response entirely.
        $hasAvailableItem = function ($itemQuery) use ($filters) {
            $itemQuery->active(zone_ids: $filters['zone_ids'] ?? [], module_id: $filters['module_id'] ?? null);
        };

        $query = Category::translateOnly(['name'])
            ->withStorage()
            ->with(['childes' => fn ($q) => $q->translateOnly(['name'])->where('status', 1)->whereHas('products', $hasAvailableItem)->select('id', 'name', 'slug', 'parent_id')]);

        if ($customSort && $sortBy === 'order_count') {
            $moduleCondition = ($filters['module_id'] ?? null)
                ? 'AND items.module_id = '.(int) $filters['module_id']
                : '';
            $query->select(self::LIST_COLUMNS)->selectRaw("(SELECT COALESCE(SUM(items.order_count), 0) FROM items
                            JOIN stores ON stores.id = items.store_id
                            WHERE items.is_approved = 1
                                AND items.top_category_id = categories.id
                                ".$this->zoneCondition($filters['zone_ids'] ?? [])." {$moduleCondition} ) AS total_order_count");
        } else {
            $query->select(self::LIST_COLUMNS);
        }

        $query->where(['position' => 0, 'status' => 1])
            ->when($filters['module_id'] ?? null, fn ($q, $moduleId) => $q->module($moduleId))
            ->when($filters['featured'] ?? null, fn ($q) => $q->featured())
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->search($search, ['childes' => 'name']))
            ->where(fn ($q) => $q->whereHas('products', $hasAvailableItem)->orWhereHas('childes.products', $hasAvailableItem));

        if (! $customSort) {
            return $this->applyCategoryPersonalization($query, $filters['customer_id'] ?? null)
                ->orderByDesc('priority');
        }

        return match ($sortBy) {
            'latest' => $query->latest(),
            'oldest' => $query->oldest(),
            'a_to_z' => $query->orderBy('name'),
            'z_to_a' => $query->orderBy('name', 'desc'),
            'order_count' => $query->orderByDesc('total_order_count'),
            default => $query,
        };
    }

    private function zoneCondition(array $zoneIds): string
    {
        $ids = array_filter($zoneIds, 'is_numeric');

        return empty($ids) ? '' : 'AND stores.zone_id IN ('.implode(',', $ids).')';
    }
}
