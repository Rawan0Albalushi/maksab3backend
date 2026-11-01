<?php

namespace App\Http\Requests\DeliveryMan\Chat;

use App\Http\Requests\BaseRequest;

class AutoMessageStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'question_id' => 'required',
        ];
    }
}
