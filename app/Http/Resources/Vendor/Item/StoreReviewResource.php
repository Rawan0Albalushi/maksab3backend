<?php

namespace App\Http\Resources\Vendor\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BaseResource;

class StoreReviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $item = $review->item;

        $data = $review->attributesToArray();
        $data['attachment'] = json_decode($review->attachment);
        $data['attachment_full_url'] = $review->attachment_full_url;
        $data['item_name'] = $item?->name;
        $data['item_image'] = $item?->image;
        $data['customer_name'] = $review->customer ? $review->customer->f_name.' '.$review->customer->l_name : null;

        if ($item) {
            $translated = array_column($item->translations->toArray(), 'value', 'key');
            $data['item_name'] = $translated['name'] ?? $item->name;
            $data['item_image_full_url'] = $item->image_full_url;
        }

        return $data;
    }
}
