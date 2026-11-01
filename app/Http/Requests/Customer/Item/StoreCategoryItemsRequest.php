<?php

namespace App\Http\Requests\Customer\Item;

use App\Http\Requests\BaseRequest;

class StoreCategoryItemsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'store_id' => 'required',
        ];
    }
}
