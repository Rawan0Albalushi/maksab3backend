<?php

namespace App\Http\Requests\DeliveryMan\Auth;

use App\Http\Requests\BaseRequest;

class FirebaseVerifyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'sessionInfo' => 'required',
            'phoneNumber' => 'required',
            'code' => 'required',
        ];
    }
}
