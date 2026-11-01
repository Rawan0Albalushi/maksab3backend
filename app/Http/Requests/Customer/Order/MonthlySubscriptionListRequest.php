<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class MonthlySubscriptionListRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['module_type' => 'nullable|string|in:grocery,pharmacy'];
    }
}
