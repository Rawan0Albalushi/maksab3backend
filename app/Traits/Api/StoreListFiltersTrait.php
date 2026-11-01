<?php

namespace App\Traits\Api;

use App\Traits\Item\ItemFilterTrait;
use Illuminate\Http\Request;

trait StoreListFiltersTrait
{
    use ApiRequestContextTrait;
    use ItemFilterTrait;

    public function storeListFilters(Request $request, string $filterData = 'all'): array
    {
        $filter = $request->query('filter', '');
        $filter = $filter ? (is_array($filter) ? $filter : str_getcsv(trim($filter, '[]'), ',')) : '';

        return [
            'zone_id' => $request->header('zoneId'),
            'longitude' => is_numeric($request->header('longitude')) ? (float) $request->header('longitude') : 0.0,
            'latitude' => is_numeric($request->header('latitude')) ? (float) $request->header('latitude') : 0.0,
            'module_id' => $this->headerModuleId($request),
            'type' => $request->query('type', 'all'),
            'store_type' => $request->query('store_type', 'all'),
            'filter_data' => $filterData,
            'featured' => $request->query('featured'),
            'filter' => $filter,
            'rating_count' => $request->query('rating_count'),
            'category_id' => $request->input('category_id'),
            'category_ids' => self::categoryIdArray($request->input('category_ids')),
            'name' => $request->input('name'),
            'store_filter' => $this->storeFilterInputs($request),
            'sort_by' => $request->query('sort_by'),
            'filter_by' => self::resolveSearchFilters($request, $filter)['filter_by'],
            'scope_input' => $request->all(),
            'user_id' => auth('api')->id(),
        ];
    }
}
