<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;

class ParcelReturnDateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
            'return_date' => 'required',
        ];
    }
}
