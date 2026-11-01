<?php

namespace App\Http\Requests\Vendor\Promotion;

class CouponUpdateRequest extends CouponStoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'coupon_id' => 'required',
            'code' => 'required|max:100|unique:coupons,code,' . $this->input('coupon_id'),
        ]);
    }
}
