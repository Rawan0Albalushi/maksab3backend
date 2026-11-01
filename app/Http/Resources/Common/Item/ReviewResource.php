<?php

namespace App\Http\Resources\Common\Item;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ReviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->resource->relationLoaded('customer') ? $this->resource->customer : null;

        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'comment' => $this->resource->comment,
            'rating' => (float) $this->resource->rating,
            'attachment' => Helpers::decodeJsonToArray($this->resource->attachment),
            'attachment_full_url' => $this->resource->attachment_full_url,
            'reply' => $this->resource->reply,
            'created_at' => $this->resource->created_at,
            'customer' => $customer ? [
                'id' => (int) $customer->id,
                'f_name' => $customer->f_name,
                'l_name' => $customer->l_name,
                'image_full_url' => $customer->image_full_url,
            ] : null,
        ]);
    }
}
