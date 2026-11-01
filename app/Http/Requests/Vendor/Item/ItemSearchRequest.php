<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class ItemSearchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required',
        ];
    }
}
