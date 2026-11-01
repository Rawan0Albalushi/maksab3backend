<?php

namespace App\Http\Requests\Customer\ProCustomer;

use App\Http\Requests\BaseRequest;

class SubscribeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:pro_customer_subscription_plans,id',
            'payment_type' => 'required|in:wallet,digital_payment,free_trial',
            'payment_method' => 'required_if:payment_type,digital_payment|string',
        ];
    }

    public function payload(): array
    {
        return [
            'plan_id' => $this->input('plan_id'),
            'payment_type' => $this->input('payment_type'),
            'payment_method' => $this->input('payment_method'),
            'payment_platform' => $this->input('payment_platform'),
            'callback' => $this->input('callback', session('callback')),
        ];
    }
}
