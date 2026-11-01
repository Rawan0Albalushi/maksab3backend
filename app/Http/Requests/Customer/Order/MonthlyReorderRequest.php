<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class MonthlyReorderRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['reminder_id' => 'required|integer'];
    }
}
