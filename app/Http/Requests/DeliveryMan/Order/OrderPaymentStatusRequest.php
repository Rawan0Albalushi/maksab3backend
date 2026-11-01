<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;

class OrderPaymentStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
            'status' => 'required|in:paid',
        ];
    }
}
