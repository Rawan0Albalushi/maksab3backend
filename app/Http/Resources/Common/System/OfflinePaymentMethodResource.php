<?php

namespace App\Http\Resources\Common\System;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class OfflinePaymentMethodResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'method_name' => $this->resource->method_name,
            'method_fields' => $this->resource->method_fields ?? [],
            'method_informations' => $this->resource->method_informations ?? [],
        ]);
    }
}
