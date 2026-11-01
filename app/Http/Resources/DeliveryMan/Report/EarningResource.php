<?php

namespace App\Http\Resources\DeliveryMan\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class EarningResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'dm_tips' => (float) $this->resource->dm_tips,
            'original_delivery_charge' => (float) $this->resource->original_delivery_charge,
            'delivery_fee_comission' => (float) $this->resource->delivery_fee_comission,
        ]);
    }
}
