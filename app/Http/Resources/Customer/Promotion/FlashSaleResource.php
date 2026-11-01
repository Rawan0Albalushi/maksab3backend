<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class FlashSaleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'end_date' => $this->resource->end_date,
            'active_products' => $this->activeProducts($request),
        ]);
    }

    private function activeProducts(Request $request): array
    {
        if (! $this->resource->relationLoaded('activeProducts')) {
            return [];
        }

        return $this->resource->activeProducts
            ->map(fn ($product) => array_merge(
                ['flash_sale_id' => $product->flash_sale_id],
                (new FlashSaleItemResource($product))->toArray($request)
            ))
            ->values()
            ->all();
    }
}
