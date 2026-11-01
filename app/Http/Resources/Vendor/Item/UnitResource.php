<?php

namespace App\Http\Resources\Vendor\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class UnitResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'unit' => $this->resource->unit,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'translations' => $this->resource->relationLoaded('translations')
                ? $this->resource->translations
                : [],
        ]);
    }
}
