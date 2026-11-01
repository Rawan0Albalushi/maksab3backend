<?php

namespace App\Http\Requests\Vendor\Subscription;

use App\Http\Requests\BaseRequest;

class ProductLimitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'store_id' => 'required|integer',
            'package_id' => 'required|integer|exists:subscription_packages,id',
        ];
    }
}
