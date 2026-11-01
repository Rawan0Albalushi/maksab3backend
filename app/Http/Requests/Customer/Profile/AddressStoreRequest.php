<?php

namespace App\Http\Requests\Customer\Profile;

use App\Http\Requests\BaseRequest;

class AddressStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => $this->phoneRule(),
            'address' => 'required',
            'longitude' => 'required',
            'latitude' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'contact_person_name' => $this->input('contact_person_name'),
            'contact_person_number' => $this->input('contact_person_number'),
            'address_type' => $this->input('address_type'),
            'address' => $this->input('address'),
            'floor' => $this->input('floor'),
            'road' => $this->input('road'),
            'house' => $this->input('house'),
            'longitude' => $this->input('longitude'),
            'latitude' => $this->input('latitude'),
        ];
    }
}
