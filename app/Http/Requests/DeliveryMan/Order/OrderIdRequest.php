<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;

class OrderIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
        ];
    }
}
