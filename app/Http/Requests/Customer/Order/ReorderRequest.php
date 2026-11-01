<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class ReorderRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['order_id' => 'required|integer'];
    }
}
