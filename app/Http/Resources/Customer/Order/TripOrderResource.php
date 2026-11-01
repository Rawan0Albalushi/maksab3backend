<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TripOrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), ['order_type' => 'trip']);
    }
}
