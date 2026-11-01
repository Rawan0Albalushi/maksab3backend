<?php

namespace App\Http\Resources\DeliveryMan\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class LoyaltyPointResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'transaction_id' => $this->resource->transaction_id,
            'transaction_type' => $this->resource->transaction_type,
            'converted_amount' => $this->resource->converted_amount,
            'point' => $this->resource->point,
            'created_at' => $this->resource->created_at,
            'reference' => $this->whenHas('reference'),
            'point_conversion_type' => $this->whenHas('point_conversion_type'),
        ];
    }
}
