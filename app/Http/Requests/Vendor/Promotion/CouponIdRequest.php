<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

class CouponIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'coupon_id' => 'required',
        ];
    }
}
