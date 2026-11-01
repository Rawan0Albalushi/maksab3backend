<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class StoreCategoryIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required',
        ];
    }
}
