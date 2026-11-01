<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Common\Item\ItemResource;
use Illuminate\Http\Request;

class FlashSaleItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'stock' => $this->resource->stock,
            'sold' => $this->resource->sold,
            'item' => $this->resource->relationLoaded('item') && $this->resource->item
                ? new ItemResource($this->resource->item)
                : null,
        ]);
    }
}
