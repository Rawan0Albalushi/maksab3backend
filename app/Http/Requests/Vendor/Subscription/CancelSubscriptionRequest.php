<?php

namespace App\Http\Requests\Vendor\Subscription;

use App\Http\Requests\BaseRequest;

class CancelSubscriptionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'store_id' => 'required|integer',
            'subscription_id' => 'required|integer',
        ];
    }
}
