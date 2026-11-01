<?php

namespace App\Http\Requests\Vendor\DeliveryMan;

class DeliveryManStatusRequest extends DeliveryManIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['status' => 'required|boolean']);
    }
}
