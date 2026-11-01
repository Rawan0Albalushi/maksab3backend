<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;

class ParcelReturnRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
            'order_status' => 'required|in:returned',
            'return_otp' => 'required|numeric',
        ];
    }
}
