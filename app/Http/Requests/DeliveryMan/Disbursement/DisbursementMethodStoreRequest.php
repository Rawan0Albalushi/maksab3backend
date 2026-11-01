<?php

namespace App\Http\Requests\DeliveryMan\Disbursement;

use App\Http\Requests\BaseRequest;

class DisbursementMethodStoreRequest extends BaseRequest
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
            'disbursement_withdrawal_method_id' => $this->input('disbursement_withdrawal_method_id'),
            'fields' => $this->all(),
        ];
    }
}
