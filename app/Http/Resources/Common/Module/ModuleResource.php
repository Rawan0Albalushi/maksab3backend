<?php

namespace App\Http\Resources\Common\Module;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ModuleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'module_name' => $this->resource->module_name,
            'module_type' => $this->resource->module_type,
            'slug' => $this->resource->slug,
            'short_description' => $this->resource->short_description,
            'icon_full_url' => $this->resource->icon_full_url,
            'thumbnail_full_url' => $this->resource->thumbnail_full_url,
            'stores_count' => (int) ($this->resource->stores_count ?? 0),
            'min_delivery_time' => $this->resource->min_delivery_time_range ?: null,
            'flash_sale' => ((int) ($this->resource->flash_sale_count ?? 0)) > 0 ? 1 : 0,
            'free_delivery' => ((int) ($this->resource->free_delivery_count ?? 0)) > 0 ? 1 : 0,
            'top_offer' => (new TopOfferResource($this->resource))->resolve(),
        ]);
    }
}
