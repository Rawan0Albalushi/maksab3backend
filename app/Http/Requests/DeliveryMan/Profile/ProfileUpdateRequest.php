<?php

namespace App\Http\Requests\DeliveryMan\Profile;

use App\Http\Requests\BaseRequest;

class ProfileUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => $this->emailRule('required', 'delivery_men,email,'.auth('delivery_men')->id()),
            'password' => $this->passwordRule('nullable'),
        ];
    }

    public function messages(): array
    {
        return [
            'f_name.required' => 'First name is required!',
            'l_name.required' => 'Last name is required!',
        ];
    }

    public function payload(): array
    {
        return [
            'f_name' => $this->input('f_name'),
            'l_name' => $this->input('l_name'),
            'email' => $this->input('email'),
            'password' => $this->input('password'),
            'vehicle_id' => $this->input('vehicle_id'),
            'image' => $this->file('image'),
        ];
    }
}
