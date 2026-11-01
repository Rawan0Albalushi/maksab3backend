<?php

namespace App\Http\Requests\Customer\LoyaltyPoint;

use App\Http\Requests\BaseRequest;

class PointTransferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'point' => 'required|integer|min:1',
        ];
    }
}
