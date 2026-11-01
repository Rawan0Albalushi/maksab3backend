<?php

namespace App\Http\Requests\Vendor\DeliveryMan;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class DeliveryManStoreRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'f_name' => 'required',
            'identity_type' => 'required|in:passport,driving_license,nid',
            'identity_number' => 'required',
            'email' => $this->emailRule('required', 'delivery_men'),
            'phone' => $this->phoneRule('required', 'delivery_men'),
            'password' => $this->passwordRule('required'),
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
            'store_id' => $this->vendorStoreId($this),
            'image' => $this->file('image'),
            'identity_image' => $this->file('identity_image') ?? [],
        ];
    }
}
