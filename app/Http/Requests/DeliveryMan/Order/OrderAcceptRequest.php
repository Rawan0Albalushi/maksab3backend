<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;

class OrderAcceptRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
        ];
    }

    public function payload(): array
    {
        return [
            'order_id' => $this->input('order_id'),
            'lat' => $this->input('lat'),
            'lng' => $this->input('lng'),
        ];
    }
}
