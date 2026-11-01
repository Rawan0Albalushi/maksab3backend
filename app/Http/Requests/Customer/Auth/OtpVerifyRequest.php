<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;

class OtpVerifyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'otp' => 'required',
            'verification_type' => 'required|in:phone,email',
            'phone' => $this->phoneRule('required_if:verification_type,phone'),
            'email' => $this->emailRule('required_if:verification_type,email'),
            'login_type' => 'required|in:manual,otp',
        ];
    }

    public function payload(): array
    {
        return [
            'otp' => $this->input('otp'),
            'verification_type' => $this->input('verification_type'),
            'phone' => $this->input('phone'),
            'email' => $this->input('email'),
            'login_type' => $this->input('login_type'),
            'guest_id' => $this->input('guest_id'),
        ];
    }
}
