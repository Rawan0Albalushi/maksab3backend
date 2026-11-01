<?php

namespace App\Http\Requests\Customer\Order;

class OrderActionRequest extends GuestOrderRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['order_id' => 'required']);
    }
}
