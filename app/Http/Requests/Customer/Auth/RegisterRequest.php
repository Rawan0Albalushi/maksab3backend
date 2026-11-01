<?php

namespace App\Http\Requests\Customer\Auth;

use App\Rules\EmailAddress;
use App\Rules\PhoneNumber;
use App\Http\Requests\BaseRequest;

class RegisterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => EmailAddress::rules('nullable', 'users'),
            'phone' => PhoneNumber::rules('required', 'users'),
            'password' => $this->basicPasswordRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => translate('messages.Name is required'),
        ];
    }

    public function payload(): array
    {
        return [
            'name' => $this->input('name'),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'password' => $this->input('password'),
            'ref_code' => $this->input('ref_code'),
        ];
    }
}
