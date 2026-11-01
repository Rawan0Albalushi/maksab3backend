<?php

namespace App\Http\Resources\Vendor\Item;

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
            'items_count' => $this->when(isset($this->resource->items_count), fn () => (int) $this->resource->items_count),
            'created_at' => $this->resource->created_at,
        ]);
    }

    public function withTranslations(): array
    {
        return [
            'translations' => $this->resource->relationLoaded('translations')
                ? $this->resource->translations->map(fn ($row) => [
                    'locale' => $row->locale,
                    'key' => $row->key,
                    'value' => $row->value,
                ])->values()
                : [],
        ];
    }
}
