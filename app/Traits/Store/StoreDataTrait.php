<?php

namespace App\Traits\Store;

use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use App\Services\Item\ItemService;
use App\Services\Marketing\FlashSaleService;
use App\Services\Marketing\ItemCampaignService;

trait StoreDataTrait
{
    public function topItemModels(array $storeIds, int $limit = 5): SupportCollection
    {
        $storeIds = array_values(array_unique(array_filter(array_map('intval', $storeIds))));
        if (empty($storeIds)) {
            return collect();
        }

        return app(ItemService::class)->getTopByStores($storeIds, $limit);
    }
    public function topItemRows(array $storeIds, int $limit = 5): SupportCollection
    {
        return $this->topItemModels($storeIds, $limit)->map(fn ($items) => $items->map(fn ($item) => [
            'id' => (int) $item->id,
            'name' => $item->name,
            'image_full_url' => $item->image_full_url,
            'price' => (float) $item->price,
            'discount' => (float) $item->discount,
            'discount_type' => $item->discount_type,
            'order_count' => (int) $item->order_count,
            'avg_rating' => (float) ($item->avg_rating ?? 0),
        ])->values()->all());
    }
    public function topCategories(array $storeIds, int $limit = 5): SupportCollection
    {
        $storeIds = array_values(array_unique(array_filter(array_map('intval', $storeIds))));
        if (empty($storeIds)) {
            return collect();
        }

        return DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('order_details', 'order_details.item_id', '=', 'items.id')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->selectRaw('items.store_id, CAST(categories.id AS UNSIGNED) as id, categories.parent_id, categories.name, COUNT(order_details.id) as order_count')
            ->whereIn('items.store_id', $storeIds)
            ->where('categories.status', 1)
            ->whereNotIn('orders.order_status', ['failed', 'canceled'])
            ->groupBy('items.store_id', 'id', 'categories.parent_id', 'categories.name')
            ->orderByDesc('order_count')
            ->get()
            ->groupBy('store_id')
            ->map(fn ($group) => $this->topCategoryRow($group->take(max(1, $limit))));
    }
    public function calculateRating($ratings): array
    {
        $totalSubmit = $ratings[0] + $ratings[1] + $ratings[2] + $ratings[3] + $ratings[4];
        $positiveSubmit = $ratings[0] + $ratings[1] + $ratings[2];
        $rating = ($ratings[0] * 5 + $ratings[1] * 4 + $ratings[2] * 3 + $ratings[3] * 2 + $ratings[4]) / ($totalSubmit ?: 1);

        return [
            'rating' => round($rating, 2),
            'total' => $totalSubmit,
            'positive_rating' => $totalSubmit > 0 ? (($positiveSubmit * 100) / $totalSubmit) : 0,
        ];
    }
    public function updateRating($ratings, $productRating): string
    {
        $storeRatings = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        if ($ratings) {
            $storeRatings[1] = $ratings[4];
            $storeRatings[2] = $ratings[3];
            $storeRatings[3] = $ratings[2];
            $storeRatings[4] = $ratings[1];
            $storeRatings[5] = $ratings[0];
            $storeRatings[$productRating] = $ratings[5 - $productRating] + 1;
        } else {
            $storeRatings[$productRating] = 1;
        }

        return json_encode($storeRatings);
    }
    public function offersByStore(array $storeIds): SupportCollection
    {
        $storeIds = array_values(array_unique(array_filter(array_map('intval', $storeIds))));
        if (empty($storeIds)) {
            return collect();
        }

        $offers = array_fill_keys($storeIds, []);

        $flashSaleStores = DB::table('flash_sale_items')
            ->join('items', 'items.id', '=', 'flash_sale_items.item_id')
            ->whereIn('items.store_id', $storeIds)
            ->select('flash_sale_items.flash_sale_id', 'items.store_id')
            ->distinct()
            ->get()
            ->groupBy('flash_sale_id');

        foreach (app(FlashSaleService::class)->getRunningTitles() as $flashSale) {
            if (! $flashSale->title) {
                continue;
            }
            foreach (($flashSaleStores[$flashSale->id] ?? collect()) as $row) {
                $offers[(int) $row->store_id][] = [
                    'type' => 'flash_sale',
                    'id' => (int) $flashSale->id,
                    'name' => $flashSale->title,
                ];
            }
        }

        foreach (app(ItemCampaignService::class)->getRunningForStores($storeIds, ['id', 'title', 'store_id']) as $campaign) {
            if (! $campaign->title) {
                continue;
            }
            $offers[(int) $campaign->store_id][] = [
                'type' => 'item_campaign',
                'id' => (int) $campaign->id,
                'name' => $campaign->title,
            ];
        }

        return collect($offers);
    }
    public function itemCountsByStore(array $storeIds): SupportCollection
    {
        $storeIds = array_values(array_unique(array_filter(array_map('intval', $storeIds))));
        if (empty($storeIds)) {
            return collect();
        }

        return app(ItemService::class)->getCountsByStores($storeIds);
    }
    private function topCategoryRow($rows): array
    {
        $categoryIds = [];
        $categoryNames = [];
        $pairs = [];
        $pairedIds = [];

        foreach ($rows as $row) {
            if ($row->id != 0) {
                $categoryIds[] = (int) $row->id;
                $categoryNames[] = $row->name;

                // 'ids'/'names' also fold in a bare parent_id below, which has no name of its own
                // and would misalign an id-to-name pairing built from them after the fact -- built
                // here instead, straight off the rows that actually have both.
                if (! in_array((int) $row->id, $pairedIds, true)) {
                    $pairedIds[] = (int) $row->id;
                    $pairs[] = ['id' => (int) $row->id, 'name' => $row->name];
                }
            }
            if ($row->parent_id != 0) {
                $categoryIds[] = (int) $row->parent_id;
            }
        }

        return [
            'ids' => array_values(array_unique($categoryIds)),
            'names' => array_values(array_unique($categoryNames)),
            'pairs' => $pairs,
        ];
    }
}
