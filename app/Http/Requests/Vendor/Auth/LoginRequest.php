<?php

namespace App\Http\Requests\Vendor\Auth;

use App\Http\Requests\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->emailRule(),
            'password' => 'required|min:6',
            'vendor_type' => 'nullable|in:owner,employee',
        ];
    }
}
