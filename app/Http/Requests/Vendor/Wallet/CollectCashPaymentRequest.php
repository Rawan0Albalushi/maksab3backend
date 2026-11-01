<?php

namespace App\Http\Requests\Vendor\Wallet;

use App\Http\Requests\BaseRequest;

class CollectCashPaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'payment_gateway' => 'required',
            'amount' => 'required|numeric|min:.001',
            'callback' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'payment_gateway' => $this->input('payment_gateway'),
            'amount' => $this->input('amount'),
            'callback' => $this->has('callback') ? $this->input('callback') : session('callback'),
        ];
    }
}
