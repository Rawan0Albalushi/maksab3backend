<?php

namespace App\Http\Requests\Vendor\DeliveryMan;

use App\Http\Requests\BaseRequest;

class DeliveryManIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'delivery_man_id' => 'required',
        ];
    }
}
