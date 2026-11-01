<?php

namespace App\Http\Resources\Customer\ProCustomer;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class SubscriptionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'plan_id' => $this->resource->plan_id,
            'plan_name' => $this->resource->plan_name,
            'plan_type' => $this->resource->plan_type,
            'plan_price' => (float) $this->resource->plan_price,
            'start_at' => optional($this->resource->start_at)->toIso8601String(),
            'end_at' => optional($this->resource->end_at)->toIso8601String(),
            'status' => $this->resource->status,
        ]);
    }
}
