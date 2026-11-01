<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CashBackCalculationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource['id'] ?? null,
            'calculated_amount' => (float) ($this->resource['calculated_amount'] ?? 0),
            'cashback_amount' => $this->resource['cashback_amount'] ?? 0,
            'cashback_type' => $this->resource['cashback_type'] ?? '',
            'min_purchase' => $this->resource['min_purchase'] ?? 0,
            'max_discount' => $this->resource['max_discount'] ?? 0,
        ]);
    }
}
