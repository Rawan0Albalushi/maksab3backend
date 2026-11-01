<?php

namespace App\Http\Resources\Common\Item;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;

class ItemOfferResource extends ItemListResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $store = $item->store;
        $discount = Helpers::product_discount_calculate($item, $item->price, $store, true);
        // While ANY store-wide rate is in force the item is shown at base price everywhere, this
        // screen included -- so the "discounted" price is the price. The reduction appears once,
        // on the checkout summary. See ItemResource::storeWideRunning() for why.
        $reduction = $this->storeWideRunning($store) ? 0.0 : (float) ($discount['discount_amount'] ?? 0);

        return array_merge(parent::toArray($request), [
            'discounted_price' => max(0, round((float) $item->price - $reduction, 2)),
            'discount_type' => $discount['original_discount_type'] ?? $item->discount_type,
            'store_image' => $store?->logo_full_url,
            'wishlist' => (int) ($item->is_wishlisted ?? 0),
        ]);
    }
}
