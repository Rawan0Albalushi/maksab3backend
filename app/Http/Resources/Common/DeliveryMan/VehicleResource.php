<?php

namespace App\Http\Resources\Common\DeliveryMan;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class VehicleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'type' => $this->resource->type,
            'name' => $this->resource->name,
        ]);
    }
}
