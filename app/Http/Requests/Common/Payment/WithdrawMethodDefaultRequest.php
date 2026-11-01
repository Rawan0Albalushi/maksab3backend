<?php

namespace App\Http\Requests\Common\Payment;

class WithdrawMethodDefaultRequest extends WithdrawMethodIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['is_default' => 'required']);
    }
}
