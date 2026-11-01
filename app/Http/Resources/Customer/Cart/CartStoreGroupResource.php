<?php

namespace App\Http\Resources\Customer\Cart;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CartStoreGroupResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $store = $this->resource['store'] ?? null;
        $carts = $this->resource['carts'];
        [$minDeliveryTime, $maxDeliveryTime] = $this->deliveryWindow($store?->delivery_time);

        return array_merge(parent::toArray($request), [
            'store' => [
                'id' => $store?->id ?? $this->resource['store_id'],
                'name' => $store?->name,
                'slug' => $store?->slug,
                'module_type' => $store?->module_type,
                'logo_full_url' => $store?->logo_full_url,
                'item_count' => $carts->count(),
                'delivery_time' => $store?->delivery_time,
                'min_delivery_time' => $minDeliveryTime,
                'max_delivery_time' => $maxDeliveryTime,
                'distance' => (float) ($store?->distance ?? 0),
                'distance_km' => isset($store->distance) ? round(((float) $store->distance) / 1000, 2) : 0,
            ],
            'carts' => CartResource::collection($carts),
        ]);
    }

}
