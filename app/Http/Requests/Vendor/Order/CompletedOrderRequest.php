<?php

namespace App\Http\Requests\Vendor\Order;

use App\Http\Requests\BaseRequest;

class CompletedOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:all,refunded,delivered',
        ];
    }
}
