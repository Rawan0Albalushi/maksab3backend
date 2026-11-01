<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class AddonIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required',
        ];
    }
}
