<?php

namespace App\Http\Resources\Common\Item;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;

class ItemListResource extends ItemResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), $this->listFields());
    }

    private function listFields(): array
    {
        $item = $this->resource;
        $store = $item->store;

        return [
            'has_variant' => (int) $this->variantCount(),
            'recommended' => $item->recommended,
            'store_category_id' => (int) $item->store_category_id,
            'store_category_name' => $item->storeCategory?->name,
            'store_logo_full_url' => $store?->logo_full_url,
            'maximum_cart_quantity' => (int) ($item->maximum_cart_quantity ?? 0),
            'module_type' => $store?->module_type,
            'rating_count' => (int) ($item->rating ? array_sum(Helpers::decodeJsonToArray($item->rating)) : 0),
            'avg_rating' => (float) ($item->avg_rating ?? 0),
        ];
    }

    private function variantCount(): int
    {
        $variants = $this->resource->store?->module_type === 'food'
            ? $this->resource->food_variations
            : $this->resource->variations;

        $variants = is_string($variants) ? json_decode($variants, true) : $variants;

        return is_array($variants) ? count($variants) : 0;
    }
}
