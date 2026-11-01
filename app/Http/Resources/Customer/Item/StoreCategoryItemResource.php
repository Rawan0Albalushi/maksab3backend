<?php

namespace App\Http\Resources\Customer\Item;

use App\CentralLogics\Helpers;
use App\Http\Resources\Common\Item\ItemResource;
use Illuminate\Http\Request;

class StoreCategoryItemResource extends ItemResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), $this->menuFields());
    }

    private function menuFields(): array
    {
        $store = $this->store();
        $verified = $store ? Helpers::get_verified_seller_status($store, $store->storeConfig) : 0;
        $foodVariations = Helpers::decodeJsonToArray($this->resource->food_variations);
        $variations = $this->menuVariations();

        return [
            'base_price' => (float) $this->resource->price,
            'available_date_starts' => null,
            'maximum_cart_quantity' => (int) ($this->resource->maximum_cart_quantity ?? 0),
            'variations' => $variations,
            'food_variations' => $foodVariations,
            'has_variant' => (int) ($this->moduleType() === 'food' ? count($foodVariations) : count($variations)),
            'status' => (int) $this->resource->status,
            'store_logo_full_url' => $store?->logo_full_url,
            'store' => [
                'free_delivery' => $store?->free_delivery,
                'logo_full_url' => $store?->logo_full_url,
                'verified_seller' => $verified,
            ],
            'store_details' => [
                'logo_full_url' => $store?->logo_full_url,
                'verified_seller' => $verified,
            ],
        ];
    }

    private function menuVariations(): array
    {
        return array_map(fn ($variation) => [
            'variant_key' => $variation['type'] ?? null,
            'name' => $variation['type'] ?? null,
            'price' => (float) ($variation['price'] ?? 0),
            'stock' => (int) ($variation['stock'] ?? 0),
        ], Helpers::decodeJsonToArray($this->resource->variations));
    }

    private function moduleType(): ?string
    {
        return $this->resource->relationLoaded('module') ? $this->resource->module?->module_type : null;
    }
}
