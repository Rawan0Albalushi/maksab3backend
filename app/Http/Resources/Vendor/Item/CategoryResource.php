<?php

namespace App\Http\Resources\Vendor\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'image_full_url' => $this->resource->image_full_url,
            'parent_id' => $this->resource->parent_id,
            'priority' => $this->resource->priority,
            'featured' => (int) $this->resource->featured,
            'products_count' => (int) ($this->resource->products_count ?? 0),
            'childs_count' => (int) ($this->resource->childs_count ?? 0),
        ]);
    }
}
