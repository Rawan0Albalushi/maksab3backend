<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class ItemIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required',
        ];
    }

    public function payload(): array
    {
        return $this->input();
    }
}
