<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class GuestOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['guest_id' => $this->user ? 'nullable' : 'required'];
    }
}
