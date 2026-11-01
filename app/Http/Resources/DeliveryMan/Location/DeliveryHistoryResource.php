<?php

namespace App\Http\Resources\DeliveryMan\Location;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class DeliveryHistoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'order_id' => $this->resource->order_id,
            'delivery_man_id' => $this->resource->delivery_man_id,
            'time' => $this->resource->time,
            'longitude' => $this->resource->longitude,
            'latitude' => $this->resource->latitude,
            'location' => $this->resource->location,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
