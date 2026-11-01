<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

class CouponSearchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => 'required',
        ];
    }
}
