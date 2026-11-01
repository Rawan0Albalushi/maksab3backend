<?php

namespace App\Http\Resources\DeliveryMan\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ReferralEarningResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'transaction_id' => $this->resource->transaction_id,
            'amount' => $this->resource->amount,
            'created_at' => $this->resource->created_at,
        ];
    }
}
