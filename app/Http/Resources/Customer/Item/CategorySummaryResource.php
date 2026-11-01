<?php

namespace App\Http\Resources\Customer\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CategorySummaryResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly int $itemsCount = 0)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            'image_full_url' => $this->resource->image_full_url ?? null,
            'items_count' => $this->itemsCount,
        ]);
    }
}
