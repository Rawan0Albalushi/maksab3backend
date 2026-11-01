<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

class OfflinePaymentStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['order_id' => 'required', 'method_id' => 'required'];
    }

    public function payload(): array
    {
        return [
            'method_id' => $this->input('method_id'),
            'customer_note' => $this->input('customer_note'),
            'inputs' => $this->all(),
        ];
    }
}
