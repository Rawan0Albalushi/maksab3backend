<?php

namespace App\Http\Requests\DeliveryMan\Auth;

use App\Http\Requests\BaseRequest;

class VerifyTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRule(),
            'reset_token' => 'required',
        ];
    }
}
