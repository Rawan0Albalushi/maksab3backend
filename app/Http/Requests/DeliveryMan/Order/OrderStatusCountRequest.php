<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;

class OrderStatusCountRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type' => 'required|in:current,history',
        ];
    }
}
