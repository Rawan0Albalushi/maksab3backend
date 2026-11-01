<?php

namespace App\Http\Requests\Vendor\Auth;


class ResetPasswordRequest extends VerifyTokenRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'password' => $this->passwordRule('required'),
            'confirm_password' => 'required|same:password',
        ]);
    }

    public function messages(): array
    {
        return [
            'password.required' => translate('The password is required'),
        ];
    }
}
