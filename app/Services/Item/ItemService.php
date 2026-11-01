<?php

namespace App\Services\Item;

use App\CentralLogics\Helpers;
use App\Models\Category;
use App\Models\Item;
use App\Scopes\StoreScope;
use App\Traits\Item\HasProductTaxablesTrait;
use App\Services\BaseService;
use App\Services\Customer\WishlistService;
use App\Services\Marketing\ItemCampaignService;
use App\Services\Store\StoreConfigService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Item\ItemFilterTrait;
use App\Traits\Item\ItemRatingTrait;
use App\Traits\Item\ItemRelationsTrait;
use App\Traits\Item\ItemSeoDataTrait;
use App\Traits\Item\ProductPayloadTrait;
use App\Traits\Item\ProductVideoTrait;
use App\Traits\Item\TempProductTrait;
use App\Traits\Store\StoreDataTrait;
use App\Traits\System\PrioritySettingsTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use App\Services\System\BusinessSettingService;
use App\Support\Storage\FileStorage;

class ItemService extends BaseService
{
    use HasProductTaxablesTrait;
    use ItemFilterTrait;
    use ItemRatingTrait, StoreDataTrait;
    use ItemRelationsTrait;
    use ItemSeoDataTrait;
    use PersonalizationTrait, PrioritySettingsTrait;
    use ProductPayloadTrait;
    use ProductVideoTrait;
    use TempProductTrait;

    private const MAX_SIDECAR_CATEGORIES = 100;

    /** Rows a search scans to build its category facet. See searchCategoryFacet(). */
    private const SEARCH_FACET_SCAN_LIMIT = 2000;

    /** Mirrors StoreCategoryService::ITEM_SEARCH_RELATIONS so both store listings match a search the same way. */
    private const STORE_ITEM_SEARCH_RELATIONS = [
        'translations' => 'value',
        'category' => 'name',
        'tags' => 'tag',
    ];

    public function getImagesByIds(array $ids): mixed
    {
        return $this->translationsUnscopedQuery()
            ->withStorage()
            ->whereIn('id', $ids)
            ->get(['id', 'image'])
            ->keyBy('id');
    }

    public function getStoreCategoryIdRows(array $storeIds): mixed
    {
        return DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->selectRaw('items.store_id, CAST(categories.id AS UNSIGNED) as id, categories.parent_id')
            ->whereIn('items.store_id', $storeIds)
            ->where('categories.status', 1)
            ->groupBy('items.store_id', 'id', 'categories.parent_id')
            ->get()
            ->groupBy('store_id');
    }

    public function getBannerItems(array $ids, array $zoneIds, mixed $moduleId): mixed
    {
        return Item::active()
            ->when($moduleId, fn ($query) => $query->whereHas('module.zones', fn ($z) => $z->whereIn('zones.id', $zoneIds)))
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    public function getUsedCategoryIds(mixed $query): array
    {
        return $query->whereNotNull('items.category_id')
            ->distinct()
            ->pluck('items.category_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function query(): mixed
    {
        return Item::query();
    }

    public function getPopularCategoryIds(): array
    {
        $averageOrders = $this->translationsUnscopedQuery()->where('order_count', '>=', 1)->avg('order_count') ?? 0;

        return $this->translationsUnscopedQuery()
            ->where('order_count', '>', $averageOrders)
            ->pluck('category_ids')
            ->flatMap(fn ($ids) => collect(Helpers::decodeJsonToArray($ids))->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    public function getByIdsWithStorage(array $ids): mixed
    {
        return $this->translationsUnscopedQuery()->with('storage')->whereIn('id', $ids)->get()->keyBy('id');
    }

    public function getStockProductsByIds(array $ids): mixed
    {
        return $this->unscopedWithModuleQuery()->whereIn('id', $ids)->get()->keyBy('id');
    }

    public function getOfferList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $module = config('module.current_module_data');
        $moduleId = $filters['module_id'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $zones = $filters['zone_ids'] ?? [];
        $categoryIds = $filters['category_ids'] ?? [];
        $brandIds = $filters['brand_ids'] ?? [];
        $search = $filters['search'] ?? null;
        $scopeInput = new Fluent($filters['scope_input'] ?? []);

        $additionalData = [
            'sort_by' => $filters['sort_by'] ?? 'default',
            'filter_by' => $filters['filter_by'] ?? [],
            'store_category_id' => $filters['store_category_id'] ?? null,
        ];

        $query = Item::withStorage()->with(['store.storage', 'storeCategory.storage', 'module.storage'])
            ->active()
            ->type($filters['type'] ?? 'all')
            ->when($module, fn ($qq) => $qq->where('module_id', $module['id']))
            ->when(! $module && is_numeric($moduleId), fn ($qq) => $qq->where('module_id', $moduleId))
            ->when(! empty($zones), function ($qq) use ($zones) {
                $qq->whereHas('store', fn ($q) => $q->whereIn('zone_id', $zones));
            })
            ->when(! empty($categoryIds), function ($qq) use ($categoryIds) {
                $qq->whereHas('category', function ($q) use ($categoryIds) {
                    $q->whereIn('id', $categoryIds)->orWhereIn('parent_id', $categoryIds);
                });
            })
            ->when(! empty($brandIds), function ($qq) use ($brandIds) {
                $qq->whereHas('ecommerce_item_details', function ($q) use ($brandIds) {
                    $q->whereHas('brand', fn ($b) => $b->whereIn('id', $brandIds));
                });
            })
            ->when($search, fn ($qq) => $qq->search(keywords: $search, relations: [
                'translations' => 'value',
                'tags' => 'tag',
                'category.parent' => 'name',
                'category' => 'name',
                'ecommerce_item_details.brand' => 'name',
            ]))
            ->Discounted()
            ->select('items.*')
            ->orderByDesc('discount')
            ->applyRating($scopeInput)
            ->applyFilters($additionalData)
            ->applySorting($additionalData['sort_by'])
            ->applyPriceRange($scopeInput);

        $query = $this->applyItemPersonalization($query, $userId, $additionalData['filter_by']);

        $paginator = $query->paginate($this->pageSize($paginate), ['items.*'], 'page', $this->pageNumber($paginate));

        $this->loadListRelations($paginator->getCollection());
        $this->flagWishlisted($paginator->getCollection(), $userId);

        return $paginator;
    }

    public function getStoreCategoryList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Item::where('store_category_id', $filters['store_category_id'] ?? null)
            ->where('store_id', $filters['store_id'] ?? null)
            ->latest()
            ->with($this->itemRelationSet())
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getAssignableList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $model = $filters['model'] ?? Item::class;

        return $model::query()
            ->where('store_id', $filters['store_id'] ?? null)
            ->where(function ($query) use ($filters) {
                $query->whereNull('store_category_id')
                    ->orWhere('store_category_id', $filters['store_category_id'] ?? null);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = '%'.trim($search).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('id', 'like', $term));
            })
            ->orderByDesc('id')
            ->with(['storage' => fn ($storage) => $storage->select(STORAGE_RELATION_COLUMNS)])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function unassignedCount(array $filters = []): int
    {
        $model = $filters['model'] ?? Item::class;

        return $model::query()
            ->where('store_id', $filters['store_id'] ?? null)
            ->whereNull('store_category_id')
            ->count();
    }

    public function syncStoreCategoryAssignment(array $filters, array $itemIds): int
    {
        $model = $filters['model'] ?? Item::class;
        $storeId = $filters['store_id'] ?? null;
        $categoryId = $filters['store_category_id'] ?? null;

        $allowedIds = [];

        if ($itemIds) {
            $allowedIds = $model::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $itemIds)
                ->where(function ($query) use ($categoryId) {
                    $query->whereNull('store_category_id')->orWhere('store_category_id', $categoryId);
                })
                ->pluck('id')
                ->all();
        }

        if ($allowedIds) {
            $model::query()->where('store_id', $storeId)->whereIn('id', $allowedIds)
                ->update(['store_category_id' => $categoryId]);
        }

        $model::query()
            ->where('store_id', $storeId)
            ->where('store_category_id', $categoryId)
            ->when($allowedIds, fn ($query) => $query->whereNotIn('id', $allowedIds))
            ->update(['store_category_id' => null]);

        return count($allowedIds);
    }

    public function getSuggestedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $interest = $filters['interest'] ?? null;
        $moduleId = $filters['module_id'] ?? null;

        $items = Item::active()
            ->when($interest, fn ($query) => $query->where(function ($group) use ($interest) {
                foreach ($interest as $categoryId) {
                    $group->orWhereJsonContains('category_ids', ['id' => (string) $categoryId]);
                }
            }))
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zoneIds))
            ->whereHas('store', function ($query) use ($zoneIds, $moduleId) {
                $query->when($moduleId, fn ($store) => $store->where('module_id', $moduleId)
                    ->whereHas('zone.modules', fn ($zone) => $zone->where('modules.id', $moduleId)))
                    ->whereIn('zone_id', $zoneIds);
            })
            ->when($interest == null, fn ($query) => $query->popular())
            ->with($this->itemRelationSet())
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $items;
    }

