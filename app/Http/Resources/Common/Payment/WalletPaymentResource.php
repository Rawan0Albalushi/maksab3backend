<?php

namespace App\Http\Resources\Common\Payment;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class WalletPaymentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'status' => 'approved',
            'payment_time' => Helpers::time_date_format($this->resource->created_at),
        ]);
    }
}
