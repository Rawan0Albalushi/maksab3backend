<?php

namespace App\Http\Requests\Customer\Wallet;

use App\Http\Requests\BaseRequest;

class FundStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'amount' => $this->input('amount'),
            'payment_method' => $this->input('payment_method'),
            'payment_platform' => $this->input('payment_platform'),
            'callback' => $this->has('callback') ? $this->input('callback') : session('callback'),
        ];
    }
}
