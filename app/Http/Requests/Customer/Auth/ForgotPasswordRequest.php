<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;

class ForgotPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'verification_method' => 'required|in:phone,email',
            'phone' => $this->phoneRule('required_if:verification_method,phone'),
            'email' => $this->emailRule('required_if:verification_method,email'),
        ];
    }
}
