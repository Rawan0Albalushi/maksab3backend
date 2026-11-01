<?php

namespace App\Traits\Api;

use App\Traits\Item\ItemFilterTrait;
use Illuminate\Http\Request;

trait ItemListFiltersTrait
{
    use ApiRequestContextTrait;
    use ItemFilterTrait;

    public function itemListFilters(Request $request): array
    {
        return [
            'zone_id' => $request->header('zoneId'),
            'type' => $request->query('type', 'all'),
            'product_id' => $request->query('product_id'),
            'min' => $request->query('min_price'),
            'max' => $request->query('max_price'),
            'filter' => $this->csvList($request->input('filter')),
            'category_ids' => $this->csvList($request->input('category_ids'), true),
            'brand_ids' => $this->idList($request, 'brand_ids'),
            'rating_count' => $request->query('rating_count'),
            'store_category_id' => $request->query('store_category_id'),
            'store_id' => $request->input('store_id'),
            'category_id' => $request->input('category_id'),
            'search' => $request->input('search'),
            'user_id' => auth('api')->id(),
        ];
    }

    public function itemSearchFilters(Request $request): array
    {
        $resolved = self::resolveSearchFilters($request, $this->csvList($request->input('filter')));

        return array_merge($this->itemListFilters($request), [
            'name' => $request->input('name'),
            'sort_by' => $resolved['sort_by'],
            'filter_by' => $resolved['filter_by'],
            'scope_input' => $request->all(),
        ]);
    }

    public function offerItemFilters(Request $request): array
    {
        $resolved = self::resolveSearchFilters($request, $request->query('filter'));
        $search = trim((string) $request->query('search'));

        return [
            'module_id' => $this->headerModuleId($request),
            'zone_ids' => $this->zoneIds($request),
            'user_id' => auth('api')->id(),
            'type' => $request->query('type', 'all') ?? 'all',
            'search' => $search !== '' ? $search : null,
            'category_ids' => $this->idList($request, 'category_ids'),
            'brand_ids' => $this->idList($request, 'brand_ids'),
            'sort_by' => $resolved['sort_by'],
            'filter_by' => $resolved['filter_by'],
            'store_category_id' => $request->query('store_category_id'),
            'scope_input' => $request->all(),
        ];
    }

    public function offerStoreFilters(Request $request): array
    {
        if (! $request->filled('quick_action') && $request->filled('store_type')) {
            $request->merge(['quick_action' => $request->query('store_type')]);
        }

        return [
            'module_id' => $this->headerModuleId($request),
            'zone_ids' => $this->zoneIds($request),
            'longitude' => is_numeric($request->header('longitude')) ? (float) $request->header('longitude') : 0.0,
            'latitude' => is_numeric($request->header('latitude')) ? (float) $request->header('latitude') : 0.0,
            'search' => $request->query('search'),
            'filter' => $this->storeFilterInputs($request),
        ];
    }

    private function csvList(mixed $value, bool $json = false): mixed
    {
        if (! $value) {
            return '';
        }

        if (is_array($value)) {
            return $value;
        }

        return $json ? json_decode($value) : str_getcsv(trim($value, '[]'), ',', '"', '\\');
    }

    private function idList(Request $request, string $key): array
    {
        return self::categoryIdArray($request->query($key));
    }
}
