<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;

class OrderListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_status' => 'nullable|string',
        ];
    }

    public function filters(): array
    {
        return ['order_status' => $this->input('order_status')];
    }
}
