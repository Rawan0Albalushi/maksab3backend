<?php

namespace App\Http\Requests\DeliveryMan\Wallet;

use App\Http\Requests\BaseRequest;

class LoyaltyPointConvertRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'points' => 'required|numeric|min:0.001',
        ];
    }
}
