<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class OrderActivityResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'order_type' => $this->resource->order_type,
            'status' => $this->resource->status,
            'is_repeat' => 0,
            'created_at' => $this->resource->created_at,
        ]);
    }
}
