<?php

namespace App\Http\Resources\Common\Payment;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class WithdrawalMethodResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'method_name' => $this->resource->method_name,
            'method_fields' => $this->resource->method_fields,
            'is_default' => $this->resource->is_default,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
