<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;

class ProfileCompletionRequest extends BaseRequest
{
    public function rules(): array
    {
        $loginType = $this->input('login_type');

        return [
            'name' => 'required',
            'login_type' => 'required|in:otp,social,manual',
            'phone' => $this->phoneRule('required', $loginType === 'social' ? 'users,phone' : null),
            'email' => $this->emailRule('required', in_array($loginType, ['otp', 'manual']) ? 'users,email' : null),
        ];
    }

    public function payload(): array
    {
        return [
            'name' => $this->input('name'),
            'login_type' => $this->input('login_type'),
            'phone' => $this->input('phone'),
            'email' => $this->input('email'),
            'ref_code' => $this->input('ref_code'),
            'guest_id' => $this->input('guest_id'),
        ];
    }
}
