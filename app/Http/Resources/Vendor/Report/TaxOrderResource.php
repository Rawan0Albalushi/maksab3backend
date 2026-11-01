<?php

namespace App\Http\Resources\Vendor\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TaxOrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), array_merge(
            $this->resource->attributesToArray(),
            [
                'order_taxes' => $this->resource->relationLoaded('orderTaxes')
                    ? $this->resource->orderTaxes
                    : [],
                'module' => $this->resource->relationLoaded('module')
                    ? $this->resource->module
                    : null,
            ]
        ));
    }
}
