<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;

class FirebaseVerifyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'session_info' => 'required',
            'phone' => $this->phoneRule(),
            'otp' => 'required',
            'login_type' => 'required|in:manual,otp',
        ];
    }

    public function payload(): array
    {
        return [
            'session_info' => $this->input('session_info'),
            'phone' => $this->input('phone'),
            'otp' => $this->input('otp'),
            'login_type' => $this->input('login_type'),
            'guest_id' => $this->input('guest_id'),
        ];
    }
}
