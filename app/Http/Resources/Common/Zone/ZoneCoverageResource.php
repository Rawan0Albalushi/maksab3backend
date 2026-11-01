<?php

namespace App\Http\Resources\Common\Zone;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ZoneCoverageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'status' => (int) $this->resource->status,
            'cash_on_delivery' => $this->resource->cash_on_delivery,
            'digital_payment' => $this->resource->digital_payment,
            'offline_payment' => $this->resource->offline_payment,
            'increased_delivery_fee_status' => $this->resource->increased_delivery_fee_status,
            'increase_delivery_charge_message' => $this->resource->increase_delivery_charge_message,
            'modules' => ZoneModuleResource::collection($this->whenLoaded('modules')),
        ]);
    }
}
