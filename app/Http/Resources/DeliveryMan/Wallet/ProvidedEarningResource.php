<?php

namespace App\Http\Resources\DeliveryMan\Wallet;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ProvidedEarningResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'amount' => (float) $this->resource->amount,
            'status' => 'Approved',
            'payment_time' => Helpers::time_date_format($this->resource->created_at),
        ]);
    }
}
