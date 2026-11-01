<?php

namespace App\Http\Resources\DeliveryMan\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class OrderStatusCountResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'count' => (int) $this->resource['count'],
        ];
    }
}
