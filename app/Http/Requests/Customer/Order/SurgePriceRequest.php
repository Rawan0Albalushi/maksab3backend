<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class SurgePriceRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['zone_id' => 'required', 'module_id' => 'required', 'date_time' => 'required'];
    }
}
