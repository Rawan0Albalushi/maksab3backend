<?php

namespace App\Http\Requests\Vendor\Disbursement;

use App\Http\Requests\BaseRequest;

class WithdrawRequestStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'id' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'amount' => $this->input('amount'),
            'withdrawal_method_id' => $this->input('id'),
            'fields' => $this->all(),
        ];
    }
}
