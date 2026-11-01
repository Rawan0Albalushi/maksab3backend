<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class ReviewCancelRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
        ];
    }
}
