<?php

namespace App\Services\Store;

use App\CentralLogics\Helpers;
use App\Models\StoreCategory;
use App\Services\BaseService;
use App\Traits\Item\ItemRelationsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use App\Services\Item\ItemService;
use App\Services\Store\StoreService;
use App\Services\Item\CategoryService;
use App\Support\Storage\FileStorage;

class StoreCategoryService extends BaseService
{
    use ItemRelationsTrait;
    private const IMAGE_DIR = 'category/';
    private const PRIORITIES = ['0', '1', '2'];
    private const USAGE_STATES = ['used', 'empty'];
    private const LIST_COLUMNS = ['id', 'store_id', 'name', 'image', 'priority', 'status', 'created_at'];
    private const BROWSE_COLUMNS = ['id', 'store_id', 'module_id', 'name', 'slug', 'image', 'priority', 'status'];
    private const ITEM_SEARCH_RELATIONS = [
        'translations' => 'value',
        'category' => 'name',
        'tags' => 'tag',
    ];

    private static array $existsMemo = [];

    public function getActiveForStore(mixed $storeId): mixed
    {
        return $this->activeForStoreQuery($storeId)->get();
    }
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            ->withStorage()
            ->latest()
            ->paginate($this->pageSize($paginate), self::LIST_COLUMNS, 'page', $this->pageNumber($paginate));
    }
    public function getActiveList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return StoreCategory::active()
            ->withStorage()
            ->when($filters['module_id'] ?? null, fn ($query, $moduleId) => $query->module($moduleId))
            ->when($filters['store_id'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->when($filters['name'] ?? null, fn ($query, $name) => $query->where('name', 'like', "%{$name}%"))
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->select(self::BROWSE_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    public function findOwned(mixed $id, mixed $storeId, bool $withTranslations = false): ?StoreCategory
    {
        return StoreCategory::where('store_id', $storeId)
            ->when($withTranslations, fn ($query) => $query->withoutGlobalScope('translate')->with('translations'))
            ->find($id);
    }
    public function buildQuery(array $filters = []): Builder
    {
        $relation = $this->resolveCountRelation($filters['store_id'] ?? null);

        return $this->applyListFilters(
            StoreCategory::withCount([$relation . ' as items_count']),
            $filters,
            $relation
        );
    }
    public function adminListFilters(array $input): array
    {
        $storeId = $input['store_id'] ?? null;
        $priority = $input['priority'] ?? null;
        $status = $input['status'] ?? null;
        $usage = $input['usage'] ?? null;

        return [
            'module_id' => $input['module_id'] ?? null,
            'search' => trim((string) ($input['search'] ?? '')),
            'store_id' => is_numeric($storeId) ? (int) $storeId : null,
            'priority' => in_array((string) $priority, self::PRIORITIES, true) ? (int) $priority : null,
            'status' => in_array((string) $status, ['0', '1'], true) ? (int) $status : null,
            'usage' => in_array($usage, self::USAGE_STATES, true) ? $usage : null,
        ];
    }
    public function adminListFilterCount(array $filters): int
    {
        return count(array_filter(
            [
                $filters['store_id'] ?? null,
                $filters['priority'] ?? null,
                $filters['status'] ?? null,
                $filters['usage'] ?? null,
            ],
            fn ($value) => $value !== null
        ));
    }
    public function adminSummary(array $filters): array
    {
        $relation = $this->resolveCountRelation($filters['store_id'] ?? null);
        $filters = array_merge($filters, ['priority' => null, 'status' => null, 'usage' => null]);

        $totals = $this->applyListFilters(StoreCategory::withoutTranslation(), $filters, $relation)
            ->reorder()
            ->selectRaw(
                'COUNT(*) as total'
                . ', SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active'
                . ', SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive'
                . ', SUM(CASE WHEN priority = 2 THEN 1 ELSE 0 END) as priority_high'
                . ', COUNT(DISTINCT store_id) as stores'
            )
            ->first();

        return [
            'total' => (int) ($totals->total ?? 0),
            'active' => (int) ($totals->active ?? 0),
            'inactive' => (int) ($totals->inactive ?? 0),
            'high_priority' => (int) ($totals->priority_high ?? 0),
            'stores' => (int) ($totals->stores ?? 0),
            'empty' => $this->applyListFilters(StoreCategory::withoutTranslation(), $filters, $relation)
                ->doesntHave($relation)
                ->count(),
        ];
    }
    public function getTranslatedLocales(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        return DB::table('translations')
            ->where('translationable_type', StoreCategory::class)
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
    public function create(int $storeId, string $name, ?int $priority = 0, ?UploadedFile $image = null): StoreCategory
    {
        $category = new StoreCategory();
        $category->store_id = $storeId;
        $category->module_id = $this->getStoreModuleId($storeId);
        $category->name = $name;
        $category->priority = $priority ?? 0;
        $category->status = 1;
        $category->image = $image ? FileStorage::upload(self::IMAGE_DIR, $image) : null;
        $category->save();

        return $category;
    }
    public function update(StoreCategory $category, ?string $name = null, ?int $priority = null, ?UploadedFile $image = null, ?int $storeId = null): StoreCategory
    {
        if ($storeId !== null) {
            $category->store_id = $storeId;
            $category->module_id = $this->getStoreModuleId($storeId);
        }
        if ($name !== null) {
            $category->name = $name;
        }
        if ($priority !== null) {
            $category->priority = $priority;
        }
        if ($image) {
            $category->image = FileStorage::update(self::IMAGE_DIR, $category->image, $image);
        }
        $category->save();

        return $category;
    }
    public function updateStatus(StoreCategory $category, int $status): StoreCategory
    {
        $category->status = $status;
        $category->save();
        return $category;
    }
    public function updatePriority(StoreCategory $category, int $priority): StoreCategory
    {
        $category->priority = $priority;
        $category->save();
        return $category;
    }
    public function delete(StoreCategory $category): bool
    {
        if ($category->image) {
            FileStorage::delete(self::IMAGE_DIR, $category->image);
        }
        $category->translations()->delete();
        return (bool) $category->delete();
    }
    public function saveFormTranslations(StoreCategory $category, array $translations): void
    {
        Helpers::add_or_update_translations(
            request: (object) [
                'lang' => $translations['lang'] ?? null,
                'name' => $translations['name'] ?? null,
            ],
            key_data: 'name',
            name_field: 'name',
            model_name: 'StoreCategory',
            data_id: $category->id,
            data_value: $category->name
        );
    }
    public function getCategoriesWithItems(array $filters = [], array $paginate = []): array
    {
        $storeId = (int) ($filters['store_id'] ?? 0);
        $useStoreCategory = $this->storeCategoriesInUse($storeId, $filters);
        $mainCategoryMap = [];

        if ($useStoreCategory) {
            $categories = $this->getStoreCategories($storeId, $filters);
        } else {
            [$categories, $mainCategoryMap] = $this->getMainCategories($storeId, $filters);
        }

        $categoryIds = $categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        $groupColumn = $useStoreCategory ? 'store_category_id' : 'category_id';

        $scopedCategoryIds = $mainCategoryMap
            ? array_map('intval', array_keys($mainCategoryMap))
            : $categoryIds;

        if (! $categoryIds) {
            return [
                'total_size' => 0,
                'category_source' => $useStoreCategory ? 'store_category' : 'main_category',
                'categories' => collect(),
                'counts' => collect(),
                'grouped' => [],
                'category_ids' => [],
                'paginator' => null,
            ];
        }

        $counts = $this->itemQuery($filters, $scopedCategoryIds, $groupColumn)
            ->reorder()
            ->select(DB::raw("items.{$groupColumn} AS cat_group"), DB::raw('COUNT(*) AS cnt'))
            ->groupBy(DB::raw("items.{$groupColumn}"))
            ->pluck('cnt', 'cat_group');

        if ($mainCategoryMap) {
            $counts = $this->rollUpCounts($counts, $mainCategoryMap);
        }

        $page = $this->itemQuery($filters, $scopedCategoryIds, $groupColumn)
            ->with($this->itemRelations())
            ->reorder()
            ->select('items.*')
            ->orderBy('items.name', 'asc')
            ->orderBy('items.id', 'asc')
            ->applySorting($filters['sort_by'] ?? 'default')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $grouped = [];
        $buckets = $page->getCollection()->groupBy(
            $mainCategoryMap
                ? fn ($item) => $mainCategoryMap[(int) $item->category_id] ?? null
                : fn ($item) => (int) $item->{$groupColumn}
        );

        foreach ($categoryIds as $categoryId) {
            $bucket = $buckets[$categoryId] ?? null;

            if ($bucket && $bucket->isNotEmpty()) {
                $grouped[(string) $categoryId] = $bucket->values();
            }
        }

        return [
            'total_size' => (int) $counts->sum(),
            'category_source' => $useStoreCategory ? 'store_category' : 'main_category',
            'categories' => $categories,
            'counts' => $counts,
            'grouped' => $grouped,
            'category_ids' => $categoryIds,
            'paginator' => $page,
        ];
    }
    public function saveApiTranslations(StoreCategory $category, array $translations): void
    {
        foreach ($translations as $t) {
            $locale = $t['locale'] ?? null;
            $value = $t['value'] ?? null;
            if (!$locale || $locale === 'default' || empty($value)) {
                continue;
            }
            $category->translations()->updateOrCreate(
                ['locale' => $locale, 'key' => 'name'],
                ['value' => $value]
            );
        }
    }
    public function existsForResolvedStore(?int $storeId = null): bool
    {
        $storeId ??= $this->resolveVendorStoreId();

        if (! $storeId) {
            return false;
        }

        return self::$existsMemo[$storeId] ??= StoreCategory::where('store_id', $storeId)->exists();
    }

    private function resolveVendorStoreId(): ?int
    {
        if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->store->id ?? null;
        }

        $vendor = auth('vendor')->check() ? auth('vendor')->user() : null;

        return $vendor && $vendor->stores && $vendor->stores->isNotEmpty()
            ? (int) $vendor->stores[0]->id
            : null;
    }

    private function activeForStoreQuery(mixed $storeId): mixed
    {
        return StoreCategory::active()->withStorage()->where('store_id', $storeId)->orderBy('priority', 'desc');
    }
    private function getStoreModuleId(int $storeId): ?int
    {
        return app(StoreService::class)->findModuleId($storeId);
    }
    private function applyListFilters(Builder $query, array $filters, string $relation): Builder
    {
        return $query
            ->when(isset($filters['store_id']) && $filters['store_id'] !== '', function ($q) use ($filters) {
                $q->where('store_id', $filters['store_id']);
            })
            ->when(isset($filters['module_id']) && $filters['module_id'] !== '', function ($q) use ($filters) {
                $q->where('module_id', $filters['module_id']);
            })
            ->when(isset($filters['priority']) && $filters['priority'] !== '', function ($q) use ($filters) {
                $q->where('priority', $filters['priority']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->when(($filters['usage'] ?? null) === 'used', fn ($q) => $q->has($relation))
            ->when(($filters['usage'] ?? null) === 'empty', fn ($q) => $q->doesntHave($relation))
            ->search(
                keywords: $filters['search'] ?? null,
                relations: ['translations' => 'value'],
                mainCol: ['name', 'id']
            );
    }
    private function resolveCountRelation($storeId): string
    {
        if (!empty($storeId) && addon_published_status('Service')) {
            $moduleType = app(StoreService::class)->findModuleTypeOnly($storeId);
            if ($moduleType === 'service') {
                return 'services';
            }
        }

        return 'items';
    }
    private function storeCategoriesInUse(int $storeId, array $filters): bool
    {
        return Helpers::storeCategoryStatus()
            && StoreCategory::active()
                ->where('store_id', $storeId)
                ->whereHas('items', fn ($query) => $query->active(zone_ids: $filters['zone_ids'] ?? null, module_id: $filters['module_id'] ?? null))
                ->exists();
    }
    private function getStoreCategories(int $storeId, array $filters): Collection
    {
        return $this->activeForStoreQuery($storeId)
            ->whereHas('items', fn ($query) => $query->active(zone_ids: $filters['zone_ids'] ?? null, module_id: $filters['module_id'] ?? null))
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'image', 'priority', 'status'])
            ->toBase();
    }
    private function getMainCategories(int $storeId, array $filters): array
    {
        $usedCategoryIds = app(ItemService::class)->getUsedCategoryIds(
            $this->constrainItems(app(ItemService::class)->query(), $filters)
        );

        if (! $usedCategoryIds) {
            return [collect(), []];
        }

        $categoryService = app(CategoryService::class);
        $mainCategoryMap = $categoryService->getMainCategoryMap($usedCategoryIds);

        if (! $mainCategoryMap) {
            return [collect(), []];
        }

        $categories = $categoryService->getActiveMainByIds(array_values(array_unique($mainCategoryMap)));
        $listedIds = array_flip($categories->pluck('id')->map(fn ($id) => (int) $id)->all());

        return [
            $categories,
            array_filter($mainCategoryMap, fn ($mainId) => isset($listedIds[$mainId])),
        ];
    }

    private function rollUpCounts(Collection $counts, array $mainCategoryMap): Collection
    {
        $rolledUp = [];

        foreach ($counts as $categoryId => $count) {
            $mainId = $mainCategoryMap[(int) $categoryId] ?? null;

            if ($mainId !== null) {
                $rolledUp[$mainId] = ($rolledUp[$mainId] ?? 0) + (int) $count;
            }
        }

        return collect($rolledUp);
    }
    private function itemQuery(array $filters, array $categoryIds, string $groupColumn): Builder
    {
        $query = app(ItemService::class)->query()->whereIn("items.{$groupColumn}", $categoryIds);

        return $this->applyItemFilters($this->constrainItems($query, $filters), $filters);
    }
    private function constrainItems(Builder $query, array $filters): Builder
    {
        return $query->active(zone_ids: $filters['zone_ids'] ?? null, module_id: $filters['module_id'] ?? null)
            ->where('items.store_id', $filters['store_id'] ?? null)
            ->type($filters['type'] ?? 'all');
    }
    private function applyItemFilters(Builder $query, array $filters): Builder
    {
        $inputs = new Fluent($filters['inputs'] ?? []);
        $ratingCount = $filters['inputs']['rating_count'] ?? null;

        return $query->applyFilters([
            'sort_by' => $filters['sort_by'] ?? 'default',
            'filter_by' => $filters['filter_by'] ?? [],
        ])
            ->applyRating($inputs)
            ->applyPriceRange($inputs)
            ->when($ratingCount && is_numeric($ratingCount), fn ($q) => $q->where('avg_rating', '<', (int) $ratingCount + 1))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->search($search, self::ITEM_SEARCH_RELATIONS));
    }
    private function itemRelations(): array
    {
        return $this->itemRelationSet();
    }
}
