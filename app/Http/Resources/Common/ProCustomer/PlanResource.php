<?php

namespace App\Http\Resources\Common\ProCustomer;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class PlanResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'plan_name' => $this->resource->plan_name,
            'plan_type' => $this->resource->plan_type,
            'price' => (float) $this->resource->price,
            'duration' => (int) $this->resource->duration,
            'duration_label' => (int) $this->resource->duration . ' ' . translate('messages.days'),
            'status' => (int) $this->resource->status,
        ]);
    }
}
