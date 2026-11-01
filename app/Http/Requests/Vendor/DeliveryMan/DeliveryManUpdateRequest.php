<?php

namespace App\Http\Requests\Vendor\DeliveryMan;

use App\Http\Requests\BaseRequest;

class DeliveryManUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'f_name' => 'required',
            'email' => $this->emailRule('required', 'delivery_men,email,'.$id),
            'phone' => $this->phoneRule('required', 'delivery_men,phone,'.$id),
            'password' => $this->passwordRule('nullable'),
        ];
    }

    public function payload(): array
    {
        return [
            'f_name' => $this->input('f_name'),
            'l_name' => $this->input('l_name'),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'identity_number' => $this->input('identity_number'),
            'identity_type' => $this->input('identity_type'),
            'vehicle_id' => $this->input('vehicle_id'),
            'password' => $this->input('password'),
            'image' => $this->file('image'),
            'identity_image' => $this->file('identity_image'),
        ];
    }
}
