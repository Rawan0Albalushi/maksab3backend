<?php

namespace App\Http\Resources\Vendor\Item;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AssignableItemResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly int $categoryId = 0, private readonly ?string $moduleType = null, private readonly bool $isService = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $variations = $this->isService
            ? $this->resource->variations
            : ($this->moduleType === 'food' ? $this->resource->food_variations : $this->resource->variations);

        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            'image_full_url' => $this->isService ? $this->resource->thumbnail_full_url : $this->resource->image_full_url,
            'price' => $this->isService ? $this->resource->base_price : $this->resource->price,
            'store_category_id' => $this->resource->store_category_id ? (int) $this->resource->store_category_id : null,
            'is_assigned' => ((int) $this->resource->store_category_id === $this->categoryId),
            'variations_count' => count(Helpers::decodeJsonToArray($variations)),
        ]);
    }
}
