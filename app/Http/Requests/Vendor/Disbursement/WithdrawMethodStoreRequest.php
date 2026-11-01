<?php

namespace App\Http\Requests\Vendor\Disbursement;

use App\Http\Requests\BaseRequest;

class WithdrawMethodStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'withdraw_method_id' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'withdraw_method_id' => $this->input('withdraw_method_id'),
            'fields' => $this->all(),
        ];
    }
}
