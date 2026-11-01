<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class MonthlySubscriptionRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => 'required|integer'];
    }
}
