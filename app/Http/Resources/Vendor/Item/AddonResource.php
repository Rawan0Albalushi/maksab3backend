<?php

namespace App\Http\Resources\Vendor\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AddonResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'price' => $this->resource->price,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'store_id' => $this->resource->store_id,
            'status' => $this->resource->status,
            'addon_category_id' => $this->resource->addon_category_id,
            'tax_ids' => $this->resource->relationLoaded('taxVats')
                ? $this->resource->taxVats->pluck('tax_id')->all()
                : [],
            'translations' => $this->resource->relationLoaded('translations')
                ? $this->resource->translations
                : [],
        ]);
    }
}