    public function getLatestList(array $filters = [], array $paginate = [])
    {
        $zone_id = $filters['zone_id'] ?? null;
        $store_id = $filters['store_id'] ?? null;
        $category_id = $filters['category_id'] ?? null;
        $type = $filters['type'] ?? null;
        $min = $filters['min'] ?? false;
        $max = $filters['max'] ?? false;
        $product_id = $filters['product_id'] ?? null;
        $filter = $filters['filter'] ?? null;
        $rating_count = $filters['rating_count'] ?? null;
        $store_category_id = $filters['store_category_id'] ?? null;

        $latest_items_default_status = 1;
        $settings = $this->sortSettings('latest_items');
        $latest_items_sort_by_general = $settings['general'];
        $latest_items_sort_by_unavailable = $settings['unavailable'];
        $latest_items_sort_by_temp_closed = $settings['temp_closed'];
        $zones = ! empty($zone_id) ? json_decode($zone_id, true) : null;

        if ($category_id != 0) {
            $category_id = explode(',', $category_id);
        }
        if ($min == false) {
            $min = 0.00000001;
        }

        $query = Item::when($category_id != 0, function ($q) use ($category_id) {
            $q->whereHas('category', function ($q) use ($category_id) {
                return $q->whereIn('id', $category_id)->orWhereIn('parent_id', $category_id);
            });
        })
            ->when(is_numeric($store_category_id), function ($q) use ($store_category_id) {
                $q->where('store_category_id', $store_category_id);
            })
            ->when(isset($product_id), function ($q) use ($product_id) {
                $q->where('id', '!=', $product_id);
            })
            ->when(empty($store_id), function ($q) use ($zones) {
                $q->whereHas('store', function ($query) use ($zones) {

                    $query->when(config('module.current_module_data'), function ($query) {
                        $query->where('module_id', config('module.current_module_data')['id'])
                            ->whereHas('zone.modules', function ($query) {
                                $query->where('modules.id', config('module.current_module_data')['id']);
                            });
                    });

                    $query->when(! empty($zones), function ($query) use ($zones) {
                        $query->whereIn('zone_id', $zones);
                    });
                });
            })
            ->when($min && $max, function ($query) use ($min, $max) {
                $query->whereBetween('price', [$min, $max]);
            })
            ->when(is_numeric($store_id), function ($qurey) use ($store_id) {
                $qurey->where('store_id', $store_id);
            })
            ->when(! is_numeric($store_id), function ($query) use ($store_id) {
                $query->whereHas('store', function ($q) use ($store_id) {
                    $q->where('slug', $store_id);
                });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()->type($type)
            ->when($filter && in_array('popular', $filter), function ($qurey) {
                $qurey->popular();
            })
            ->when($filter && in_array('high', $filter), function ($qurey) {
                $qurey->orderBy('price', 'DESC');
            })
            ->when($filter && in_array('low', $filter), function ($qurey) {
                $qurey->orderBy('price', 'asc');
            })
            ->when($filter && in_array('discounted', $filter), function ($qurey) {
                $qurey->Discounted();
            })
            ->when($rating_count, function ($query) use ($rating_count) {
                $query->where('avg_rating', '>=', $rating_count);
            })
            ->when($filter && in_array('available_now', $filter), function ($query) {
                $query->where(function ($q) {
                    $currentTime = now()->format('H:i:s');
                    $q->whereRaw('(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)', [$currentTime])
                        ->orWhereRaw('(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))', [$currentTime, $currentTime]);
                });
            });

        if ($latest_items_default_status == '1') {
            $query = $this->personalise($query, $filters);
            $query = $query->latest();
        } else {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                if ($latest_items_sort_by_unavailable == 'remove') {
                    $query = $query->where('stock', '>', 0);
                } elseif ($latest_items_sort_by_unavailable == 'last') {
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }

            }

            if ($latest_items_sort_by_temp_closed == 'remove') {
                $query = $query->having('temp_available', '>', 0);
            } elseif ($latest_items_sort_by_temp_closed == 'last') {
                $query = $query->orderByDesc('temp_available');
            }

            if ($latest_items_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($latest_items_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($latest_items_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($latest_items_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($latest_items_sort_by_general == 'latest_created') {
                $query = $query->latest();
            }
        }

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $query = Item::when($category_id != 0, function ($q) use ($category_id) {
            $q->whereHas('category', function ($q) use ($category_id) {
                return $q->whereId($category_id)->orWhere('parent_id', $category_id);
            });
        })
            ->when(isset($product_id), function ($q) use ($product_id) {
                $q->where('id', '!=', $product_id);
            })
            ->when(empty($store_id), function ($q) use ($zones) {

                $q->when(! empty($zones), function ($q) use ($zones) {
                    $q->whereHas('module.zones', function ($query) use ($zones) {
                        $query->whereIn('zones.id', $zones);
                    });
                });

                $q->whereHas('store', function ($query) use ($zones) {

                    $query->when(config('module.current_module_data'), function ($query) {
                        $query->where('module_id', config('module.current_module_data')['id'])
                            ->whereHas('zone.modules', function ($query) {
                                $query->where('modules.id', config('module.current_module_data')['id']);
                            });
                    });

                    $query->when(! empty($zones), function ($query) use ($zones) {
                        $query->whereIn('zone_id', $zones);
                    });
                });
            })
            ->when($min && $max, function ($query) use ($min, $max) {
                $query->whereBetween('price', [$min, $max]);
            })
            ->when(is_numeric($store_id), function ($qurey) use ($store_id) {
                $qurey->where('store_id', $store_id);
            })
            ->when(! is_numeric($store_id), function ($query) use ($store_id) {
                $query->whereHas('store', function ($q) use ($store_id) {
                    return $q->where('slug', $store_id);
                });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()->type($type);

        if ($latest_items_default_status == '1') {
            $query = $query->latest();
        } else {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                if ($latest_items_sort_by_unavailable == 'remove') {
                    $query = $query->where('stock', '>', 0);
                } elseif ($latest_items_sort_by_unavailable == 'last') {
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if ($latest_items_sort_by_temp_closed == 'remove') {
                $query = $query->having('temp_available', '>', 0);
            } elseif ($latest_items_sort_by_temp_closed == 'last') {
                $query = $query->orderByDesc('temp_available');
            }

            if ($latest_items_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($latest_items_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($latest_items_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($latest_items_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($latest_items_sort_by_general == 'latest_created') {
                $query = $query->latest();
            }
        }

        $item_categories = $query->pluck('category_id')->toArray();

        $item_categories = array_unique($item_categories);

        $categories = app(CategoryService::class)->getSidecarTree($item_categories, self::MAX_SIDECAR_CATEGORIES);

        $prices = Item::active()
            ->when(is_numeric($store_id), fn ($q) => $q->where('store_id', $store_id))
            ->when(! is_numeric($store_id), fn ($q) => $q->whereHas('store', fn ($q2) => $q2->where('slug', $store_id))
            )
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $min_price = $prices->min_price;
        $max_price = $prices->max_price;

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => $categories,
            'min_price' => $min_price,
            'max_price' => $max_price,
        ];
    }

    public function getRelatedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->relatedListPayload($filters, $paginate, fn ($query, $product) => $query->where('category_ids', $product->category_ids));
    }

    public function getRelatedStoreList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->relatedListPayload($filters, $paginate, fn ($query, $product) => $query->where('store_id', $product->store_id));
    }

    public function getRecommendedList(array $filters = [], array $paginate = [])
    {
        $zone_id = $filters['zone_id'] ?? null;
        $store_id = $filters['store_id'] ?? null;
        $type = $filters['type'] ?? 'all';
        $filter = $filters['filter'] ?? 'all';
        $store_category_id = $filters['store_category_id'] ?? null;

        $zones = ! empty($zone_id) ? json_decode($zone_id, true) : null;

        $query = Item::when(isset($store_id), function ($q) use ($store_id) {
            $q->where('store_id', $store_id);
        })
            ->when(is_numeric($store_category_id), function ($q) use ($store_category_id) {
                $q->where('store_category_id', $store_category_id);
            })
            ->active(
                zone_ids: empty($store_id) ? $zones : null,
                module_id: empty($store_id) ? (config('module.current_module_data')['id'] ?? null) : null,
            )
            ->type($type)
            ->Recommended()
            ->when($filter === 'new_arrival', fn ($q) => $q->latest())
            ->when($filter === 'top_rated', fn ($q) => $q->withCount('reviews')->orderBy('reviews_count', 'desc'))
            ->when($filter === 'best_selling', fn ($q) => $q->popular());

        if ($filter === 'all') {
            $query = $this->personalise($query, $filters, withFilter: false);
        }

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
        ];
    }

    public function getPopularList(array $filters = [], array $paginate = []): array
    {
        return $this->listPayload($filters, $paginate, [
            'setting' => 'popular_item',
            'scope' => fn ($q) => $q,
            'order' => fn ($q) => $q->orderByDesc('order_count'),
            'general_default' => fn ($q) => $q->orderByDesc('order_count'),
        ]);
    }

    public function getMostReviewedList(array $filters = [], array $paginate = []): array
    {
        return $this->listPayload($filters, $paginate, [
            'setting' => 'best_reviewed_item',
            'scope' => fn ($q) => $q->withCount('reviews')->having('reviews_count', '>', 0),
            'order' => fn ($q) => $q->orderBy('reviews_count', 'desc'),
        ]);
    }

    public function getTopRatedList(array $filters = [], array $paginate = []): array
    {
        return $this->listPayload($filters, $paginate, [
            'setting' => 'best_reviewed_item',
            'with_count' => ['reviews'],
            'scope' => fn ($q) => $q->where('avg_rating', '>', 0),
            'order' => fn ($q) => $q->orderByDesc('avg_rating')->orderByDesc('rating_count')->orderByDesc('reviews_count'),
            'general_default' => fn ($q) => $q->orderByDesc('avg_rating')->orderByDesc('rating_count'),
        ]);
    }

    public function getRecentlyViewedList(array $filters = [], array $paginate = [])
    {
        $zone_id = $filters['zone_id'] ?? null;
        $type = $filters['type'] ?? 'all';
        $category_ids = $filters['category_ids'] ?? null;
        $filter = $filters['filter'] ?? null;
        $min = $filters['min'] ?? 0;
        $max = $filters['max'] ?? false;
        $rating_count = $filters['rating_count'] ?? null;
        $search = $filters['search'] ?? null;
        $store_category_id = $filters['store_category_id'] ?? null;

        $category_ids = isset($category_ids) ? (is_array($category_ids) ? $category_ids : json_decode($category_ids)) : [];
        $withCount = [];

        if ($filter && in_array('top_rated', $filter)) {
            $withCount[] = 'reviews';
        }
        if ($filter && in_array('most_loved', $filter)) {
            $withCount[] = 'whislists';
        }

        $visitorLogQuery = DB::table('visitor_logs')
            ->selectRaw('visitor_log_id, SUM(visit_count) as total_view_count')
            ->where('visitor_log_type', Item::class)
            ->groupBy('visitor_log_id');

        $zones = self::decodeValidZoneIds($zone_id);

        $query = Item::with('store')
            ->joinSub($visitorLogQuery, 'visitor_log_summary', function ($join) {
                $join->on('visitor_log_summary.visitor_log_id', '=', 'items.id');
            })
            ->select(['items.*'])
            ->selectRaw('COALESCE(visitor_log_summary.total_view_count, 0) as total_view_count')
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(! $zones, fn ($q) => $q->whereRaw('0 = 1'))
            ->type($type);

        $query = $query->filterList($filter, $min ?? 0, $max, $category_ids, $rating_count, $withCount, $search, $store_category_id);
        $query = $query->orderByDesc('total_view_count')->latest('items.created_at');

        $query = $this->personalise($query, $filters);

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => self::getCategoryData($paginator->items()),
        ];
    }

    public function getCartSuggestionList(array $filters = [], array $paginate = [])
    {
        $zone_id = $filters['zone_id'] ?? null;
        $store_id = $filters['store_id'] ?? null;
        $type = $filters['type'] ?? 'all';
        $recomended = $filters['recomended'] ?? false;

        $zoneIds = self::decodeValidZoneIds($zone_id);

        $query = Item::where('store_id', $store_id)
            ->active(
                zone_ids: $zoneIds,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(! $zoneIds, fn ($q) => $q->whereRaw('0 = 1'))
            ->type($type)
            ->whereHas('store', function ($q) {
                $q->Weekday();
            })
            ->when($recomended, fn ($q) => $q->Recommended())
            ->withCount('reviews')
            ->orderBy('reviews_count', 'desc');

        $query = $this->personalise($query, $filters, withFilter: false);

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
        ];
    }

    public function getPopularBasicList(array $filters = [], array $paginate = [])
    {
        $zone_id = $filters['zone_id'] ?? null;
        $type = $filters['type'] ?? null;
        $store_id = $filters['store_id'] ?? null;
        $category_id = $filters['category_id'] ?? null;
        $min = $filters['min'] ?? false;
        $max = $filters['max'] ?? false;
        $product_id = $filters['product_id'] ?? null;

        $basic_medicine_default_status = app(BusinessSettingService::class)->value('basic_medicine_default_status') ?? 1;
        $settings = $this->sortSettings('basic_medicine');
        $basic_medicine_sort_by_general = $settings['general'];
        $basic_medicine_sort_by_unavailable = $settings['unavailable'];
        $basic_medicine_sort_by_temp_closed = $settings['temp_closed'];

        if (isset($category_id) && ($category_id != 0)) {
            $category_id = explode(',', $category_id);
        }
        $query = Item::active()->type($type)
            ->whereHas('pharmacy_item_details', function ($query) {
                $query->where('is_basic', 1);
            })
            ->when(isset($category_id) && ($category_id != 0), function ($q) use ($category_id) {
                $q->whereHas('category', function ($q) use ($category_id) {
                    return $q->whereIn('id', $category_id)->orWhereIn('parent_id', $category_id);
                });
            })
            ->when(isset($product_id), function ($q) use ($product_id) {
                $q->where('id', '!=', $product_id);
            })
            ->whereHas('module.zones', function ($query) use ($zone_id) {
                $query->whereIn('zones.id', json_decode($zone_id, true));
            })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->when($min && $max, function ($query) use ($min, $max) {
                $query->whereBetween('price', [$min, $max]);
            })
            ->when(isset($store_id) && is_numeric($store_id), function ($qurey) use ($store_id) {
                $qurey->where('store_id', $store_id);
            })
            ->when(isset($store_id) && (! is_numeric($store_id)), function ($query) use ($store_id) {
                $query->whereHas('store', function ($q) use ($store_id) {
                    return $q->where('slug', $store_id);
                });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()->type($type);

        if ($basic_medicine_default_status == '1') {
            $query = $query->popular();
        } else {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                if ($basic_medicine_sort_by_unavailable == 'remove') {
                    $query = $query->where('stock', '>', 0);
                } elseif ($basic_medicine_sort_by_unavailable == 'last') {
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if ($basic_medicine_sort_by_temp_closed == 'remove') {
                $query = $query->having('temp_available', '>', 0);
            } elseif ($basic_medicine_sort_by_temp_closed == 'last') {
                $query = $query->orderByDesc('temp_available');
            }

            if ($basic_medicine_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($basic_medicine_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($basic_medicine_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($basic_medicine_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($basic_medicine_sort_by_general == 'order_count') {
                $query = $query->orderByDesc('order_count');
            }

        }

        $query = $this->personalise($query, $filters, withFilter: false);

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $categories = self::getCategoryData($paginator->items());

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => $categories,
        ];
    }

    public function getOrganicList(array $filters = [], array $paginate = []): array
    {
        return $this->listPayload($filters, $paginate, [
            'setting' => 'latest_items',
            'always_personalised' => true,
            'personalise' => false,
            'store_category_filter' => false,
            'scope' => fn ($q) => $q->where('organic', 1)
                ->whereHas('store', function ($query) use ($filters) {
                    $filter = $filters['filter'] ?? null;
                    $query->when($filter && in_array('free_delivery', $filter), fn ($s) => $s->where('free_delivery', 1))
                        ->when($filter && in_array('coupon', $filter), fn ($s) => $s->has('activeCoupons'));
                }),
            'order' => fn ($q) => $q->latest(),
        ]);
    }

    public function getNewArrivalList(array $filters = [], array $paginate = [])
    {
        $zone_id = $filters['zone_id'] ?? null;
        $type = $filters['type'] ?? null;
        $min = $filters['min'] ?? false;
        $max = $filters['max'] ?? false;
        $product_id = $filters['product_id'] ?? null;
        $filter = $filters['filter'] ?? null;
        $rating_count = $filters['rating_count'] ?? null;
        $category_ids = $filters['category_ids'] ?? null;
        $brand_ids = $filters['brand_ids'] ?? null;
        $store_category_id = $filters['store_category_id'] ?? null;

        $latest_items_default_status = 1;
        $settings = $this->sortSettings('latest_items');
        $latest_items_sort_by_general = $settings['general'];
        $latest_items_sort_by_unavailable = $settings['unavailable'];
        $latest_items_sort_by_temp_closed = $settings['temp_closed'];

        $category_ids = isset($category_ids) ? (is_array($category_ids) ? $category_ids : json_decode($category_ids)) : [];
        $brand_ids = isset($brand_ids) ? (is_array($brand_ids) ? $brand_ids : json_decode($brand_ids)) : [];
        $filter = $filter ? (is_array($filter) ? $filter : str_getcsv(trim($filter, '[]'), ',')) : '';
        $query = Item::when(isset($product_id), function ($q) use ($product_id) {
            $q->where('id', '!=', $product_id);
        })
            ->when(isset($category_ids) && (count($category_ids) > 0), function ($query) use ($category_ids) {
                $query->whereHas('category', function ($q) use ($category_ids) {
                    return $q->whereIn('id', $category_ids)->orWhereIn('parent_id', $category_ids);
                });
            })
            ->when(is_numeric($store_category_id), function ($query) use ($store_category_id) {
                $query->where('store_category_id', $store_category_id);
            })
            ->when(isset($brand_ids) && (count($brand_ids) > 0), function ($query) use ($brand_ids) {
                $query->whereHas('ecommerce_item_details', function ($q) use ($brand_ids) {
                    return $q->whereHas('brand', function ($q) use ($brand_ids) {
                        return $q->whereIn('id', $brand_ids);
                    });
                });
            })
            ->whereHas('module.zones', function ($query) use ($zone_id) {
                $query->whereIn('zones.id', json_decode($zone_id, true));
            })
            ->whereHas('store', function ($query) use ($zone_id, $filter) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zone_id, true))
                    ->when($filter && in_array('free_delivery', $filter), function ($qurey) {
                        return $qurey->where('free_delivery', 1);
                    })
                    ->when($filter && in_array('coupon', $filter), function ($qurey) {
                        return $qurey->has('activeCoupons');
                    });
            })
            ->when($rating_count, function ($query) use ($rating_count) {
                $query->where('avg_rating', '>=', $rating_count);
            })
            ->when($min && $max, function ($query) use ($min, $max) {
                $query->whereBetween('price', [$min, $max]);
            })
            ->when($filter && in_array('top_rated', $filter), function ($qurey) {
                $qurey->withCount('reviews')->orderBy('reviews_count', 'desc');
            })
            ->when($filter && in_array('popular', $filter), function ($qurey) {
                $qurey->popular();
            })
            ->when($filter && in_array('high', $filter), function ($qurey) {
                $qurey->orderBy('price', 'desc');
            })
            ->when($filter && in_array('low', $filter), function ($qurey) {
                $qurey->orderBy('price', 'asc');
            })
            ->when($filter && in_array('discounted', $filter), function ($qurey) {
                $qurey->Discounted()->orderBy('discount', 'desc');
            })
            ->when($filter && in_array('available_now', $filter), function ($query) {
                $query->where(function ($q) {
                    $currentTime = now()->format('H:i:s');
                    $q->whereRaw('(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)', [$currentTime])
                        ->orWhereRaw('(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))', [$currentTime, $currentTime]);
                });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()->type($type);

        if ($latest_items_default_status == '1') {
            $query = $this->personalise($query, $filters);
            $query = $query->latest();
        } else {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                if ($latest_items_sort_by_unavailable == 'remove') {
                    $query = $query->where('stock', '>', 0);
                } elseif ($latest_items_sort_by_unavailable == 'last') {
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if ($latest_items_sort_by_temp_closed == 'remove') {
                $query = $query->having('temp_available', '>', 0);
            } elseif ($latest_items_sort_by_temp_closed == 'last') {
                $query = $query->orderByDesc('temp_available');
            }

            if ($latest_items_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($latest_items_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($latest_items_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($latest_items_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($latest_items_sort_by_general == 'latest_created') {
                $query = $query->latest();
            }
        }

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $item_categories = collect($paginator->items())->pluck('category_id')->unique()->toArray();

        $categories = app(CategoryService::class)->getSidecarTree($item_categories, self::MAX_SIDECAR_CATEGORIES);

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => $categories,
        ];
    }

    public function getDiscountedList(array $filters = [], array $paginate = []): array
    {
        return $this->listPayload($filters, $paginate, [
            'setting' => 'special_offer',
            'scope' => fn ($q) => $q->Discounted(),
            'free_delivery_filter' => true,
            'brand_filter' => true,
            'order' => fn ($q) => $q->orderBy('discount', 'desc'),
            'general_default' => fn ($q) => $q,
        ]);
    }

    public function loadListRelations(mixed $items): mixed
    {
        $collection = $items instanceof SupportCollection ? $items : collect($items);

        if ($collection->isNotEmpty()) {
            (new EloquentCollection($collection->all()))->loadMissing(['storage', 'unit', 'storeCategory', 'store.storage', 'store.discount', 'store.module.storage']);
        }

        return $items;
    }

    /**
     * Of these eager loads, the ones the given model actually defines.
     *
     * Only the FIRST segment is checked: `store.discount` needs `store` on this model, and
     * `discount` is then resolved against Store, which is not ours to vet. A missing first
     * segment drops the whole entry, nested or not.
     *
     * Deliberately silent — a relation absent from one of the two models this loader serves is
     * the normal case, not a mistake. It is not a licence to misspell one: a typo drops a
     * relation for BOTH models, and the resource that reads it fails loudly on the next line.
     *
     * @param  array<int|string, mixed>  $relations
     * @return array<int|string, mixed>
     */
    private function relationsDefinedOnModel(mixed $model, array $relations): array
    {
        if (! $model instanceof Model) {
            return $relations;
        }

        return collect($relations)
            ->filter(fn ($value, $key) => $model->isRelation(
                strtok((string) (is_int($key) ? $value : $key), '.')
            ))
            ->all();
    }

    public function loadDetailRelations(mixed $items): mixed
    {
        $collection = $items instanceof SupportCollection ? $items : collect($items);

        if ($collection->isEmpty()) {
            return $items;
        }

        $models = new EloquentCollection($collection->all());

        // This loader serves BOTH Item and ItemCampaign — `findDetail($id, campaign: true)`
        // returns the latter, and the campaign detail endpoint renders it through here.
        // ItemCampaign has no `rating`, `storeCategory`, `seoData`, `pharmacy_item_details`,
        // `ecommerce_item_details` or `flashSaleItems`, and `loadMissing()` THROWS on a relation
        // the model does not define rather than skipping it. That is why every
        // `items/details/{id}?campaign=1` request answered 404: the exception was caught by the
        // controller and reported as "Not Found".
        $models->loadMissing($this->relationsDefinedOnModel($models->first(), array_merge($this->itemRelationSet(), [
            'nutritions', 'allergies', 'generic', 'storeCategory',
        ])));
        $models->load('module');
        $models->loadMissing(['store' => fn ($q) => $q->withCount('campaigns')]);
        $models->loadMissing($this->relationsDefinedOnModel($models->first(), [
            'store.discount', 'store.storeConfig', 'store.storage', 'store.module',
            'storeCategory.storage', 'module.storage', 'pharmacy_item_details', 'seoData', 'taxVats',
            'rating', 'storage', 'unit', 'ecommerce_item_details.brand', 'flashSaleItems',
        ]));

        $this->attachAddOns($models);
        $this->attachCategoryNames($models);
        $this->attachTaxes($models);

        return $items;
    }

    public function isSetMenuSupported(): bool
    {
        return Schema::hasColumn((new Item)->getTable(), 'set_menu');
    }

    public function getSetMenuList(array $paginate = []): LengthAwarePaginator
    {
        $items = Item::active()
            ->where('set_menu', 1)
            ->where('status', 1)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadDetailRelations($items->getCollection());

        return $items;
    }

    public function applyReviewRating(mixed $itemId, int $rating): void
    {
        $item = $this->find($itemId);

        if (! $item) {
            return;
        }

        if ($item->store) {
            $item->store->rating = $this->updateRating($item->store->rating, $rating);
            $item->store->save();
        }

        $item->rating = self::updateRatingHistogram($item->rating, $rating);
        $item->avg_rating = self::averageRating(json_decode($item->rating, true));
        $item->save();
        $item->increment('rating_count');
    }

    public function exists(mixed $itemId): bool
    {
        return Item::where('id', $itemId)->exists();
    }

    public function findDetail(mixed $identifier, bool $campaign = false): mixed
    {
        if ($campaign) {
            return app(ItemCampaignService::class)->findActiveDetail($identifier);
        }

        return Item::withCount('whislists')
            ->with(['tags', 'nutritions', 'allergies', 'reviews', 'reviews.customer', 'reviews.customer.storage'])
            ->active()
            ->when(config('module.current_module_data'), function ($query) {
                $query->module(config('module.current_module_data')['id']);
            })
            ->when(is_numeric($identifier), fn ($query) => $query->where('id', $identifier))
            ->when(! is_numeric($identifier), fn ($query) => $query->where('slug', $identifier))
            ->first();
    }

    public function priceRange(mixed $storeId): array
    {
        return Item::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->selectRaw('MIN(price) AS min_price, MAX(price) AS max_price')
            ->get(['min_price', 'max_price'])
            ->toArray();
    }

    public function categoriesByIds(array $categoryIds): array
    {
        return app(CategoryService::class)->getByIdsWithStorageLimited($categoryIds, self::MAX_SIDECAR_CATEGORIES);
    }

    public function storeCategoryIds(mixed $storeId): array
    {
        $rows = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->selectRaw('categories.position as positions, IF((categories.position = "0"), categories.id, categories.parent_id) as categories')
            ->where('items.store_id', $storeId)
            ->where('categories.status', 1)
            ->groupBy('categories', 'positions')
            ->limit(self::MAX_SIDECAR_CATEGORIES)
            ->get();

        return array_map('intval', $rows->pluck('categories')->toArray());
    }

    public function getSuggestionList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $zoneId = $filters['zone_id'] ?? null;
        $zones = json_decode((string) $zoneId, true) ?: [];
        $key = explode(' ', (string) ($filters['name'] ?? ''));

        $items = Item::active()->type($filters['type'] ?? 'all')
            ->when($filters['category_id'] ?? null, function ($query) use ($filters) {
                $query->whereHas('category', function ($q) use ($filters) {
                    return $q->whereId($filters['category_id'])->orWhere('parent_id', $filters['category_id']);
                });
            })
            ->when($filters['store_category_id'] ?? null, fn ($query) => $query->where('store_category_id', $filters['store_category_id']))
            ->when($filters['store_id'] ?? null, fn ($query) => $query->where('store_id', $filters['store_id']))
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zones))
            ->whereHas('store', function ($query) use ($zones) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])
                        ->whereHas('zone.modules', fn ($q) => $q->where('modules.id', config('module.current_module_data')['id']));
                })->whereIn('zone_id', $zones);
            })
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
                foreach ([
                    'translations' => 'value',
                    'tags' => 'tag',
                    'nutritions' => 'nutrition',
                    'allergies' => 'allergy',
                    'generic' => 'generic_name',
                ] as $relation => $column) {
                    $q->orWhereHas($relation, function ($query) use ($key, $column) {
                        $query->where(function ($q) use ($key, $column) {
                            foreach ($key as $value) {
                                $q->where($column, 'like', "%{$value}%");
                            }
                        });
                    });
                }
            })
            ->withStorage()->with('unit')->select(['name', 'image', 'unit_id'])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $items->getCollection()->each->makeHidden('unit_id');

        return $items;
    }

    public function getBrandItemQuery(array $filters, array $sortSpec): mixed
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;
        $brand = $filters['brand'] ?? null;

        $query = Item::translateOnly(['name'])
            ->whereHas('module.zones', fn ($q) => $q->whereIn('zones.id', $zoneIds))
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->whereHas('store', function ($q) use ($zoneIds, $moduleId) {
                $q->whereIn('zone_id', $zoneIds)
                    ->whereHas('zone.modules', fn ($q) => $q->when($moduleId, fn ($q) => $q->where('modules.id', $moduleId)));
            })
            ->whereHas('ecommerce_item_details', fn ($q) => $q->whereHas('brand', fn ($q) => $this->matchBrand($q, $brand)))
            ->select('items.*')
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()
            ->type($filters['type'] ?? 'all');

        return $this->applyBrandItemSort($query, $sortSpec);
    }

    public function getCommonConditionOrderCountQuery(array $filters = []): mixed
    {
        return Item::orderCountsByCommonCondition(
            $filters['zone_ids'] ?? [],
            $filters['module_id'] ?? null,
            $filters['type'] ?? 'all'
        );
    }

    public function getBrandOrderCountQuery(array $filters = []): mixed
    {
        $brandItems = app(EcommerceItemDetailsService::class)->getBrandItemQuery();

        return $this->translationsUnscopedQuery()
            ->active(zone_ids: $filters['zone_ids'] ?? null, module_id: $filters['module_id'] ?? null)
            ->joinSub($brandItems, 'brand_items', 'brand_items.item_id', '=', 'items.id')
            ->groupBy('brand_items.brand_id')
            ->selectRaw('brand_items.brand_id as brand_id, SUM(items.order_count) as order_count');
    }

    public function getPriceRangeForStore(mixed $storeId): array
    {
        return Item::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->selectRaw('MIN(price) AS min_price, MAX(price) AS max_price')
            ->toBase()->get()->toArray();
    }

    public function getActiveForStores(array $storeIds): mixed
    {
        return Item::whereIn('store_id', $storeIds)->active()
            ->without(['translations', 'storeCategory'])
            ->get(['store_id', 'category_ids', 'order_count']);
    }

    public function getCommonConditionList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Item::translateOnly(['name'])
            ->servableIn($filters['zone_ids'] ?? [], $filters['module_id'] ?? null)
            ->whereHas('pharmacy_item_details', fn ($q) => $q->whereHas(
                'common_condition',
                fn ($c) => is_numeric($filters['condition'] ?? null)
                    ? $c->whereId($filters['condition'])
                    : $c->where('slug', $filters['condition'] ?? null)
            ))
            ->active()
            ->type($filters['type'] ?? 'all')
            ->latest()
            ->with($this->itemRelationSet())
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getStoreConditionList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $zones = json_decode((string) ($filters['zone_id'] ?? ''), true) ?: null;
        $storeId = $filters['store_id'] ?? null;

        $query = Item::when(empty($storeId), function ($query) use ($zones) {
            $query->when(! empty($zones), function ($query) use ($zones) {
                $query->whereHas('module.zones', fn ($q) => $q->whereIn('zones.id', $zones));
            });

            $query->whereHas('store', function ($q) use ($zones) {
                $q->when(! empty($zones), fn ($q) => $q->whereIn('zone_id', $zones));
                $q->whereHas('zone.modules', function ($q) {
                    $q->when(config('module.current_module_data'), fn ($q) => $q->where('modules.id', config('module.current_module_data')['id']));
                });
            });
        })
            ->when(is_numeric($storeId), fn ($query) => $query->where('store_id', $storeId))
            ->when($storeId !== null && ! is_numeric($storeId), function ($query) use ($storeId) {
                $query->whereHas('store', fn ($q) => $q->where('slug', $storeId));
            })
            ->when($filters['store_category_id'] ?? null, fn ($query) => $query->where('store_category_id', $filters['store_category_id']))
            ->whereHas('pharmacy_item_details', fn ($q) => $q->whereNotNull('common_condition_id'))
            ->whereHas('ecommerce_item_details', fn ($q) => $q->whereNotNull('brand_id'))
            ->active()
            ->type($filters['type'] ?? 'all');

        $query = $this->personalise($query, $filters, withFilter: false);

        $paginator = $query->latest()->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadDetailRelations($paginator->getCollection());

        return $paginator;
    }

    public function getSearchList(array $filters = [], array $paginate = []): array
    {
        $zones = json_decode((string) ($filters['zone_id'] ?? ''), true) ?: [];
        $categoryIds = $filters['category_ids'] ?? '';
        $brandIds = $filters['brand_ids'] ?? [];
        $filter = is_array($filters['filter'] ?? null) ? $filters['filter'] : [];
        $scopeInput = new Fluent($filters['scope_input'] ?? []);
        $additionalData = ['sort_by' => $filters['sort_by'] ?? 'default', 'filter_by' => $filters['filter_by'] ?? []];

        $defaultStatus = app(BusinessSettingService::class)->value('product_search_default_status') ?? 1;
        $sortUnavailable = $this->findPrioritySetting(name: 'product_search_sort_by_unavailable', type: 'unavailable');
        $sortTempClosed = $this->findPrioritySetting(name: 'product_search_sort_by_temp_closed', type: 'temp_closed');

        $query = Item::active()->type($filters['type'] ?? 'all')
            ->with('store', function ($query) {
                $query->withCount(['campaigns' => fn ($q) => $q->Running()]);
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available');

        if ($defaultStatus != '1') {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                if ($sortUnavailable == 'remove') {
                    $query = $query->where('stock', '>', 0);
                } elseif ($sortUnavailable == 'last') {
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if ($sortTempClosed == 'remove') {
                $query = $query->having('temp_available', '>', 0);
            } elseif ($sortTempClosed == 'last') {
                $query = $query->orderByDesc('temp_available');
            }
        }

        $query = $query
            ->when($filters['category_id'] ?? null, function ($query) use ($filters) {
                $query->whereHas('category', function ($q) use ($filters) {
                    return $q->whereId($filters['category_id'])->orWhere('parent_id', $filters['category_id']);
                });
            })
            ->when($categoryIds, function ($query) use ($categoryIds) {
                $query->whereHas('category', function ($q) use ($categoryIds) {
                    return $q->whereIn('id', $categoryIds)->orWhereIn('parent_id', $categoryIds);
                });
            })
            ->when($filters['store_category_id'] ?? null, fn ($query) => $query->where('store_category_id', $filters['store_category_id']))
            ->when($filters['store_id'] ?? null, fn ($query) => $query->where('store_id', $filters['store_id']))
            ->when($brandIds, fn ($query) => $query->whereHas('ecommerce_item_details.brand', fn ($q) => $q->whereIn('id', $brandIds)))
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zones))
            ->whereHas('store', function ($query) use ($zones, $filter) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])
                        ->whereHas('zone.modules', fn ($q) => $q->where('modules.id', config('module.current_module_data')['id']));
                })->whereIn('zone_id', $zones)
                    ->when(in_array('coupon', $filter), fn ($q) => $q->has('activeCoupons'));
            })
            ->search(keywords: $filters['name'] ?? '', relations: [
                'translations' => 'value',
                'tags' => 'tag',
                'nutritions' => 'nutrition',
                'allergies' => 'allergy',
                'category.parent' => 'name',
                'category' => 'name',
                'generic' => 'generic_name',
                'ecommerce_item_details.brand' => 'name',
                'pharmacy_item_details.common_condition' => 'name',
            ])
            ->when(in_array('available_now', $filter), fn ($query) => $query->availableNow())
            ->applyRating($scopeInput)
            ->applyFilters($additionalData)
            ->applySorting($additionalData['sort_by'])
            ->applyPriceRange($scopeInput);

        $query = $this->personalise($query, $filters);

        // The search predicate ORs the name against nine relations, so nothing can narrow it
        // and every pass over it scans the item table. This request used to make three such
        // passes -- the facet scan, the paginator's count, and the page fetch -- each costing
        // about the same. The facet scan already reads one row per match, so when the match set
        // fits inside its bound it also knows the exact total, and the count pass can be
        // skipped. Bounded because an unbounded pluck here exhausted the memory limit before
        // paginate() was ever reached, returning an empty 500 with nothing in the log.
        [$itemCategories, $exactTotal] = $this->searchCategoryFacet($query->clone());

        $perPage = $this->pageSize($paginate);
        $page = $this->pageNumber($paginate);

        $paginator = $exactTotal === null
            ? $query->paginate($perPage, ['*'], 'page', $page)
            : new LengthAwarePaginator(
                $query->forPage($page, $perPage)->get(),
                $exactTotal,
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
            );

        $this->loadDetailRelations($paginator->getCollection());

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => $this->searchCategories($itemCategories),
        ];
    }

    public function getCombinedList(array $filters = [], array $paginate = []): array
    {
        $zones = json_decode((string) ($filters['zone_id'] ?? ''), true) ?: [];
        $module = config('module.current_module_data');
        $filter = is_array($filters['filter'] ?? null) ? $filters['filter'] : [];
        $scopeInput = new Fluent($filters['scope_input'] ?? []);
        $additionalData = ['sort_by' => $filters['sort_by'] ?? 'default', 'filter_by' => $filters['filter_by'] ?? []];

        $paginator = Item::with(['module', 'store'])
            ->active()
            ->type($filters['type'] ?? 'all')
            ->whereHas('module')
            ->whereHas('store')
            ->when($module, fn ($query) => $query->where('module_id', $module['id']))
            ->whereHas('store', function ($query) use ($zones, $module, $filter) {
                if (! empty($zones) && (! $module || ! ($module['all_zone_service'] ?? false))) {
                    $query->whereIn('zone_id', $zones);
                }
                $query->when(in_array('coupon', $filter), fn ($q) => $q->has('activeCoupons'));
            })
            ->select('items.*')
            ->when(in_array('available_now', $filter), fn ($query) => $query->availableNow())
            ->applyRating($scopeInput)
            ->applyFilters($additionalData)
            ->applySorting($additionalData['sort_by'])
            ->applyPriceRange($scopeInput)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadDetailRelations($paginator->getCollection());

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => [],
        ];
    }

    public function getBrandList(array $filters = [], array $paginate = []): array
    {
        $zones = json_decode((string) ($filters['zone_id'] ?? ''), true) ?: [];
        $categoryIds = $filters['category_ids'] ?? [];
        $brandIds = $filters['brand_ids'] ?? [];
        $filter = is_array($filters['filter'] ?? null) ? $filters['filter'] : [];
        $min = $filters['min'] ?? false;
        $max = $filters['max'] ?? false;

        $paginator = Item::whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zones))
            ->when($categoryIds, function ($query) use ($categoryIds) {
                $query->whereHas('category', fn ($q) => $q->whereIn('id', $categoryIds)->orWhereIn('parent_id', $categoryIds));
            })
            ->when(is_numeric($filters['store_category_id'] ?? null), fn ($query) => $query->where('store_category_id', $filters['store_category_id']))
            ->when($brandIds, fn ($query) => $query->whereHas('ecommerce_item_details.brand', fn ($q) => $q->whereIn('id', $brandIds)))
            ->whereHas('store', function ($query) use ($zones, $filter) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])
                        ->whereHas('zone.modules', fn ($q) => $q->where('modules.id', config('module.current_module_data')['id']));
                })->whereIn('zone_id', $zones)
                    ->when(in_array('free_delivery', $filter), fn ($q) => $q->where('free_delivery', 1))
                    ->when(in_array('coupon', $filter), fn ($q) => $q->has('activeCoupons'));
            })
            ->active()
            ->type($filters['type'] ?? 'all')
            ->when($filters['rating_count'] ?? null, fn ($query) => $query->where('avg_rating', '>=', $filters['rating_count']))
            ->when($min && $max, fn ($query) => $query->whereBetween('price', [$min, $max]))
            ->when(in_array('top_rated', $filter), fn ($query) => $query->withCount('reviews')->orderBy('reviews_count', 'desc'))
            ->when(in_array('popular', $filter), fn ($query) => $query->popular())
            ->when(in_array('high', $filter), fn ($query) => $query->orderBy('price', 'desc'))
            ->when(in_array('low', $filter), fn ($query) => $query->orderBy('price', 'asc'))
            ->when(in_array('available_now', $filter), fn ($query) => $query->availableNow())
            ->when(in_array('discounted', $filter), fn ($query) => $query->Discounted()->orderBy('discount', 'desc'))
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadDetailRelations($paginator->getCollection());

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => $this->searchCategories(
                array_unique(collect($paginator->items())->pluck('category_id')->all())
            ),
        ];
    }

    public function searchSuggestList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $zones = json_decode((string) ($filters['zone_id'] ?? ''), true) ?: [];
        $module = $filters['module'] ?? null;
        $scopeInput = new Fluent($filters['scope_input'] ?? []);

        $paginator = Item::active()->withStorage()->with(['module.storage'])
            ->without(['translations', 'storeCategory'])
            ->whereHas('store', function ($query) use ($zones, $module) {
                $query->when($module, function ($query) use ($module) {
                    $query->where('module_id', $module['id'])
                        ->whereHas('zone.modules', fn ($q) => $q->where('modules.id', $module['id']));
                })->whereIn('zone_id', $zones);
            })
            ->when($filters['store_category_id'] ?? null, fn ($query) => $query->where('store_category_id', $filters['store_category_id']))
            ->search(keywords: $filters['name'] ?? '', relations: [
                'translations' => 'value',
                'tags' => 'tag',
                'nutritions' => 'nutrition',
                'allergies' => 'allergy',
                'category.parent' => 'name',
                'category' => 'name',
                'generic' => 'generic_name',
                'ecommerce_item_details.brand' => 'name',
                'pharmacy_item_details.common_condition' => 'name',
            ], mainCol: ['name', 'description'])
            ->applyPriceRange($scopeInput)
            ->select(['id', 'name', 'image', 'module_id'])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'image' => $item->image,
                'image_full_url' => $item->image_full_url,
                'module_id' => $item->module_id,
                'module' => $this->moduleSummary($item->module),
            ])
        );
    }

    public function getEmptyList(array $paginate = []): array
    {
        $paginator = new LengthAwarePaginator([], 0, $this->pageSize($paginate), $this->pageNumber($paginate));

        return [
            'paginator' => $paginator,
            'total_size' => 0,
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => [],
            'categories' => [],
        ];
    }

    public function getRecentOrderedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $zones = json_decode((string) ($filters['zone_id'] ?? ''), true) ?: [];
        $userId = $filters['user_id'] ?? null;
        $moduleId = $filters['module_id'] ?? null;

        $paginator = Item::with('store')
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->when(! $moduleId && $this->configModuleId(), fn ($query) => $query->where('module_id', $this->configModuleId()))
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zones))
            ->whereHas('store', function ($query) use ($zones, $moduleId) {
                $scopedModule = $moduleId ?: $this->configModuleId();

                $query->whereIn('zone_id', $zones)
                    ->when($scopedModule, fn ($store) => $store->where('module_id', $scopedModule)
                        ->whereHas('zone.modules', fn ($module) => $module->where('modules.id', $scopedModule)));
            })
            ->whereHas('orders.order', fn ($query) => $this->constrainCustomerOrders($query, $userId, $moduleId))
            ->select(['items.*'])
            ->selectSub(function ($subQuery) use ($userId, $moduleId) {
                $subQuery->from('order_details')
                    ->join('orders', 'orders.id', '=', 'order_details.order_id')
                    ->selectRaw('MAX(orders.created_at)')
                    ->whereColumn('order_details.item_id', 'items.id');

                $this->constrainCustomerOrders($subQuery, $userId, $moduleId, 'orders.');
            }, 'latest_ordered_at')
            ->active()
            ->type($filters['type'] ?? 'all')
            ->orderByDesc('latest_ordered_at')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadListRelations($paginator->getCollection());

        return $paginator;
    }

    /**
     * $filters is the array StoreItemFiltersTrait builds, i.e. the same surface
     * /store-categories/items accepts. Ordering follows that endpoint too: whatever
     * filter_by asked for is reset, popularity is the base order, and an explicit
     * sort_by replaces it.
     */
    public function getStorePopularList(mixed $storeIdentifier, array $paginate = [], array $filters = []): LengthAwarePaginator
    {
        $inputs = new Fluent($filters['inputs'] ?? []);
        $ratingCount = $filters['inputs']['rating_count'] ?? null;

        $paginator = Item::when(is_numeric($storeIdentifier), fn ($query) => $query->where('store_id', $storeIdentifier))
            ->when(! is_numeric($storeIdentifier), function ($query) use ($storeIdentifier) {
                $query->whereHas('store', fn ($q) => $q->where('slug', $storeIdentifier));
            })
            ->active(zone_ids: $filters['zone_ids'] ?? null, module_id: $filters['module_id'] ?? null)
            ->type($filters['type'] ?? 'all')
            ->applyFilters(['filter_by' => $filters['filter_by'] ?? []])
            ->applyRating($inputs)
            ->applyPriceRange($inputs)
            ->when($ratingCount && is_numeric($ratingCount), fn ($query) => $query->where('avg_rating', '<', (int) $ratingCount + 1))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->search($search, self::STORE_ITEM_SEARCH_RELATIONS))
            ->reorder()
            ->popular()
            ->applySorting($filters['sort_by'] ?? 'default')
            // Last key in every ordering, including the ones applySorting reorders to.
            // order_count ties are common, and without it MySQL was free to place a tied
            // row on more than one page -- or on none.
            ->orderBy('items.id', 'asc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadListRelations($paginator->getCollection());

        return $paginator;
    }

    public function getStoreSearchList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $terms = array_filter(explode(' ', $filters['keyword'] ?? ''));

        return $this->storeScopeUnscopedQuery()
            ->withStorage()
            ->with(['module', 'store.storeConfig', 'store.discount'])
            ->where('store_id', $filters['store_id'] ?? null)
            ->when($terms !== [], fn ($query) => $query->where(function ($inner) use ($terms) {
                foreach ($terms as $term) {
                    $inner->orWhere('name', 'like', "%{$term}%");
                }
            }))
            ->active()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getVendorSearchList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $keywords = explode(' ', (string) ($filters['name'] ?? ''));

        $items = Item::active()
            ->with(['rating' => fn ($query) => $query->where('status', 1)])
            ->where('store_id', $filters['store_id'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->whereHas(
                'category',
                fn ($category) => $category->whereId($categoryId)->orWhere('parent_id', $categoryId)
            ))
            ->when($filters['store_category_id'] ?? null, fn ($query, $storeCategoryId) => $query->where('store_category_id', $storeCategoryId))
            ->when($filters['requested_store_id'] ?? null, fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('name', 'like', "%{$keyword}%");
                }

                $query->orWhereHas('tags', fn ($tags) => $tags->where(function ($tag) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $tag->where('tag', 'like', "%{$keyword}%");
                    }
                }));
            })
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadProductRelations($items->getCollection());

        return $items;
    }

    public function getLowStockList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $storeId = $filters['store_id'] ?? null;
        $categoryId = $filters['category_id'] ?? 'all';
        $warningStock = app(StoreConfigService::class)->findLowStockWarningLevel($storeId);

        if (! ($warningStock > 0)) {
            return $this->paginateCollection([], $paginate);
        }

        $items = Item::withStorage()
            ->where('store_id', $storeId)
            ->when(is_numeric($categoryId), fn ($query) => $query->whereHas(
                'category',
                fn ($category) => $category->whereId($categoryId)->orWhere('parent_id', $categoryId)
            ))
            ->type($filters['type'] ?? 'all')
            ->where('stock', '<=', $warningStock)
            ->orderBy('stock')
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadProductRelations($items->getCollection());

        return $items;
    }

    public function findForVendorDetail(mixed $itemId, mixed $storeId): ?Item
    {
        $item = $this->translationsUnscopedQuery()
            ->with([
                'tags', 'taxVats', 'module.storage', 'store.storage', 'store.discount', 'store.storeConfig',
                'storeCategory.storage', 'pharmacy_item_details', 'ecommerce_item_details.brand',
                'nutritions', 'allergies', 'generic', 'seoData', 'translations', 'unit', 'storage',
                'flashSaleItems' => fn ($query) => $query->active()->whereHas('flashSale', fn ($sale) => $sale->active()->running()),
            ])
            ->where('id', $itemId)
            ->where('store_id', $storeId)
            ->first();

        if ($item) {
            $this->attachAddOns(new EloquentCollection([$item]));
            $this->attachCategoryNames(new EloquentCollection([$item]));
        }

        return $item;
    }

    public function findForStore(mixed $itemId, mixed $storeId): ?Item
    {
        return Item::where('id', $itemId)->where('store_id', $storeId)->first();
    }

    public function createForStore(array $data, mixed $store): array
    {
        if (! $store->item_section) {
            return $this->denied();
        }

        $subscription = $this->guardSubscription($store);

        if ($subscription) {
            return $subscription;
        }

        $choiceOptions = $this->choiceOptions($data);
        $variations = $this->variations($data);

        if (isset($choiceOptions['error']) || isset($variations['error'])) {
            return ['status_code' => 422] + ($choiceOptions['error'] ?? $variations['error']);
        }

        $taxonomyIds = $this->taxonomyIds($data);
        $gallerySource = $this->gallerySource($data);
        $galleryImage = $gallerySource && ! array_key_exists('image', $data)
            ? FileStorage::copyStorageFile('product/', $gallerySource->image, FileStorage::getStorageDiskByKey($gallerySource, 'image', 'public'))
            : null;
        $categories = $this->categoryPayload($data);
        $moduleType = $store->module->module_type;
        $taxIds = $this->decodedInput($data['tax_ids'] ?? null);

        $item = new Item;
        $item->name = $data['translations'][0]['value'];
        $item->category_id = ($data['sub_category_id'] ?? null) ?: ($data['category_id'] ?? null);
        $item->category_ids = json_encode($categories);
        $item->store_category_id = $data['store_category_id'] ?? null ? (int) $data['store_category_id'] : null;
        $item->description = $data['translations'][1]['value'];
        $item->choice_options = json_encode($choiceOptions);
        $item->variations = json_encode($variations);
        $item->food_variations = json_encode($this->foodVariations($data));
        $item->price = $data['price'];
        $item->image = array_key_exists('image', $data) ? FileStorage::upload('product/', $data['image']) : $galleryImage;

        $videoData = $this->createdVideoData($data, $gallerySource);
        $item->video = $videoData['video'];
        $item->video_link = $videoData['video_link'];

        $item->available_time_starts = $data['available_time_starts'] ?? null;
        $item->available_time_ends = $data['available_time_ends'] ?? null;
        $item->discount = $data['discount'] ?? null;
        $item->discount_type = $data['discount_type'] ?? null;
        $item->maximum_cart_quantity = $data['maximum_cart_quantity'] ?? null;
        $item->attributes = $data['attribute_id'] ?? json_encode([]);
        $item->add_ons = json_encode($this->ownAddonIds($data['addon_ids'] ?? null, $store->id));
        $item->store_id = $store->id;
        $item->veg = $data['veg'] ?? null;
        $item->module_id = $store->module_id;
        $item->stock = $data['current_stock'] ?? null;
        $item->images = $this->galleryImages($data, $gallerySource);
        $item->unit_id = $data['unit'] ?? null;
        $item->organic = $data['organic'] ?? 0;
        $item->is_halal = $data['is_halal'] ?? 0;
        $item->save();

        $item->tags()->sync($taxonomyIds['tags']);
        $item->nutritions()->sync($taxonomyIds['nutritions']);
        $item->allergies()->sync($taxonomyIds['allergies']);

        $this->syncModuleDetails($item, $data, $moduleType, $taxonomyIds);

        $item->translations()->insert(array_map(fn ($row) => $row + [
            'translationable_type' => $item->getMorphClass(),
            'translationable_id' => $item->getKey(),
        ], $data['translations']));

        $this->createTaxables($item, $taxIds);

        if ($this->requiresApproval('Add_new_product')) {
            $this->storeTempProduct($item, $data, $taxonomyIds, $moduleType, taxIds: $taxIds);
            $item->is_approved = 0;
            $item->save();

            return ['status_code' => 200, 'message' => translate('messages.The product will be published once it receives approval from the admin.')];
        }

        if ($moduleType === 'ecommerce') {
            $this->syncItemSeoData($data, $item->id);
        }

        return ['status_code' => 201, 'message' => translate('Added successfully')];
    }

    public function updateForStore(array $data, mixed $store): array
    {
        if (! $store->item_section) {
            return $this->denied();
        }

        $item = $this->findForStore($data['id'], $store->id);

        if (! $item) {
            return $this->missing();
        }

        $choiceOptions = $this->choiceOptions($data);
        $variations = $this->variations($data);

        if (isset($choiceOptions['error']) || isset($variations['error'])) {
            return ['status_code' => 422] + ($choiceOptions['error'] ?? $variations['error']);
        }

        $taxonomyIds = $this->taxonomyIds($data);
        $foodVariations = $this->foodVariations($data);
        $moduleType = $store->module->module_type;
        $taxIds = $this->decodedInput($data['tax_ids'] ?? null);
        $oldVideo = $item->video;
        $oldPrice = $item->price;
        $variationChanged = $this->variationChanged($item, $variations, $foodVariations);

        $item->name = $data['translations'][0]['value'];
        $item->category_id = ($data['sub_category_id'] ?? null) ?: ($data['category_id'] ?? null);
        $item->category_ids = json_encode($this->categoryPayload($data));
        $item->store_category_id = $data['store_category_id'] ?? null ? (int) $data['store_category_id'] : null;
        $item->description = $data['translations'][1]['value'];
        $item->choice_options = json_encode($choiceOptions);
        $item->variations = json_encode($variations);
        $item->food_variations = json_encode($foodVariations);
        $item->price = $data['price'];
        $item->available_time_starts = $data['available_time_starts'] ?? null;
        $item->available_time_ends = $data['available_time_ends'] ?? null;
        $item->discount = $data['discount'] ?? null;
        $item->discount_type = $data['discount_type'] ?? null;
        $item->maximum_cart_quantity = $data['maximum_cart_quantity'] ?? null;
        $item->attributes = $data['attribute_id'] ?? json_encode([]);
        $item->add_ons = json_encode($this->ownAddonIds($data['addon_ids'] ?? null, $store->id));
        $item->stock = $data['current_stock'] ?? 0;
        $item->veg = $data['veg'] ?? 0;
        $item->unit_id = $data['unit'] ?? null;
        $item->organic = $data['organic'] ?? 0;
        $item->is_halal = $data['is_halal'] ?? 0;

        if ($this->requiresApproval('Update_anything_in_product_details')
            || ($this->requiresApproval('Update_product_price') && $oldPrice != $data['price'])
            || ($this->requiresApproval('Update_product_variation') && $variationChanged)) {
            $this->storeTempProduct($item, $data, $taxonomyIds, $moduleType, update: true, taxIds: $taxIds);

            return ['status_code' => 200, 'message' => translate('Your product added for approval')];
        }

        $videoData = $this->persistedVideoData($data, $item->video, $item->video_link);
        $item->image = array_key_exists('image', $data) ? FileStorage::update('product/', $item->image, $data['image']) : $item->image;
        $item->video = $videoData['video'];
        $item->video_link = $videoData['video_link'];
        $item->images = $this->retainedImages($item, $data);

        $this->syncModuleDetails($item, $data, $moduleType, $taxonomyIds);
        $this->replaceTaxables($item, $taxIds);

        $item->save();

        if ($oldVideo && $oldVideo !== $item->video) {
            FileStorage::delete('product/', $oldVideo);
        }

        $item->tags()->sync($taxonomyIds['tags']);
        $item->nutritions()->sync($taxonomyIds['nutritions']);
        $item->allergies()->sync($taxonomyIds['allergies']);
        $item->generic()->sync($taxonomyIds['generics']);

        foreach ($data['translations'] as $translation) {
            $item->translations()->updateOrCreate(
                ['locale' => $translation['locale'], 'key' => $translation['key']],
                ['value' => $translation['value']]
            );
        }

        if ($moduleType === 'ecommerce') {
            $this->syncItemSeoData($data, $item->id);
        }

        return ['status_code' => 200, 'message' => translate('Updated successfully')];
    }

    public function deleteForStore(array $data, mixed $store): array
    {
        if (! $store->item_section) {
            return $this->denied();
        }

        $product = ($data['temp_product'] ?? null)
            ? app(TempProductService::class)->findForStore($data['id'], $store->id)
            : $this->findForStore($data['id'], $store->id);

        if (! $product) {
            return $this->missing();
        }

        if ($product instanceof Item) {
            if ($product->temp_product?->video) {
                FileStorage::delete('product/', $product->temp_product->video);
            }

            $product->temp_product?->translations()?->delete();
            $product->temp_product()?->delete();
            $product->carts()?->delete();
        }

        foreach (['image', 'video'] as $key) {
            if ($product->{$key}) {
                FileStorage::delete('product/', $product->{$key});
            }
        }

        foreach ($product->images ?? [] as $value) {
            FileStorage::delete('product/', is_array($value) ? $value['img'] : $value);
        }

        $product->taxVats()->delete();
        $product->translations()->delete();
        $product->delete();

        return ['status_code' => 200, 'message' => translate('Deleted successfully')];
    }

    public function updateStatusForStore(array $data, mixed $store): array
    {
        if (! $store->item_section && $store->product_uploaad_check === 'commission') {
            return $this->denied();
        }

        if ($store->product_uploaad_check !== null
            && ! in_array($store->product_uploaad_check, ['unlimited', 'commission'], true)
            && $store->product_uploaad_check >= 0
            && (int) $data['status'] === 1) {
            return $this->denied(translate('messages.Your current package does not allow to activate more than allocated items in your package'));
        }

        return $this->applyFlag($data['id'], $store->id, ['status' => $data['status']], translate('messages.Product status updated'));
    }

    public function updateOrganicForStore(array $data, mixed $store): array
    {
        if (! $store->item_section) {
            return $this->denied();
        }

        return $this->applyFlag($data['id'], $store->id, ['organic' => $data['organic'] ?? 0], translate('messages.Product organic status updated'));
    }

    public function updateRecommendedForStore(array $data, mixed $store): array
    {
        if (! $store->item_section) {
            return $this->denied();
        }

        return $this->applyFlag($data['id'], $store->id, ['recommended' => $data['status']], translate('messages.Product recommended status updated'));
    }

    public function updateStockForStore(array $data, mixed $store): array
    {
        $item = $this->findForStore($data['product_id'], $store->id);

        if (! $item) {
            return $this->missing();
        }

        if (count($this->decodedInput($item->variations)) > 0 && ! ($data['type'] ?? null)) {
            return ['status_code' => 422, 'code' => 'type', 'message' => translate('Variation types are required')];
        }

        $variations = [];

        foreach ($this->decodedInput($data['type'] ?? null) as $key => $type) {
            $suffix = $key.'_'.str_replace('.', '_', $type);
            $variations[] = [
                'type' => $type,
                'price' => abs($data['price_'.$suffix] ?? 0),
                'stock' => abs($data['stock_'.$suffix] ?? 0),
            ];
        }

        $item->stock = $data['current_stock'] ?? 0;
        $item->variations = json_encode($variations);
        $item->save();

        return ['status_code' => 200, 'message' => translate('Updated successfully')];
    }

    public function getPendingList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $keywords = explode(' ', (string) ($filters['name'] ?? ''));

        return app(TempProductService::class)->getListForStore($filters, $paginate, $keywords);
    }

    public function findPendingForStore(mixed $tempProductId, mixed $storeId): mixed
    {
        return app(TempProductService::class)->findDetailForStore($tempProductId, $storeId);
    }

    public function getVendorApprovedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $keywords = isset($filters['search']) ? explode(' ', (string) $filters['search']) : [];
        $categoryId = $filters['category_id'] ?? 0;

        $items = Item::withStorage()
            ->with(['tags', 'storeCategory.storage', 'ecommerce_item_details.brand.storage'])
            ->when($categoryId != 0, fn ($query) => $query->whereHas(
                'category',
                fn ($category) => $category->whereId($categoryId)->orWhere('parent_id', $categoryId)
            ))
            ->when($keywords !== [], fn ($query) => $query->where(function ($inner) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $inner->orWhere('name', 'like', "%{$keyword}%");
                }
            }))
            ->type($filters['type'] ?? 'all')
            ->approved()
            ->where('store_id', $filters['store_id'] ?? null)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->loadProductRelations($items->getCollection());

        return $items;
    }

    public function getStoreItems(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return Item::translateOnly(['name'])
            ->where('store_id', $filters['store_id'] ?? null)
            ->where('is_approved', 1)
            ->when(
                ! empty($filters['sub_category']),
                fn ($query) => $query->where('category_id', $filters['category']),
                fn ($query) => $query->whereRaw(
                    "JSON_CONTAINS(category_ids, JSON_OBJECT('id', CAST(? AS CHAR), 'position', ?), '$')",
                    [$filters['category'], 1]
                )
            )
            ->with($this->itemRelationSet())
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getCategoryItems($categoryId, $zoneId, int $limit, int $offset, $type, $userId = null)
    {
        $categorySubCategoryItemDefaultStatus = app(BusinessSettingService::class)->value('category_sub_category_item_default_status') ?? 1;
        $categorySubCategoryItemSortByGeneral = $this->findPrioritySetting(name: 'category_sub_category_item_sort_by_general', type: 'general');
        $categorySubCategoryItemSortByUnavailable = $this->findPrioritySetting(name: 'category_sub_category_item_sort_by_unavailable', type: 'unavailable');
        $categorySubCategoryItemSortByTempClosed = $this->findPrioritySetting(name: 'category_sub_category_item_sort_by_temp_closed', type: 'temp_closed');

        $query = Item::whereHas('module.zones', function ($query) use ($zoneId) {
            $query->whereIn('zones.id', json_decode($zoneId, true));
        })
            ->whereHas('store', function ($query) use ($zoneId) {
                $query->whereIn('zone_id', json_decode($zoneId, true))->whereHas('zone.modules', function ($query) {
                    $query->when(config('module.current_module_data'), function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                });
            })
            ->whereHas('category', function ($q) use ($categoryId) {
                return $q->when(is_numeric($categoryId), function ($qurey) use ($categoryId) {
                    return $qurey->whereId($categoryId)->orWhere('parent_id', $categoryId);
                })
                    ->when(! is_numeric($categoryId), function ($qurey) use ($categoryId) {
                        $qurey->where('slug', $categoryId);
                    });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()->type($type);

        if ($categorySubCategoryItemDefaultStatus == '1') {
            $query = $this->applyItemPersonalization($query, $userId);
            $query = $query->latest();
        } else {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                if ($categorySubCategoryItemSortByUnavailable == 'remove') {
                    $query = $query->where('stock', '>', 0);
                } elseif ($categorySubCategoryItemSortByUnavailable == 'last') {
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if ($categorySubCategoryItemSortByTempClosed == 'remove') {
                $query = $query->having('temp_available', '>', 0);
            } elseif ($categorySubCategoryItemSortByTempClosed == 'last') {
                $query = $query->orderByDesc('temp_available');
            }

            if ($categorySubCategoryItemSortByGeneral == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($categorySubCategoryItemSortByGeneral == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($categorySubCategoryItemSortByGeneral == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($categorySubCategoryItemSortByGeneral == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($categorySubCategoryItemSortByGeneral == 'order_count') {
                $query = $query->orderByDesc('order_count');
            }

        }

        $paginator = $query->paginate($limit ?: config('default_pagination'), ['*'], 'page', max(1, (int) $offset));

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
        ];
    }

    public function getItemsForCategories($categoryIds, $zoneId, int $limit, int $offset, $type, $filter = null, $min = false, $max = false, $ratingCount = null, $brandIds = null, $userId = null)
    {
        $categorySubCategoryItemDefaultStatus = app(BusinessSettingService::class)->value('category_sub_category_item_default_status') ?? 1;
        $categorySubCategoryItemSortByGeneral = $this->findPrioritySetting(name: 'category_sub_category_item_sort_by_general', type: 'general');
        $categorySubCategoryItemSortByUnavailable = $this->findPrioritySetting(name: 'category_sub_category_item_sort_by_unavailable', type: 'unavailable');
        $categorySubCategoryItemSortByTempClosed = $this->findPrioritySetting(name: 'category_sub_category_item_sort_by_temp_closed', type: 'temp_closed');

        $categoryIds = isset($categoryIds) ? (is_array($categoryIds) ? $categoryIds : json_decode($categoryIds)) : [];
        $brandIds = isset($brandIds) ? (is_array($brandIds) ? $brandIds : json_decode($brandIds)) : [];
        $filter = $filter ? (is_array($filter) ? $filter : str_getcsv(trim($filter, '[]'), ',')) : '';

        $query = Item::whereHas('module.zones', function ($query) use ($zoneId) {
            $query->whereIn('zones.id', json_decode($zoneId, true));
        })
            ->whereHas('store', function ($query) use ($zoneId, $filter) {
                return $query->when(config('module.current_module_data'), function ($query) {
                    return $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        return $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zoneId, true))
                    ->when($filter && in_array('free_delivery', $filter), function ($qurey) {
                        return $qurey->where('free_delivery', 1);
                    })
                    ->when($filter && in_array('coupon', $filter), function ($qurey) {
                        return $qurey->has('activeCoupons');
                    });

            })
            ->when(isset($categoryIds) && (count($categoryIds) > 0), function ($query) use ($categoryIds) {
                return $query->whereHas('category', function ($q) use ($categoryIds) {
                    return $q->whereIn('id', $categoryIds)->orWhereIn('parent_id', $categoryIds);
                });
            })
            ->when(isset($brandIds) && (count($brandIds) > 0), function ($query) use ($brandIds) {
                return $query->whereHas('ecommerce_item_details', function ($q) use ($brandIds) {
                    return $q->whereHas('brand', function ($q) use ($brandIds) {
                        return $q->whereIn('id', $brandIds);
                    });
                });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()->type($type);

        $query = $query->when($ratingCount, function ($query) use ($ratingCount) {
            return $query->where('avg_rating', '>=', $ratingCount);
        });
        $query = $query->when($min && $max, function ($query) use ($min, $max) {
            return $query->whereBetween('price', [$min, $max]);
        });
        $query = $query->when($filter && in_array('top_rated', $filter), function ($qurey) {
            return $qurey->withCount('reviews')->orderBy('reviews_count', 'desc');
        });
        $query = $query->when($filter && in_array('popular', $filter), function ($qurey) {
            return $qurey->popular();
        });
        $query = $query->when($filter && in_array('high', $filter), function ($qurey) {
            return $qurey->orderByDesc('price');
        });
        $query = $query->when($filter && in_array('low', $filter), function ($qurey) {
            return $qurey->orderBy('price', 'asc');
        });
        $query = $query->when($filter && in_array('discounted', $filter), function ($qurey) {
            return $qurey->Discounted()->orderBy('discount', 'desc');
        });
        $query = $query->when($filter && in_array('available_now', $filter), function ($query) {
            $query->where(function ($q) {
                $currentTime = now()->format('H:i:s');

                $q->whereRaw('(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)', [$currentTime])
                    ->orWhereRaw('(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))', [$currentTime, $currentTime]);
            });
        });

        if ($categorySubCategoryItemDefaultStatus == '1') {
            $query = $this->applyItemPersonalization($query, $userId, $filter);
            $query = $query->latest();
        } else {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                if ($categorySubCategoryItemSortByUnavailable == 'remove') {
                    $query = $query->where('stock', '>', 0);
                } elseif ($categorySubCategoryItemSortByUnavailable == 'last') {
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if ($categorySubCategoryItemSortByTempClosed == 'remove') {
                $query = $query->having('temp_available', '>', 0);
            } elseif ($categorySubCategoryItemSortByTempClosed == 'last') {
                $query = $query->orderByDesc('temp_available');
            }

            if ($categorySubCategoryItemSortByGeneral == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($categorySubCategoryItemSortByGeneral == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($categorySubCategoryItemSortByGeneral == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($categorySubCategoryItemSortByGeneral == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($categorySubCategoryItemSortByGeneral == 'order_count') {
                $query = $query->orderByDesc('order_count');
            }

        }

        $paginator = $query->paginate($limit ?: config('default_pagination'), ['*'], 'page', max(1, (int) $offset));
        $itemCategories = collect($paginator->items())->pluck('category_id')->unique()->toArray();

        $categories = app(CategoryService::class)->getTreeForItemCategories($itemCategories);

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories' => $categories,
        ];
    }

    public function getAllCategoryItems($id, $zoneId)
    {
        $cateIds = [];
        array_push($cateIds, (int) $id);
        foreach (app(CategoryService::class)->getChildIds($id) as $ch1) {
            array_push($cateIds, $ch1['id']);
            foreach (app(CategoryService::class)->getChildIds($ch1['id']) as $ch2) {
                array_push($cateIds, $ch2['id']);
            }
        }

        return Item::whereIn('category_id', $cateIds)
            ->whereHas('module.zones', function ($query) use ($zoneId) {
                $query->whereIn('zones.id', json_decode($zoneId, true));
            })
            ->whereHas('store', function ($query) use ($zoneId) {
                $query->whereIn('zone_id', json_decode($zoneId, true))->whereHas('zone.modules', function ($query) {
                    $query->when(config('module.current_module_data'), function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                });
            })
            ->get();
    }

    /**
     * The subset of the requested zones the given module is served in. Mirrors the
     * whereHas('zone.modules') check this replaced: the link lives in module_zone.
     */
    private function zonesServingModule(array $zoneIds, mixed $moduleId): array
    {
        if ($zoneIds === [] || ! $moduleId) {
            return $zoneIds;
        }

        $served = DB::table('module_zone')
            ->where('module_id', $moduleId)
            ->whereIn('zone_id', $zoneIds)
            ->pluck('zone_id')
            ->map('intval')
            ->all();

        return array_values(array_intersect($zoneIds, $served));
    }

    public function getFeaturedItems($zoneId, int $limit, int $offset, $type, $userId = null)
    {
        $featuredModuleId = config('module.current_module_data')['id'] ?? null;

        // Zones resolved to an id list up front, then matched against items.zone_id. The
        // previous whereHas('store', ...) with a nested whereHas('zone.modules') made MySQL
        // drive from the store table: 33.5M rows examined to return ten items.
        $requestedZones = array_map('intval', json_decode($zoneId, true) ?: []);
        $allowedZones = $this->zonesServingModule($requestedZones, $featuredModuleId);

        $paginator = Item::active()->type($type)
            ->whereIn('items.zone_id', $allowedZones)
            // One subquery against the small categories table instead of two OR'd EXISTS
            // from items, which forced a correlated existence check per item and a temp-table
            // sort. Condition unchanged: the item's category is featured, or its parent is.
            // top_category_id cannot express it -- a child may be featured when its parent is not.
            ->whereIn('items.category_id', Category::select('categories.id')
                ->where(function ($category) use ($featuredModuleId) {
                    $category->where(['featured' => 1, 'status' => 1, 'module_id' => $featuredModuleId])
                        ->orWhereExists(function ($parent) use ($featuredModuleId) {
                            $parent->selectRaw('1')->from('categories as parent_category')
                                ->whereColumn('parent_category.id', 'categories.parent_id')
                                ->where([
                                    'parent_category.featured' => 1,
                                    'parent_category.status' => 1,
                                    'parent_category.module_id' => $featuredModuleId,
                                ]);
                        });
                }));

        $paginator = $this->applyItemPersonalization($paginator, $userId);
        $paginator = $paginator->latest()->paginate($limit, ['*'], 'page', $offset);

        $itemCategories = collect($paginator->items())->pluck('category_id')->unique()->toArray();

        $categories = app(CategoryService::class)->getBasicActiveByIds($itemCategories);

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'categories' => $categories,
            'products' => $paginator->items(),
        ];
    }

    public function findActiveWithModule(mixed $id, array $relations = ['module']): ?Item
    {
        return $this->find($id, $relations, true);
    }

    public function getByIdsWithModule(array $ids, bool $activeOnly = false): array
    {
        return Item::with(['module', 'pharmacy_item_details'])
            ->when($activeOnly, fn ($query) => $query->active())
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    public function find(mixed $id, array $relations = [], bool $active = false): ?Item
    {
        return Item::with($relations)
            ->when($active, fn ($query) => $query->active())
            ->find($id);
    }

    public function findUnscopedWithModule(mixed $id): ?Item
    {
        return $this->unscopedWithModuleQuery()->find($id);
    }

    public function getTopByStores(array $storeIds, int $limit): mixed
    {
        return Item::active()
            ->withStorage()
            ->whereIn('store_id', $storeIds)
            ->orderBy('store_id')
            ->orderByDesc('order_count')
            ->get(['id', 'name', 'image', 'store_id', 'price', 'discount', 'discount_type', 'order_count', 'avg_rating'])
            ->groupBy('store_id')
            ->map(fn ($group) => $group->take(max(1, $limit))->values());
    }

    public function getCountsByStores(array $storeIds): mixed
    {
        return Item::active()
            ->whereIn('store_id', $storeIds)
            ->selectRaw('store_id, COUNT(*) as items_count')
            ->groupBy('store_id')
            ->pluck('items_count', 'store_id');
    }

    /*
     * Catalogue depth per module for the admin module list. One grouped row per
     * module rather than a count per row, in the same shape as
     * ZoneService::getCountsByModule() and StoreService::getMetricsByModule().
     * The ZoneScope on Item stays on purpose: a zone-scoped admin then sees the
     * same slice here as in the Stores and Vendors columns beside it.
     */
    public function getCountsByModule(array $moduleIds): array
    {
        return Item::whereIn('module_id', $moduleIds)
            ->selectRaw('module_id, COUNT(*) AS item_count, '
                .'SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_item_count')
            ->groupBy('module_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->module_id => [
                'items' => (int) $row->item_count,
                'active_items' => (int) $row->active_item_count,
            ]])->all();
    }

    public function adminListFilters(array $input): array
    {
        return [
            'module_id' => $input['module_id'] ?? null,
            'search' => trim((string) ($input['search'] ?? '')),
            'store_id' => $this->adminListId($input['store_id'] ?? null),
            'zone_id' => $this->adminListId($input['zone_id'] ?? null),
            'category_id' => $this->adminListId($input['category_id'] ?? null),
            'sub_category_id' => $this->adminListId($input['sub_category_id'] ?? null),
            'store_category_id' => $this->adminListId($input['store_category_id'] ?? null),
            'condition_id' => $this->adminListId($input['condition_id'] ?? null),
            'brand_id' => $this->adminListId($input['brand_id'] ?? null),
            'type' => array_values(array_intersect(['veg', 'non_veg'], (array) ($input['type'] ?? []))),
            'status' => array_values(array_intersect(['active', 'inactive'], (array) ($input['status'] ?? []))),
            'stock' => array_values(array_intersect(['in', 'out'], (array) ($input['stock'] ?? []))),
            'flag' => array_values(array_intersect(['discounted', 'never_ordered'], (array) ($input['flag'] ?? []))),
        ];
    }

    public function adminListFilterCount(array $filters): int
    {
        return count(array_filter([
            $filters['store_id'],
            $filters['zone_id'],
            $filters['category_id'],
            $filters['sub_category_id'],
            $filters['store_category_id'],
            $filters['condition_id'],
            $filters['brand_id'],
            $filters['type'],
            $filters['status'],
            $filters['stock'],
            $filters['flag'],
        ]));
    }

    public function adminList(array $filters, array $paginate = [], bool $withTax = false): LengthAwarePaginator
    {
        return $this->adminListQuery($filters)
            ->withStorage()
            ->with(['store' => fn ($query) => $query->select(['id', 'name'])])
            ->when($withTax, fn ($query) => $query->with('taxVats.tax'))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate))
            ->withQueryString();
    }

    public function adminListExportQuery(array $filters): mixed
    {
        return $this->adminListQuery($filters)
            ->with(['unit', 'store', 'tags', 'taxVats.tax', 'category'])
            ->latest()
            ->orderBy('id', 'asc');
    }

    public function adminListSummary(array $filters): array
    {
        $row = $this->adminListQuery(array_merge($filters, ['status' => [], 'stock' => [], 'flag' => []]))
            ->without('storeCategory')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_items')
            ->selectRaw('SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_items')
            ->selectRaw('SUM(CASE WHEN discount > 0 THEN 1 ELSE 0 END) as discounted_items')
            ->selectRaw('SUM(CASE WHEN COALESCE(order_count, 0) = 0 THEN 1 ELSE 0 END) as never_ordered_items')
            ->selectRaw('COALESCE(AVG(CASE WHEN rating_count > 0 THEN avg_rating END), 0) as average_rating')
            ->first();

        $total = (int) ($row->total_items ?? 0);
        $active = (int) ($row->active_items ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'out_of_stock' => (int) ($row->out_of_stock_items ?? 0),
            'discounted' => (int) ($row->discounted_items ?? 0),
            'never_ordered' => (int) ($row->never_ordered_items ?? 0),
            'average_rating' => round((float) ($row->average_rating ?? 0), 1),
        ];
    }

    public function vendorListFilters(array $input): array
    {
        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'category_id' => $this->adminListId($input['category_id'] ?? null),
            'sub_category_id' => $this->adminListId($input['sub_category_id'] ?? null),
            'store_category_id' => $this->adminListId($input['store_category_id'] ?? null),
            'type' => array_values(array_intersect(['veg', 'non_veg'], (array) ($input['type'] ?? []))),
            'status' => array_values(array_intersect(['active', 'inactive'], (array) ($input['status'] ?? []))),
            'stock' => array_values(array_intersect(['in', 'out'], (array) ($input['stock'] ?? []))),
            'flag' => array_values(array_intersect(['discounted', 'never_ordered', 'recommended'], (array) ($input['flag'] ?? []))),
        ];
    }

    public function vendorListFilterCount(array $filters): int
    {
        return count(array_filter([
            $filters['category_id'],
            $filters['sub_category_id'],
            $filters['store_category_id'],
            $filters['type'],
            $filters['status'],
            $filters['stock'],
            $filters['flag'],
        ]));
    }

    public function vendorList(array $filters, array $paginate = [], bool $withTax = false): LengthAwarePaginator
    {
        return $this->vendorListQuery($filters)
            ->withStorage()
            ->with('category')
            ->when($withTax, fn ($query) => $query->with('taxVats.tax'))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate))
            ->withQueryString();
    }

    public function vendorListSummary(array $filters): array
    {
        $row = $this->vendorListQuery(array_merge($filters, ['status' => [], 'stock' => [], 'flag' => []]))
            ->without('storeCategory')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_items')
            ->selectRaw('SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_items')
            ->selectRaw('SUM(CASE WHEN discount > 0 THEN 1 ELSE 0 END) as discounted_items')
            ->selectRaw('SUM(CASE WHEN COALESCE(order_count, 0) = 0 THEN 1 ELSE 0 END) as never_ordered_items')
            ->selectRaw('COALESCE(AVG(CASE WHEN rating_count > 0 THEN avg_rating END), 0) as average_rating')
            ->first();

        $total = (int) ($row->total_items ?? 0);
        $active = (int) ($row->active_items ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'out_of_stock' => (int) ($row->out_of_stock_items ?? 0),
            'discounted' => (int) ($row->discounted_items ?? 0),
            'never_ordered' => (int) ($row->never_ordered_items ?? 0),
            'average_rating' => round((float) ($row->average_rating ?? 0), 1),
        ];
    }

    private function vendorListQuery(array $filters): mixed
    {
        $keywords = array_values(array_filter(explode(' ', $filters['search'] ?? ''), fn ($word) => $word !== ''));
        $type = $filters['type'] ?? [];
        $status = $filters['status'] ?? [];
        $stock = $filters['stock'] ?? [];

        return Item::where('store_id', Helpers::get_store_id())
            ->when($filters['sub_category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->whereHas('category',
                fn ($category) => $category->whereId($id)->orWhere('parent_id', $id)))
            ->when($filters['store_category_id'] ?? null, fn ($query, $id) => $query->where('store_category_id', $id))
            ->when($keywords, fn ($query) => $query->where(function ($outer) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $outer->where(fn ($group) => $group->where('name', 'like', "%{$keyword}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$keyword}%")));
                }
            }))
            ->when(count($type) === 1, fn ($query) => $query->type($type[0]))
            ->when(count($status) === 1, fn ($query) => $query->where('status', $status[0] === 'active' ? 1 : 0))
            ->when(count($stock) === 1, fn ($query) => $stock[0] === 'out'
                ? $query->where('stock', '<=', 0)
                : $query->where('stock', '>', 0))
            ->when(in_array('discounted', $filters['flag'] ?? [], true), fn ($query) => $query->where('discount', '>', 0))
            ->when(in_array('never_ordered', $filters['flag'] ?? [], true), fn ($query) => $query->where(fn ($unsold) => $unsold->whereNull('order_count')->orWhere('order_count', 0)))
            ->when(in_array('recommended', $filters['flag'] ?? [], true), fn ($query) => $query->recommended())
            ->approved();
    }

    private function adminListQuery(array $filters): mixed
    {
        $keywords = array_values(array_filter(explode(' ', $filters['search'] ?? ''), fn ($word) => $word !== ''));
        $type = $filters['type'] ?? [];
        $status = $filters['status'] ?? [];
        $stock = $filters['stock'] ?? [];

        return Item::withoutGlobalScope(StoreScope::class)
            ->when($filters['module_id'] ?? null, fn ($query, $moduleId) => $query->module($moduleId))
            ->when($filters['store_id'] ?? null, fn ($query, $id) => $query->where('store_id', $id))
            ->when($filters['sub_category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->whereHas('category',
                fn ($category) => $category->whereId($id)->orWhere('parent_id', $id)))
            ->when($filters['store_category_id'] ?? null, fn ($query, $id) => $query->where('store_category_id', $id))
            ->when($filters['zone_id'] ?? null, fn ($query, $id) => $query->whereHas('store',
                fn ($store) => $store->where('zone_id', $id)))
            ->when($filters['condition_id'] ?? null, fn ($query, $id) => $query->whereHas('pharmacy_item_details',
                fn ($details) => $details->where('common_condition_id', $id)))
            ->when($filters['brand_id'] ?? null, fn ($query, $id) => $query->whereHas('ecommerce_item_details',
                fn ($details) => $details->where('brand_id', $id)))
            ->when($keywords, fn ($query) => $query->where(function ($outer) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $outer->where('name', 'like', "%{$keyword}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$keyword}%"));
                }
            }))
            ->when(count($type) === 1, fn ($query) => $query->type($type[0]))
            ->when(count($status) === 1, fn ($query) => $query->where('status', $status[0] === 'active' ? 1 : 0))
            ->when(count($stock) === 1, fn ($query) => $stock[0] === 'out'
                ? $query->where('stock', '<=', 0)
                : $query->where('stock', '>', 0))
            ->when(in_array('discounted', $filters['flag'] ?? [], true), fn ($query) => $query->where('discount', '>', 0))
            ->when(in_array('never_ordered', $filters['flag'] ?? [], true), fn ($query) => $query->where(fn ($unsold) => $unsold->whereNull('order_count')->orWhere('order_count', 0)))
            ->approved();
    }

    private function adminListId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function decodedInput(mixed $value): array
    {
        return is_array($value) ? $value : Helpers::decodeJsonToArray($value);
    }

    protected function taxonomyIds(array $data): array
    {
        return [
            'tags' => app(TagService::class)->splitToIds($data['tags'] ?? null),
            'nutritions' => app(NutritionService::class)->splitToIds($data['nutritions'] ?? null),
            'allergies' => app(AllergyService::class)->splitToIds($data['allergies'] ?? null),
            'generics' => ($data['generic_name'] ?? null) != null
                ? [app(GenericNameService::class)->findOrCreateId($data['generic_name'])]
                : [],
        ];
    }

    protected function categoryPayload(array $data): array
    {
        $positions = ['category_id' => 1, 'sub_category_id' => 2, 'sub_sub_category_id' => 3];
        $categories = [];

        foreach ($positions as $key => $position) {
            if (($data[$key] ?? null) != null) {
                $categories[] = ['id' => $data[$key], 'position' => $position];
            }
        }

        return $categories;
    }

    protected function choiceOptions(array $data): array
    {
        if (! array_key_exists('choice', $data)) {
            return [];
        }

        $choiceOptions = [];

        foreach ($this->decodedInput($data['choice_no'] ?? null) as $key => $no) {
            $values = $this->decodedInput($data['choice_options_'.$no] ?? null);

            if (($values[0] ?? null) === null) {
                return ['error' => ['code' => 'name', 'message' => translate('messages.Attribute choice option value can not be null')]];
            }

            $choiceOptions[] = [
                'name' => 'choice_'.$no,
                'title' => $this->decodedInput($data['choice'])[$key] ?? null,
                'options' => explode(',', implode('|', preg_replace('/\s+/', ' ', $values))),
            ];
        }

        return $choiceOptions;
    }

    protected function variations(array $data): array
    {
        $options = [];

        if (array_key_exists('choice_no', $data)) {
            foreach ($this->decodedInput($data['choice_no']) as $no) {
                $options[] = explode(',', implode('|', $this->decodedInput($data['choice_options_'.$no] ?? null)));
            }
        }

        $combinations = Helpers::combinations($options);

        if (count($combinations[0]) === 0) {
            return [];
        }

        $variations = [];

        foreach ($combinations as $combination) {
            $type = implode('-', array_map(fn ($value) => str_replace(' ', '', $value), $combination));
            $key = str_replace('.', '_', $type);
            $price = abs($data['price_'.$key] ?? 0);

            if (($data['discount_type'] ?? null) === 'amount' && $price < (float) ($data['discount'] ?? 0)) {
                return ['error' => ['code' => 'unit_price', 'message' => translate('Variation price must be greater than discount amount')]];
            }

            $variations[] = ['type' => $type, 'price' => $price, 'stock' => abs($data['stock_'.$key] ?? 0)];
        }

        return $variations;
    }

    protected function foodVariations(array $data): array
    {
        $foodVariations = [];

        foreach ($this->decodedInput($data['options'] ?? null) as $option) {
            $values = [];

            foreach ($option['values'] as $value) {
                if (isset($value['label'])) {
                    $option_label = $value['label'];
                }

                $row = isset($option_label) ? ['label' => $option_label] : [];
                $row['optionPrice'] = $value['optionPrice'];
                $values[] = $row;
            }

            $foodVariations[] = [
                'name' => $option['name'],
                'type' => $option['type'],
                'min' => $option['min'] ?? 0,
                'max' => $option['max'] ?? 0,
                'required' => $option['required'] ?? 'off',
                'values' => $values,
            ];
        }

        return $foodVariations;
    }

    private function storeScopeUnscopedQuery(): mixed
    {
        return Item::withoutGlobalScope(StoreScope::class);
    }

    private function translationsUnscopedQuery(): mixed
    {
        return Item::withoutGlobalScope('translate');
    }

    private function relatedListPayload(array $filters, array $paginate, callable $relatedBy): LengthAwarePaginator
    {
        $zone_id = $filters['zone_id'] ?? null;
        $product_id = $filters['product_id'] ?? null;
        $product = Item::find($product_id);
        $query = Item::active()
            ->whereHas('module.zones', function ($query) use ($zone_id) {
                $query->whereIn('zones.id', json_decode($zone_id, true));
            })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zone_id, true));
            });

        $query = $relatedBy($query, $product)->where('id', '!=', $product->id);

        $query = $this->personalise($query, $filters, withFilter: false);

        return $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    private function unscopedWithModuleQuery(): mixed
    {
        return $this->storeScopeUnscopedQuery()->with('module');
    }

    private function flagWishlisted(mixed $items, mixed $userId): void
    {
        $rows = collect($items);
        $wishlisted = $this->wishlistedItemIds($rows->pluck('id')->all(), $userId);

        foreach ($rows as $item) {
            $item->is_wishlisted = isset($wishlisted[$item->id]) ? 1 : 0;
        }
    }

    private function wishlistedItemIds(array $itemIds, mixed $userId): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));

        if (! $userId || ! $itemIds) {
            return [];
        }

        return array_flip(app(WishlistService::class)->getItemIds($userId, $itemIds));
    }

    private static function decodeValidZoneIds($zone_id)
    {
        $zones = is_array($zone_id) ? $zone_id : json_decode((string) $zone_id, true);
        if (! is_array($zones) && is_numeric($zones)) {
            $zones = [(int) $zones];
        }

        return is_array($zones) && ! empty($zones) ? $zones : null;
    }

    private static function getCategoryData($products)
    {
        $productCollection = collect(is_array($products) ? $products : [$products]);
        $item_categories = $productCollection->pluck('category_ids')->filter()->toArray();
        $item_categories = array_reduce($item_categories, function ($carry, $jsonString) {
            $decoded = is_string($jsonString) ? json_decode($jsonString, true) : $jsonString;
            if (! is_array($decoded)) {
                return $carry;
            }
            $filtered = array_filter($decoded, fn ($item) => isset($item['position']) && $item['position'] == 1);
            $carry = array_merge($carry, array_column($filtered, 'id'));

            return $carry;
        }, []);

        $item_categories = array_unique($item_categories);

        return app(CategoryService::class)->getBasicByIds($item_categories, self::MAX_SIDECAR_CATEGORIES);
    }

    /**
     * Distinct category ids across the leading slice of a search result, for the category
     * facet. Bounded on purpose: scanning the full match set is what made items/search fail
     * outright on broad keywords.
     */
    /**
     * The category facet, plus the exact match total when it is known.
     *
     * One row is read per match up to the bound, so reading one extra row tells us whether the
     * match set fitted: if it did, its size is the exact total and the caller can skip the
     * paginator's separate count pass over the same expensive predicate. If it overflowed the
     * bound the total is unknown and null is returned, and the caller counts as before.
     *
     * @return array{0: array<int, mixed>, 1: int|null}
     */
    private function searchCategoryFacet($query): array
    {
        $ids = $query->reorder()
            ->limit(self::SEARCH_FACET_SCAN_LIMIT + 1)
            ->pluck('category_id');

        $overflowed = $ids->count() > self::SEARCH_FACET_SCAN_LIMIT;

        return [
            array_values(array_filter(array_unique($ids->take(self::SEARCH_FACET_SCAN_LIMIT)->all()))),
            $overflowed ? null : $ids->count(),
        ];
    }

    private function searchCategories(array $categoryIds): array
    {
        return app(CategoryService::class)->getSearchTree($categoryIds, self::MAX_SIDECAR_CATEGORIES);
    }

    private function moduleSummary(mixed $module): ?array
    {
        return $module ? [
            'id' => $module->id,
            'name' => $module->module_name,
            'image' => $module->icon_full_url,
            'type' => $module->module_type,
        ] : null;
    }

    private function listPayload(array $filters, array $paginate, array $spec): array
    {
        $filter = $filters['filter'] ?? null;
        $prefix = $spec['setting'];

        $categoryIds = $filters['category_ids'] ?? null;
        $categoryIds = isset($categoryIds) ? (is_array($categoryIds) ? $categoryIds : json_decode($categoryIds)) : [];

        $settings = $this->sortSettings($prefix);
        $defaultStatus = ($spec['always_personalised'] ?? false) ? 1 : $settings['status'];
        $sortGeneral = $settings['general'];
        $sortUnavailable = $settings['unavailable'];
        $sortTempClosed = $settings['temp_closed'];

        $withCount = $spec['with_count'] ?? [];
        if ($filter && in_array('top_rated', $filter)) {
            $withCount[] = 'reviews';
        }
        if ($filter && in_array('most_loved', $filter)) {
            $withCount[] = 'whislists';
        }
        if ($sortGeneral === 'review_count') {
            $withCount[] = 'reviews';
        }

        $zones = self::decodeValidZoneIds($filters['zone_id'] ?? null);

        $query = Item::with('store')
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(! $zones, fn ($q) => $q->whereRaw('0 = 1'))
            ->type($filters['type'] ?? 'all');

        $query = ($spec['scope'])($query);

        if ($spec['free_delivery_filter'] ?? false) {
            $query = $query->when($filter && in_array('free_delivery', $filter), fn ($q) => $q->where('free_delivery', 1));
        }

        if ($spec['brand_filter'] ?? false) {
            $brandIds = $filters['brand_ids'] ?? null;
            $brandIds = isset($brandIds) ? (is_array($brandIds) ? $brandIds : json_decode($brandIds)) : [];
            $query = $query->when($brandIds && count($brandIds) > 0, function ($query) use ($brandIds) {
                $query->whereHas('ecommerce_item_details', function ($q) use ($brandIds) {
                    $q->whereHas('brand', fn ($q) => $q->whereIn('id', $brandIds));
                });
            });
        }

        $query = $query->filterList(
            $filter, $filters['min'] ?? 0, $filters['max'] ?? false, $categoryIds,
            $filters['rating_count'] ?? null, $withCount, $filters['search'] ?? null,
            ($spec['store_category_filter'] ?? true) ? ($filters['store_category_id'] ?? null) : null
        );

        if ($defaultStatus == '1') {
            if ($spec['personalise'] ?? true) {
                $query = $this->personalise($query, $filters);
            }
            $query = ($spec['order'])($query);
        } else {
            if (config('module.current_module_data')['module_type'] !== 'food') {
                $query = match ($sortUnavailable) {
                    'remove' => $query->where('stock', '>', 0),
                    'last' => $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END'),
                    default => $query,
                };
            }

            $query = match ($sortTempClosed) {
                'remove' => $query->having('temp_available', '>', 0),
                'last' => $query->orderByDesc('temp_available'),
                default => $query,
            };

            $query = match ($sortGeneral) {
                'review_count' => $query->orderByDesc('reviews_count'),
                'order_count' => $query->orderByDesc('order_count'),
                'a_to_z' => $query->orderBy('name'),
                'z_to_a' => $query->orderByDesc('name'),
                'latest_created' => $query->latest(),
                'first_created' => $query->oldest(),
                'rating' => $query->orderByDesc('avg_rating')->orderByDesc('rating_count'),
                default => ($spec['general_default'] ?? $spec['order'])($query),
            };
        }

        $paginator = $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return [
            'paginator' => $paginator,
            'total_size' => $paginator->total(),
            'limit' => $paginator->perPage(),
            'offset' => $paginator->currentPage(),
            'products' => $paginator->items(),
            'categories' => self::getCategoryData($paginator->items()),
        ];
    }

    private function sortSettings(string $prefix): array
    {
        return [
            'status' => app(BusinessSettingService::class)->value($prefix.'_default_status') ?? 1,
            'general' => $this->findPrioritySetting(name: $prefix.'_sort_by_general', type: 'general'),
            'unavailable' => $this->findPrioritySetting(name: $prefix.'_sort_by_unavailable', type: 'unavailable'),
            'temp_closed' => $this->findPrioritySetting(name: $prefix.'_sort_by_temp_closed', type: 'temp_closed'),
        ];
    }

    private function personalise(mixed $query, array $filters, bool $withFilter = true): mixed
    {
        return $this->applyItemPersonalization(
            $query,
            $filters['user_id'] ?? null,
            $withFilter ? ($filters['filter'] ?? null) : null
        );
    }

    private function constrainCustomerOrders(mixed $query, mixed $userId, mixed $moduleId, string $prefix = ''): void
    {
        $query->where($prefix.'user_id', $userId)
            ->where($prefix.'is_guest', 0)
            ->where($prefix.'order_type', '<>', 'pos')
            ->whereNotIn($prefix.'order_status', ['failed', 'canceled'])
            ->when($moduleId, fn ($scoped) => $scoped->where($prefix.'module_id', $moduleId));
    }

    private function configModuleId(): mixed
    {
        return config('module.current_module_data')['id'] ?? null;
    }

    private function applyFlag(mixed $itemId, mixed $storeId, array $attributes, string $message): array
    {
        $item = $this->findForStore($itemId, $storeId);

        if (! $item) {
            return $this->missing();
        }

        $item->forceFill($attributes)->save();

        return ['status_code' => 200, 'message' => $message];
    }

    private function denied(?string $message = null): array
    {
        return ['status_code' => 403, 'code' => 'unauthorized', 'message' => $message ?? translate('messages.Permission denied')];
    }

    private function missing(): array
    {
        return ['status_code' => 404, 'code' => 'id', 'message' => translate('No data found')];
    }

    private function guardSubscription(mixed $store): ?array
    {
        if ($store->store_business_model === 'unsubscribed') {
            return ['status_code' => 403, 'code' => 'unsubscribed', 'message' => translate('messages.You are not subscribed to any package')];
        }

        if ($store->store_business_model !== 'subscription') {
            return null;
        }

        if (! isset($store->store_sub)) {
            return ['status_code' => 403, 'code' => 'unsubscribed', 'message' => translate('messages.You are not subscribed to any package')];
        }

        $maxProduct = $store->store_sub->max_product;

        if ($maxProduct !== 'unlimited' && $maxProduct > 0 && Item::where('store_id', $store->id)->count() + 1 >= $maxProduct) {
            $store->update(['item_section' => 0]);
        }

        return null;
    }

    private function requiresApproval(string $key): bool
    {
        if (! app(BusinessSettingService::class)->value('product_approval')) {
            return false;
        }

        $settings = $this->decodedInput(app(BusinessSettingService::class)->value('product_approval_datas', false) ?? '');

        return data_get($settings, $key) == 1;
    }

    private function gallerySource(array $data): ?Item
    {
        if (! ($data['item_id'] ?? null) || (int) ($data['product_gellary'] ?? 0) !== 1) {
            return null;
        }

        return $this->storeScopeUnscopedQuery()->findOrFail($data['item_id']);
    }

    private function galleryImages(array $data, ?Item $gallerySource): array
    {
        $images = [];
        $removed = explode(',', (string) ($data['removedImageKeys'] ?? ''));

        foreach ($gallerySource?->images ?? [] as $value) {
            if (in_array(is_array($value) ? $value['img'] : $value, $removed)) {
                continue;
            }

            $value = is_array($value) ? $value : ['img' => $value, 'storage' => 'public'];
            $copied = FileStorage::copyStorageFile('product/', $value['img'], $value['storage']);
            $images[] = ['img' => $copied ?? $value['img'], 'storage' => FileStorage::getDisk()];
        }

        foreach ($this->uploadedImages($data) as $image) {
            $images[] = ['img' => FileStorage::upload('product/', $image), 'storage' => FileStorage::getDisk()];
        }

        return $images;
    }

    private function retainedImages(Item $item, array $data): array
    {
        $kept = $this->decodedInput($data['images'] ?? null);
        $images = [];

        foreach ($item->images ?? [] as $image) {
            if (! in_array($image, $kept)) {
                FileStorage::delete('product/', $image);

                continue;
            }

            $images[] = $image;
        }

        foreach ($this->uploadedImages($data) as $image) {
            $images[] = ['img' => FileStorage::upload('product/', $image), 'storage' => FileStorage::getDisk()];
        }

        return $images;
    }

    private function variationChanged(Item $item, array $variations, array $foodVariations): bool
    {
        return (($item->food_variations !== null && $foodVariations !== []) && strcmp($item->food_variations, json_encode($foodVariations)) !== 0)
            || (($item->variations !== null && $variations !== []) && strcmp($item->variations, json_encode($variations)) !== 0);
    }

    private function syncModuleDetails(Item $item, array $data, string $moduleType, array $taxonomyIds): void
    {
        if ($moduleType === 'pharmacy') {
            $item->generic()->sync($taxonomyIds['generics']);

            $details = [
                'common_condition_id' => $data['condition_id'] ?? null,
                'is_basic' => $data['basic'] ?? 0,
                'is_prescription_required' => $data['is_prescription_required'] ?? 0,
                'unit_value' => $data['unit_value'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
            ];

            $item->pharmacy_item_details()->updateOrCreate([], $details);
        }

        if (in_array($moduleType, ['ecommerce', 'grocery'], true)) {
            $item->ecommerce_item_details()->updateOrCreate([], ['brand_id' => $data['brand_id'] ?? null]);
        }
    }

    private function replaceTaxables(Item $item, array $taxIds): void
    {
        if (! addon_published_status('TaxModule') || ! $taxIds) {
            return;
        }

        $current = $item->taxVats()->pluck('tax_id')->toArray();
        $wanted = array_map('intval', $taxIds);
        sort($current);
        sort($wanted);

        if ($current === $wanted) {
            return;
        }

        $item->taxVats()->delete();
        $this->createTaxables($item, $taxIds);
    }

    private function matchBrand($query, mixed $brand): void
    {
        is_numeric($brand) ? $query->whereId($brand) : $query->where('slug', $brand);
    }

    private function applyBrandItemSort(mixed $query, array $spec): mixed
    {
        if ($spec['is_default']) {
            return $query->latest();
        }

        if (data_get(config('module.current_module_data'), 'module_type') !== 'food') {
            if ($spec['unavailable'] === 'remove') {
                $query->where('stock', '>', 0);
            } elseif ($spec['unavailable'] === 'last') {
                $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
            }
        }

        if ($spec['temp_closed'] === 'remove') {
            $query->having('temp_available', '>', 0);
        } elseif ($spec['temp_closed'] === 'last') {
            $query->orderByDesc('temp_available');
        }

        return match ($spec['sort_by']) {
            'rating' => $query->orderByDesc('avg_rating'),
            'review_count' => $query->withCount('reviews')->orderByDesc('reviews_count'),
            'a_to_z' => $query->orderBy('name'),
            'z_to_a' => $query->orderByDesc('name'),
            'order_count' => $query->orderByDesc('order_count'),
            default => $query,
        };
    }

    /**
     * The add-on ids an item may carry: this store's own. They arrive as a comma list and were
     * stored as sent, so another store's add-ons -- names and prices -- could be attached to an
     * item and priced into its orders. Kept as the strings explode() produced, the stored shape.
     */
    private function ownAddonIds(mixed $raw, mixed $storeId): array
    {
        $ids = array_values(array_filter(explode(',', (string) $raw), fn ($id) => $id !== ''));
        if ($ids === []) {
            return [];
        }

        $owned = \App\Models\AddOn::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $storeId)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (string) $id)->all();

        return array_values(array_filter($ids, fn ($id) => in_array(trim($id), $owned, true)));
    }
}
