<?php

namespace App\Services\Marketing;

use App\Models\FlashSale;
use App\Services\BaseService;
use App\Traits\Item\ItemRelationsTrait;
use App\Traits\Customer\PersonalizationTrait;

class FlashSaleService extends BaseService
{
    use PersonalizationTrait;

    use ItemRelationsTrait;

    private const DETAIL_COLUMNS = ['id', 'end_date'];

    private const PRODUCT_COLUMNS = ['id', 'flash_sale_id', 'item_id', 'stock', 'sold'];

    public function getRunning(array $filters = []): mixed
    {
        $flashSale = $this->runningQuery($filters)
            ->with([
                'activeProducts' => fn ($query) => $query
                    ->select(self::PRODUCT_COLUMNS)
                    ->with(['item' => fn ($item) => $item->with($this->itemRelationSet())]),
            ])
            ->whereHas('activeProducts.item.store', fn ($query) => $this->storeInZone($query, $filters))
            ->first(self::DETAIL_COLUMNS);

        if ($flashSale && ! empty($filters['customer_id'])) {
            $flashSale->setRelation('activeProducts', $this->reorderByPreference(
                $flashSale->activeProducts,
                $filters['customer_id'],
                'item.category_id',
                'category'
            ));
        }

        return $flashSale;
    }

    public function findRunning(array $filters = []): mixed
    {
        return $this->runningQuery($filters)->first(self::DETAIL_COLUMNS);
    }

    public function getRunningTitles(): mixed
    {
        return FlashSale::active()->running()->get(['id', 'title']);
    }

    private function runningQuery(array $filters): mixed
    {
        $zoneIds = $filters['zone_ids'] ?? [];

        return FlashSale::withoutGlobalScope('translate')
            ->module($filters['module_id'] ?? null)
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zoneIds))
            ->running()
            ->active();
    }

    private function storeInZone(mixed $query, array $filters): void
    {
        $moduleId = $filters['module_id'] ?? null;

        $query->when($moduleId, fn ($store) => $store
            ->where('module_id', $moduleId)
            ->whereHas('zone.modules', fn ($zone) => $zone->where('modules.id', $moduleId)))
            ->whereIn('zone_id', $filters['zone_ids'] ?? []);
    }

}
