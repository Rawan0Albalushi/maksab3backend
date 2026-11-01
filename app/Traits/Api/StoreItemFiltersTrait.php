<?php

namespace App\Traits\Api;

use App\Traits\Item\ItemFilterTrait;
use Illuminate\Http\Request;

/**
 * The query parameters a store-scoped item listing accepts.
 *
 * Read from one place so the endpoints that list a single store's items stay on the
 * same surface. They had drifted: /store-categories/items grew type, sorting, search,
 * rating and price filters while /stores/popular-items still honoured only
 * limit/offset, so the same query string answered differently depending on which one
 * the app called.
 */
trait StoreItemFiltersTrait
{
    use ApiRequestContextTrait;
    use ItemFilterTrait;

    /** Handed to the Item scopes as-is; they each pick the keys they understand. */
    private const STORE_ITEM_FILTER_INPUTS = [
        'rating', 'rating_plus', 'rating_count', 'rating_1', 'rating_1_plus', 'rating_2', 'rating_2_plus',
        'rating_3', 'rating_3_plus', 'rating_4', 'rating_4_plus', 'rating_5',
        'price', 'min_price', 'max_price',
    ];

    /**
     * The store is passed in rather than read here: /store-categories/items takes it
     * from the `store_id` query parameter, /stores/popular-items from the path segment,
     * which may be a slug.
     */
    protected function storeItemFilters(Request $request, mixed $storeId): array
    {
        $searchFilters = $this->resolveSearchFilters($request);

        return [
            'store_id' => $storeId,
            'zone_ids' => $this->zoneIds($request) ?: null,
            'module_id' => $this->storeItemsModuleId($request),
            'type' => $request->query('type', 'all'),
            'sort_by' => $searchFilters['sort_by'],
            'filter_by' => $searchFilters['filter_by'],
            'search' => $request->query('name') ?? $request->query('search'),
            'inputs' => $request->only(self::STORE_ITEM_FILTER_INPUTS),
        ];
    }

    private function storeItemsModuleId(Request $request): ?int
    {
        $moduleHeader = $request->header('moduleId');
        $moduleId = $moduleHeader ? getModuleId($moduleHeader) : $this->currentModuleId();

        return is_numeric($moduleId) ? (int) $moduleId : null;
    }
}
