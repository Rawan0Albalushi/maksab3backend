<?php

namespace App\Http\Requests\Vendor\Profile;

use App\Rules\PhoneNumber;
use App\Http\Requests\BaseRequest;

class ProfileUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'f_name' => 'required',
            'l_name' => 'required',
            'phone' => PhoneNumber::rules('required', 'vendors,phone,'.$this->input('vendor')?->id),
            'password' => $this->passwordRule('nullable'),
        ];
    }

    public function messages(): array
    {
        return [
            'f_name.required' => translate('messages.First name is required'),
            'l_name.required' => translate('messages.Last name is required'),
        ];
    }

    public function payload(): array
    {
        return [
            'f_name' => $this->input('f_name'),
            'l_name' => $this->input('l_name'),
            'phone' => $this->input('phone'),
            'password' => $this->input('password'),
        ] + ($this->hasFile('image') ? ['image' => $this->file('image')] : []);
    }
}
