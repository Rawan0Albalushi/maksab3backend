<?php

namespace App\Http\Resources\Customer\Promotion;

use App\CentralLogics\Helpers;
use App\Http\Resources\Common\Item\ItemResource;
use Illuminate\Http\Request;

class ItemCampaignResource extends ItemResource
{
    public function toArray(Request $request): array
    {
        $store = $this->store();

        return array_merge(
            [
                'id' => (int) $this->resource->id,
                'title' => $this->resource->title,
                'description' => $this->resource->description,
                'slug' => $this->resource->slug,
                'image_full_url' => $this->resource->image_full_url,

                'price' => (float) $this->resource->price,
                'discount' => $this->resource->discount,
                'discount_type' => $this->resource->discount_type,
                // Always zero, matching StackFood, which returns 0/0 for a campaign item whichever
                // promotion is running: a store-wide rate does not reach a campaign item, which
                // carries its own price, so quoting the store's rate here advertised a reduction
                // the checkout was never going to apply. The campaign's own `discount` above is
                // untouched -- that one is real, and it is why a campaign keeps showing a saving
                // on a screen where ordinary items have stopped.
                'store_discount' => 0.0,

                'stock' => (int) $this->resource->stock,
                'unit_type' => $this->resource->relationLoaded('unit') ? $this->resource->unit?->unit : null,
                'maximum_cart_quantity' => $this->resource->maximum_cart_quantity,
                'veg' => (int) $this->resource->veg,

                'available_date_starts' => $this->resource->start_date?->format('Y-m-d'),
                'available_date_ends' => $this->resource->end_date?->format('Y-m-d'),
                'available_time_starts' => $this->formatTime($this->resource->start_time),
                'available_time_ends' => $this->formatTime($this->resource->end_time),

                'category_id' => $this->resource->category_id,
                'category_ids' => Helpers::decodeJsonToArray($this->resource->category_ids),
            ],
            $this->moduleFields(),
            $this->storeFields($store),
        );
    }

    protected function availableDateStarts(): ?string
    {
        return $this->resource->start_date?->format('Y-m-d');
    }
}
