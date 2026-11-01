<?php

namespace App\Services\Item;

use App\Models\Brand;
use App\Services\BaseService;
use App\Traits\Item\ItemRelationsTrait;
use App\Traits\System\DropdownDataTrait;
use App\Traits\System\PrioritySettingsTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\System\BusinessSettingService;
use App\Support\Storage\FileStorage;

class BrandService extends BaseService
{
    use DropdownDataTrait;
    use PrioritySettingsTrait;
    use ItemRelationsTrait;

    private const DEFAULT_STATUS = 1;

    public function getAddData(array $input): array
    {
        return [
            'status' => ($input['brand_status'] ?? null) ? 1 : 0,
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'image' => FileStorage::upload('brand/', ($input['image'] ?? null)),
        ];
    }

    public function getUpdateData(array $input, object $brand): array
    {
        $slug = Str::slug(($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))]);

        return [
            'status' => ($input['brand_status'] ?? null) ? 1 : 0,
            'slug' => $brand->slug ? $brand->slug : "{$slug}{$brand->id}",
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'image' => array_key_exists('image', $input) ? FileStorage::update('brand/', $brand->image, ($input['image'] ?? null)) : $brand->image,
        ];
    }

    public function getTranslatedLocales(array $brandIds): array
    {
        if (empty($brandIds)) {
            return [];
        }

        return DB::table('translations')
            ->where('translationable_type', Brand::class)
            ->whereIn('translationable_id', $brandIds)
            ->where('key', 'name')
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->orderBy('locale')
            ->get(['translationable_id', 'locale'])
            ->groupBy('translationable_id')
            ->map(fn ($rows) => $rows->pluck('locale')->unique()->values()->all())
            ->all();
    }

    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        bool $withTrashed = false,
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildBrandQuery($filters)
            ->with($with)
            ->withCount($withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getBrandItems(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return app(ItemService::class)->getBrandItemQuery($filters, $this->itemSortSpec())
            ->with($this->itemRelationSet() + $with)
            ->withCount($withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    private function buildBrandQuery(array $filters): Builder
    {
        $sortBy = $this->findPrioritySetting(name: 'brand_sort_by_general', type: 'general');
        $customSort = (app(BusinessSettingService::class)->value('brand_default_status') ?? self::DEFAULT_STATUS) != self::DEFAULT_STATUS;

        $query = Brand::translateOnly(['name'])
            ->withStorage()
            ->active()
            ->where(function ($query) use ($filters) {
                $query->whereNull('module_id')->orWhere('module_id', $filters['module_id'] ?? null);
            })
            ->withCount(['items as items_count' => function ($query) use ($filters) {
                $query->select(DB::raw('count(distinct ecommerce_item_details.item_id)'))
                    ->whereHas('item', fn ($q) => $this->constrainCountedItems($q, $filters));
            }]);

        if (($customSort && $sortBy === 'order_count') || ($filters['top'] ?? null) == 1) {
            return $this->orderByItemOrderCount($query, $filters);
        }

        return match ($customSort ? $sortBy : null) {
            'latest' => $query->latest(),
            'oldest' => $query->oldest(),
            'a_to_z' => $query->orderBy('name'),
            'z_to_a' => $query->orderBy('name', 'desc'),
            default => $query,
        };
    }

    private function orderByItemOrderCount(Builder $query, array $filters): Builder
    {
        $orderCounts = app(ItemService::class)->getBrandOrderCountQuery($filters);

        return $query
            ->leftJoinSub($orderCounts, 'brand_order_counts', 'brand_order_counts.brand_id', '=', 'brands.id')
            ->orderByDesc(DB::raw('COALESCE(brand_order_counts.order_count, 0)'));
    }

    private function constrainCountedItems($query, array $filters): void
    {
        $moduleId = $filters['module_id'] ?? null;
        $itemSort = $this->itemSortSettings();

        $query->active(zone_ids: $filters['zone_ids'] ?? null, module_id: $moduleId)
            ->where('module_id', $moduleId)
            ->when($itemSort['remove_out_of_stock'], fn ($q) => $q->where('stock', '>', 0))
            ->when($itemSort['remove_temp_closed'], fn ($q) => $q->whereHas('store', fn ($q) => $q->where('active', 1)));
    }

    private function itemSortSpec(): array
    {
        $settings = $this->itemSortSettings();

        return [
            'is_default' => ! $settings['is_custom'],
            'sort_by' => $this->findPrioritySetting(name: 'brand_item_sort_by_general', type: 'general'),
            'unavailable' => $settings['unavailable'],
            'temp_closed' => $settings['temp_closed'],
        ];
    }

    private function itemSortSettings(): array
    {
        $defaultStatus = app(BusinessSettingService::class)->value('brand_item_default_status') ?? self::DEFAULT_STATUS;
        $unavailable = $this->findPrioritySetting(name: 'brand_item_sort_by_unavailable', type: 'unavailable');
        $tempClosed = $this->findPrioritySetting(name: 'brand_item_sort_by_temp_closed', type: 'temp_closed');

        return [
            'is_custom' => $defaultStatus != self::DEFAULT_STATUS,
            'unavailable' => $unavailable,
            'temp_closed' => $tempClosed,
            'remove_out_of_stock' => $defaultStatus != self::DEFAULT_STATUS
                && data_get(config('module.current_module_data'), 'module_type') !== 'food'
                && $unavailable === 'remove',
            'remove_temp_closed' => $defaultStatus != self::DEFAULT_STATUS && $tempClosed === 'remove',
        ];
    }
}
