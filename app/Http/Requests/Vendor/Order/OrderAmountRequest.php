<?php

namespace App\Http\Requests\Vendor\Order;

class OrderAmountRequest extends OrderIdRequest
{
    public function payload(): array
    {
        return $this->input();
    }
}
