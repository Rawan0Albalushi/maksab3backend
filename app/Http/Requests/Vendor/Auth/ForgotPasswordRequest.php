<?php

namespace App\Http\Requests\Vendor\Auth;

use App\Http\Requests\BaseRequest;

class ForgotPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->emailRule(),
        ];
    }
}
