<?php

namespace App\Http\Requests\Vendor\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class VerifyTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->emailRule('required', Rule::exists('vendors', 'email')),
            'reset_token' => 'required',
        ];
    }
}
