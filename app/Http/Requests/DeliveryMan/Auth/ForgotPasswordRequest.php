<?php

namespace App\Http\Requests\DeliveryMan\Auth;

use App\Http\Requests\BaseRequest;

class ForgotPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRule(),
        ];
    }
}
