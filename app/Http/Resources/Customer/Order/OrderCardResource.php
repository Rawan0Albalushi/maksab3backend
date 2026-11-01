<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use App\Traits\Api\OrderPayloadTrait;
use Illuminate\Http\Request;

class OrderCardResource extends BaseResource
{
    use OrderPayloadTrait;

    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $items = $this->orderItemRows($order);
        $preview = array_slice($items, 0, self::ITEMS_PREVIEW_LIMIT);

        return array_merge(parent::toArray($request), [
            'order_id' => (int) $order->id,
            'module_id' => (int) $order->module_id,
            'order_amount' => (float) $order->order_amount,
            'created_at' => $order->created_at,
            'store' => $this->orderStoreCard($order->store),
            'items_preview' => $preview,
            'extra_items_count' => count($items) - count($preview),
            'item_count' => count($items),
            // `!== false` so a row that was never flagged -- no zoneId header to judge by, or
            // an order whose store row is gone -- keeps whatever can_reorder said on its own.
            'can_reorder' => $order->can_reorder && $order->getAttribute('store_zone_reachable') !== false,
            'is_bogo' => $order->is_bogo,
        ]);
    }
}
