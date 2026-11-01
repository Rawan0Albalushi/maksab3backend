<?php

namespace App\Http\Requests\DeliveryMan\Auth;

use App\Http\Requests\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRule(),
            'password' => 'required|min:6',
            'type' => 'nullable|in:is_delivery,is_ride',
        ];
    }

    public function loginType(): string
    {
        return $this->filled('type') ? $this->input('type') : 'is_delivery';
    }
}
