<?php

namespace App\Http\Resources\Vendor\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AttributeResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'translations' => $this->resource->relationLoaded('translations')
                ? $this->resource->translations
                : [],
        ]);
    }
}
