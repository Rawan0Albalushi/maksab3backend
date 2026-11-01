<?php

namespace App\Http\Requests\Customer\Order;

class OrderCancelRequest extends OrderActionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['reason' => 'required_without:note']);
    }

    public function messages(): array
    {
        return ['reason.required_without' => translate('You must enter note or reason')];
    }
}
