<?php

namespace App\Http\Requests\Common\System;

use App\Http\Requests\BaseRequest;

class PlaceDetailsRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['placeid' => 'required'];
    }

    public function placeId(): string
    {
        return (string) $this->input('placeid');
    }
}
