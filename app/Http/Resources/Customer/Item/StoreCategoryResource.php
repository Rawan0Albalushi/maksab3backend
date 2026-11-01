<?php

namespace App\Http\Resources\Customer\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class StoreCategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'store_id' => (int) $this->resource->store_id,
            'module_id' => $this->resource->module_id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'image' => $this->resource->image,
            'image_full_url' => $this->resource->image_full_url,
            'priority' => (int) $this->resource->priority,
            'status' => (int) $this->resource->status,
        ]);
    }
}
