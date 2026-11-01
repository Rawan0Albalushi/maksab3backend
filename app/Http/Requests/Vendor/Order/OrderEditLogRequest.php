<?php

namespace App\Http\Requests\Vendor\Order;

use App\Http\Requests\BaseRequest;

class OrderEditLogRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
        ];
    }
}
