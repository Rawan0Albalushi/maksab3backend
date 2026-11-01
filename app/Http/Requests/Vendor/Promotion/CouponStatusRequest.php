<?php

namespace App\Http\Requests\Vendor\Promotion;

class CouponStatusRequest extends CouponIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => 'required',
        ]);
    }
}
