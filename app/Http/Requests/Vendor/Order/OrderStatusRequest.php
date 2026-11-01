<?php

namespace App\Http\Requests\Vendor\Order;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Config;

class OrderStatusRequest extends OrderIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'reason' => 'required_if:status,canceled',
            'status' => 'required|in:confirmed,processing,handover,delivered,canceled',
            'order_proof' => 'array|max:5',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('otp', 'required', fn () => Config::get('order_delivery_verification') == 1 && $this->input('status') === 'delivered');
    }

    public function payload(): array
    {
        return $this->input() + ['order_proof' => $this->file('order_proof') ?? []];
    }
}
