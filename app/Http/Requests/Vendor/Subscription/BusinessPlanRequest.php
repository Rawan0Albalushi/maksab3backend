<?php

namespace App\Http\Requests\Vendor\Subscription;

use App\Http\Requests\BaseRequest;

class BusinessPlanRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'store_id' => 'required|integer',
            'payment' => 'nullable',
            'business_plan' => 'required|in:subscription,commission',
            'package_id' => 'nullable|required_if:business_plan,subscription|integer',
            'payment_gateway' => 'nullable|required_if:business_plan,subscription',
            'payment_platform' => 'nullable|in:app,web',
        ];
    }
}
