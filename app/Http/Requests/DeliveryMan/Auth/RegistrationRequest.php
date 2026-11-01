<?php

namespace App\Http\Requests\DeliveryMan\Auth;

use App\Http\Requests\BaseRequest;

class RegistrationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'f_name' => 'required',
            'l_name' => 'nullable',
            'identity_type' => 'required|in:passport,driving_license,nid',
            'identity_number' => 'required',
            'email' => $this->emailRule('required', 'delivery_men'),
            'phone' => $this->phoneRule('required', 'delivery_men'),
            'password' => $this->passwordRule('required'),
            'zone_id' => 'required',
            'vehicle_id' => 'required_if:type,is_delivery',
            'earning' => 'required',
            'type' => 'required|in:is_delivery,is_ride',
            'referral_code' => 'nullable',
            'image' => $this->imageRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'f_name.required' => translate('messages.First name is required'),
            'zone_id.required' => translate('messages.Select a zone'),
            'earning.required' => translate('Select deliveryman type'),
            'vehicle_id.required' => translate('messages.Select a vehicle'),
            'password.required' => translate('The password is required'),
        ];
    }
}
