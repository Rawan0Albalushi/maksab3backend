<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;

class CouponApplyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required',
            'store_id' => 'required',
        ];
    }
}
