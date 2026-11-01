<?php

namespace App\Http\Requests\Common\Payment;

use App\Http\Requests\BaseRequest;

class WithdrawMethodIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required',
        ];
    }
}
