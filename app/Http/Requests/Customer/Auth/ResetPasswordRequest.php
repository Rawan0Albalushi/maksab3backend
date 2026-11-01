<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ResetPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reset_token' => 'required',
            'password' => $this->basicPasswordRule(),
            'verification_method' => 'required|in:phone,email',
            'phone' => $this->phoneRule('nullable|required_if:verification_method,phone', Rule::exists('users', 'phone')),
            'email' => $this->emailRule('nullable|required_if:verification_method,email', Rule::exists('users', 'email')),
            'confirm_password' => 'required|same:password',
        ];
    }
}
