<?php

namespace App\Traits\Item;

use App\Services\Marketing\FlashSaleItemService;

trait ItemStockTrait
{
    public static function updateItemStock($item, $quantity, $variant = null)
    {
        if (isset($variant)) {
            $variations = is_array($item['variations']) ? $item['variations'] : json_decode($item['variations'], true);

            foreach ($variations as $key => $value) {
                if ($value['type'] == $variant) {
                    $variations[$key]['stock'] -= $quantity;
                }
            }
            $item['variations'] = json_encode($variations);
        }
        $item->stock -= $quantity;

        return $item;
    }

    public static function updateFlashSaleStock($item, $quantity, $decreaseStock = false)
    {
        $flashSaleItem = app(FlashSaleItemService::class)->findActiveRunningForItem($item->id);

        if ($flashSaleItem) {
            $flashSaleItem->sold = $decreaseStock
                ? max(0, $flashSaleItem->sold - $quantity)
                : $flashSaleItem->sold + $quantity;

            $flashSaleItem->available_stock = max(0, $flashSaleItem->stock - $flashSaleItem->sold);
        }

        return $flashSaleItem;
    }
}
