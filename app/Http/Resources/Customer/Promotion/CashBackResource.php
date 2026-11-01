<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CashBackResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'title' => $this->resource->title,
            'cashback_type' => $this->resource->cashback_type,
            'cashback_amount' => (float) $this->resource->cashback_amount,
            'min_purchase' => (float) $this->resource->min_purchase,
            'max_discount' => (float) $this->resource->max_discount,
            'end_date' => $this->resource->end_date,
        ]);
    }
}
