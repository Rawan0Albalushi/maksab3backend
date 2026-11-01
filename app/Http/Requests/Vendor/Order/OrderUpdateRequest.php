<?php

namespace App\Http\Requests\Vendor\Order;

use App\Http\Requests\BaseRequest;

class OrderUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
            'carts' => 'required|array|min:1',
            'carts.*.item_id' => 'required',
            'carts.*.quantity' => 'required|numeric|min:1',
        ];
    }

    public function payload(): array
    {
        return [
            'carts' => $this->input('carts'),
            'order_attachment' => $this->hasFile('order_attachment') ? $this->file('order_attachment') : null,
        ];
    }
}
