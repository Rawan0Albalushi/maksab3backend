<?php

namespace App\Services\Marketing;

use App\Models\FlashSaleItem;
use App\Services\BaseService;
use App\Traits\Item\ItemRelationsTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class FlashSaleItemService extends BaseService
{
    use ItemRelationsTrait;

    private const LIST_COLUMNS = ['id', 'item_id', 'stock', 'sold'];

    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $zoneIds = $filters['zone_ids'] ?? [];

        return FlashSaleItem::where('flash_sale_id', $filters['flash_sale_id'] ?? null)
            ->where('available_stock', '>', 0)
            ->active()
            ->with(['item' => fn ($query) => $query->with($this->itemRelationSet())])
            ->whereHas('item.store', fn ($query) => $query->whereIn('zone_id', $zoneIds))
            ->whereHas('item', fn ($query) => $query->active())
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findActiveRunningForItem(mixed $itemId): ?FlashSaleItem
    {
        return FlashSaleItem::active()
            ->whereHas('flashSale', fn ($query) => $query->active()->running())
            ->where(['item_id' => $itemId])
            ->first();
    }


    public function runningQuery(): mixed
    {
        return FlashSaleItem::Active()->with('flashSale')->whereHas('flashSale', function ($query) {
            $query->Active()->Running();
        });
    }
}
