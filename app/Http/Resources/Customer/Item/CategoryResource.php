<?php

namespace App\Http\Resources\Customer\Item;

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
            'module_id' => $this->resource->module_id,
            'featured' => (int) $this->resource->featured,
            'childes' => $this->childes(),
            'order_count' => $this->when(
                $this->resource->total_order_count !== null,
                fn () => (int) $this->resource->total_order_count
            ),
        ]);
    }

    protected function childes(): array
    {
        if (! $this->resource->relationLoaded('childes')) {
            return [];
        }

        return $this->resource->childes
            ->map(fn ($child) => [
                'id' => (int) $child->id,
                'name' => $child->name,
                'slug' => $child->slug,
            ])
            ->values()
            ->all();
    }
}
