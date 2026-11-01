<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;

class VerifyResetTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reset_token' => 'required',
            'verification_method' => 'required|in:phone,email',
            'phone' => $this->phoneRule('nullable|required_if:verification_method,phone'),
            'email' => $this->emailRule('nullable|required_if:verification_method,email'),
        ];
    }
}
