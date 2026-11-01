<?php

namespace App\Http\Resources\Common\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class BrandResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'image_full_url' => $this->image_full_url,
            'items_count' => (int) ($this->items_count ?? 0),
        ]);
    }
}
