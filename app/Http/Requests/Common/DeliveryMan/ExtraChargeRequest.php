<?php

namespace App\Http\Requests\Common\DeliveryMan;

use App\Http\Requests\BaseRequest;

class ExtraChargeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'distance' => 'required',
        ];
    }

    public function distance(): mixed
    {
        return $this->input('distance') ?? 1;
    }
}
